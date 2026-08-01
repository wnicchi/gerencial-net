<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RequerimientoController — ABM de Requerimientos (requerimientos.scx) con
 * documentación asociada.
 *
 * Tabla requerimientos: REQ_COD, REQ_DES (descripción), REQ_DIA (días),
 * REQ_OBS (observaciones), REQ_COM (documentación común a todos los clientes).
 *
 * Los documentos se guardan en la tabla compartida `documentos` (DOC_TIP='C',
 * DOC_REF=código del requerimiento) y físicamente en la biblioteca digital
 * (proceso DOCUMENTACION). Al eliminar uno se mueve a `hdocumentos` (historial).
 */
class RequerimientoController extends Controller
{
    private const EXT_BLOQUEADAS = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'];

    /** @route GET /api/requerimientos?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('requerimientos');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where('REQ_DES', 'like', "%{$b}%");
        }
        return response()->json($q->orderBy('REQ_DES')->get()->map(fn ($r) => [
            'cod'           => (int) $r->REQ_COD,
            'descripcion'   => trim((string) $r->REQ_DES),
            'dias'          => (int) $r->REQ_DIA,
            'observaciones' => trim((string) $r->REQ_OBS),
            'comun'         => (bool) $r->REQ_COM,
        ])->values());
    }

    /** @route GET /api/requerimientos/init — tipos de documento. */
    public function init(): JsonResponse
    {
        return response()->json([
            'tipos' => DB::table('tipo_doc')->orderBy('TDO_DES')->get(['TDO_COD', 'TDO_DES'])
                ->map(fn ($t) => ['cod' => trim((string) $t->TDO_COD), 'nombre' => trim((string) $t->TDO_DES)])->values(),
        ]);
    }

    /** @route POST /api/requerimientos */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('requerimientos')->max('REQ_COD') + 1;
        DB::table('requerimientos')->insert($this->datos($d) + ['REQ_COD' => $cod]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/requerimientos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('requerimientos')->where('REQ_COD', $cod)->exists()) {
            return response()->json(['message' => 'Requerimiento no encontrado.'], 404);
        }
        DB::table('requerimientos')->where('REQ_COD', $cod)->update($this->datos($this->validar($request)));
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/requerimientos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('documentos')->where('DOC_TIP', 'C')->where('DOC_REF', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el requerimiento tiene documentos asociados.'], 422);
        }
        $b = DB::table('requerimientos')->where('REQ_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Requerimiento no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route GET /api/requerimientos/{cod}/documentos — activos + historial. */
    public function documentos(int $cod): JsonResponse
    {
        if (!DB::table('requerimientos')->where('REQ_COD', $cod)->exists()) {
            return response()->json(['message' => 'Requerimiento no encontrado.'], 404);
        }
        return response()->json([
            'activos'   => $this->docs('documentos', $cod),
            'historial' => $this->docs('hdocumentos', $cod),
        ]);
    }

    /** @route POST /api/requerimientos/{cod}/documento — adjunta un documento. */
    public function subirDocumento(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'tipo'    => 'required|string|max:2',
            'obs'     => 'nullable|string|max:60',
            'archivo' => 'required|file|max:51200',
        ], ['tipo.required' => 'Debe ingresar el tipo de documento.', 'archivo.required' => 'Debe seleccionar el documento.']);

        if (!DB::table('requerimientos')->where('REQ_COD', $cod)->exists()) {
            return response()->json(['message' => 'Requerimiento no encontrado.'], 404);
        }
        $tipo = mb_strtoupper(trim($d['tipo']));
        $file = $request->file('archivo');
        $ext  = mb_strtoupper((string) $file->getClientOriginalExtension());
        if ($ext === '' || in_array($ext, self::EXT_BLOQUEADAS, true)) {
            return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
        }
        $tdd      = trim((string) DB::table('tipo_doc')->where('TDO_COD', $tipo)->value('TDO_DES'));
        $nombre   = mb_strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $tam      = (int) $file->getSize();
        $ahora    = now();
        $usuario  = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        $terminal = mb_substr(gethostname() ?: 'RRHH.NET', 0, 20);

        $docOrd = (int) DB::table('documentos')->max('DOC_ORD') + 1;
        $docNro = (int) DB::table('documentos')->where('DOC_TDO', $tipo)->max('DOC_NRO') + 1;

        $unico = DB::table('documentos')->insertGetId([
            'DOC_ORD' => $docOrd, 'DOC_TDO' => $tipo, 'DOC_NRO' => $docNro, 'DOC_TIP' => 'C', 'DOC_REF' => $cod,
            'DOC_DET' => mb_substr('Requerimiento ' . $cod, 0, 60), 'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES',
            'DOC_TDD' => mb_substr($tdd, 0, 30), 'DOC_FUL' => mb_substr((string) $file->getClientOriginalName(), 0, 120),
            'DOC_DIR' => '', 'DOC_NOM' => mb_substr($nombre, 0, 120), 'DOC_EXT' => mb_substr($ext, 0, 5),
            'DOC_CRE' => $ahora->format('d/m/Y H:i'), 'DOC_TAM' => $tam, 'DOC_KB' => (int) round($tam / 1024),
            'DOC_OBS' => mb_substr(trim((string) ($d['obs'] ?? '')), 0, 60), 'DOC_USU' => $usuario,
            'DOC_TER' => $terminal, 'DOC_GRA' => $ahora->format('d/m/y H:i'),
        ], 'UNICO');

        try {
            (new BibliotecaDigitalService())->archivoDigitalGuardar(
                config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->referencia($tipo, $docNro, $cod, $ext), $ext,
                file_get_contents($file->getRealPath()), $usuario
            );
        } catch (\Throwable $e) {
            DB::table('documentos')->where('UNICO', $unico)->delete();
            Log::error("[reqAddDoc] req {$cod}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }
        return response()->json(['ok' => true, 'activos' => $this->docs('documentos', $cod), 'historial' => $this->docs('hdocumentos', $cod)]);
    }

    /** @route GET /api/requerimientos/documento/{id}/ver — visualiza un documento (activo o histórico). */
    public function verDocumento(Request $request, int $id)
    {
        $hist = $request->boolean('historial');
        $doc  = DB::table($hist ? 'hdocumentos' : 'documentos')->where('UNICO', $id)->where('DOC_TIP', 'C')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $ref  = $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, mb_strtoupper(trim((string) $doc->DOC_EXT)));
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $ref);
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** @route DELETE /api/requerimientos/documento/{id} — mueve a historial y borra el activo. */
    public function eliminarDocumento(Request $request, int $id): JsonResponse
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'C')->first();
        if (!$doc) {
            return response()->json(['message' => 'Documento no encontrado.'], 404);
        }
        $usuario = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);

        // Copiar a hdocumentos (historial) con fecha/usuario de eliminación.
        $fila = (array) $doc;
        unset($fila['UNICO']);
        $fila['DOC_FEL'] = now()->format('Y-m-d');
        $fila['DOC_UEL'] = $usuario;
        DB::table('hdocumentos')->insert($fila);

        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, mb_strtoupper(trim((string) $doc->DOC_EXT)))); }
        catch (\Throwable $e) { Log::warning("[reqDelDoc] {$id}: " . $e->getMessage()); }

        DB::table('documentos')->where('UNICO', $id)->delete();
        return response()->json(['ok' => true, 'activos' => $this->docs('documentos', (int) $doc->DOC_REF), 'historial' => $this->docs('hdocumentos', (int) $doc->DOC_REF)]);
    }

    /** Documentos de un requerimiento (DOC_TIP='C', DOC_REF=req) en la tabla indicada. */
    private function docs(string $tabla, int $cod): array
    {
        return DB::table($tabla)->where('DOC_TIP', 'C')->where('DOC_REF', $cod)->orderByDesc('DOC_ORD')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->UNICO, 'nro' => (int) $r->DOC_ORD, 'tipo' => trim((string) $r->DOC_TDO),
                'detalle' => trim((string) $r->DOC_TDD), 'nombre' => trim((string) $r->DOC_NOM),
                'ext' => trim((string) $r->DOC_EXT), 'creado' => trim((string) $r->DOC_CRE),
                'observaciones' => trim((string) $r->DOC_OBS), 'usuario' => trim((string) $r->DOC_USU),
                'eliminado' => isset($r->DOC_FEL) && $r->DOC_FEL && substr((string) $r->DOC_FEL, 0, 4) !== '1900' ? substr((string) $r->DOC_FEL, 0, 10) : '',
            ])->values()->all();
    }

    /** Identificación del archivo en la biblioteca: TDO + NRO(6) + REF(6) + .EXT */
    private function referencia(string $tipo, int $nro, int $ref, string $ext): string
    {
        return $tipo . str_pad((string) $nro, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $ref, 6, '0', STR_PAD_LEFT) . '.' . $ext;
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion'   => 'required|string|max:100',
            'dias'          => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string|max:2000',
            'comun'         => 'boolean',
        ]);
    }

    private function datos(array $d): array
    {
        return [
            'REQ_DES' => trim($d['descripcion']),
            'REQ_DIA' => (int) ($d['dias'] ?? 0),
            'REQ_OBS' => trim((string) ($d['observaciones'] ?? '')),
            'REQ_COM' => !empty($d['comun']) ? 1 : 0,
        ];
    }
}
