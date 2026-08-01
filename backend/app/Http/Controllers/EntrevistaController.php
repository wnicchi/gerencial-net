<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EntrevistaController — ABM de Entrevistas (entrevistas.scx).
 *
 * Tabla entrevistas (ETV_*). Dos solapas: Datos del Entrevistado y Documentación
 * Recibida (tabla `documentos`, DOC_TIP='M', DOC_REF=código de entrevistado).
 * Además una foto guardada en la biblioteca digital (proceso ENTREVISTAS_FOTO,
 * identificación "ETV{cod}.JPG").
 */
class EntrevistaController extends Controller
{
    private const EXT_BLOQUEADAS = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'];

    /** @route GET /api/entrevistas/init — sectores + tipos de documento. */
    public function init(): JsonResponse
    {
        return response()->json([
            'sectores' => DB::table('sector')->orderBy('SEC_DES')->get(['SEC_COD', 'SEC_DES'])
                ->map(fn ($s) => ['cod' => (int) $s->SEC_COD, 'nombre' => trim((string) $s->SEC_DES)])->values(),
            'tipos' => DB::table('tipo_doc')->orderBy('TDO_DES')->get(['TDO_COD', 'TDO_DES'])
                ->map(fn ($t) => ['cod' => trim((string) $t->TDO_COD), 'nombre' => trim((string) $t->TDO_DES)])->values(),
        ]);
    }

    /** @route GET /api/entrevistas?buscar= — listado para navegar/buscar. */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('entrevistas');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where(function ($w) use ($b) {
                $w->where('ETV_NOM', 'like', "%{$b}%");
                if (ctype_digit($b)) {
                    $w->orWhere('ETV_COD', (int) $b);
                }
            });
        }
        return response()->json($q->orderByDesc('ETV_COD')->limit(300)->get()->map(fn ($r) => [
            'cod'    => (int) $r->ETV_COD,
            'nombre' => trim((string) $r->ETV_NOM),
            'fecha'  => $this->fecha($r->ETV_FEC),
            'lugar'  => trim((string) $r->ETV_LUG),
            'sector' => trim((string) $r->ETV_SED),
        ])->values());
    }

    /** @route GET /api/entrevistas/{cod} — un entrevistado + sus documentos. */
    public function show(int $cod): JsonResponse
    {
        $e = DB::table('entrevistas')->where('ETV_COD', $cod)->first();
        if (!$e) {
            return response()->json(['message' => 'Entrevistado no encontrado.'], 404);
        }
        return response()->json([
            'entrevista' => $this->mapear($e),
            'documentos' => $this->docs($cod),
        ]);
    }

    /** @route POST /api/entrevistas — alta. */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('entrevistas')->max('ETV_COD') + 1;
        DB::table('entrevistas')->insert(Registro::completar('entrevistas',
            $this->datos($d) + ['ETV_COD' => $cod, 'ETV_FEC' => ($d['fecha'] ?? '') ?: now()->format('Y-m-d')]));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/entrevistas/{cod} — modificación. */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('entrevistas')->where('ETV_COD', $cod)->exists()) {
            return response()->json(['message' => 'Entrevistado no encontrado.'], 404);
        }
        DB::table('entrevistas')->where('ETV_COD', $cod)->update($this->datos($this->validar($request)));
        return response()->json(['ok' => true]);
    }

    /** @route GET /api/entrevistas/listado — datos para el informe PDF (filtros orden/sector/documento/foto). */
    public function listado(Request $request): JsonResponse
    {
        $orden     = $request->query('orden', 'nombre');       // nombre | codigo | fecha
        $sector    = (int) $request->query('sector', 0);       // 0 = todas
        $documento = (int) $request->query('documento', 0);    // 0 = todos
        $conFoto   = $request->boolean('con_foto');

        $q = DB::table('entrevistas');
        if ($sector > 0)    { $q->where('ETV_SEC', $sector); }
        if ($documento > 0) { $q->where('ETV_DOC', $documento); }
        $col = match ($orden) { 'codigo' => 'ETV_COD', 'fecha' => 'ETV_FEC', default => 'ETV_NOM' };
        $rows = $q->orderBy($col)->get();

        $svc = $conFoto ? new BibliotecaDigitalService() : null;
        $items = $rows->map(function ($e) use ($conFoto, $svc) {
            $it = [
                'cod'       => (int) $e->ETV_COD,
                'nombre'    => trim((string) $e->ETV_NOM),
                'tipo_doc'  => trim((string) $e->ETV_TDO),
                'numero_doc' => (int) $e->ETV_DOC,
                'fecha'     => $this->fecha($e->ETV_FEC),
                'sector'    => trim((string) $e->ETV_SED),
                'subsector' => trim((string) $e->ETV_SUD),
                'formacion' => trim((string) $e->ETV_FOR_ACA),
                'lugar'     => trim((string) $e->ETV_LUG),
                'telefono'  => trim((string) $e->ETV_TEL),
                'email'     => mb_strtolower(trim((string) $e->ETV_EMA)),
                'domicilio' => trim((string) $e->ETV_DOM),
                'notas'     => trim((string) $e->ETV_NOT),
            ];
            if ($conFoto) {
                $it['foto'] = $svc->archivoDigitalRecuperarDataUrl(config('rrhh.docs_sistema'), 'ENTREVISTAS_FOTO', 'ETV' . (int) $e->ETV_COD . '.JPG');
            }
            return $it;
        })->values();

        return response()->json(['items' => $items, 'total' => $items->count()]);
    }

    // ── Foto (biblioteca digital, proceso ENTREVISTAS_FOTO) ─────

    /** @route GET /api/entrevistas/{cod}/foto — devuelve { foto: dataURL|null }. */
    public function foto(int $cod): JsonResponse
    {
        $foto = (new BibliotecaDigitalService())->archivoDigitalRecuperarDataUrl(
            config('rrhh.docs_sistema'), 'ENTREVISTAS_FOTO', 'ETV' . $cod . '.JPG');
        return response()->json(['foto' => $foto]);
    }

    /** @route POST /api/entrevistas/{cod}/foto — sube/reemplaza la foto (JPG). */
    public function subirFoto(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['foto' => 'required|image|max:20480']);
        if (!DB::table('entrevistas')->where('ETV_COD', $cod)->exists()) {
            return response()->json(['message' => 'Entrevistado no encontrado.'], 404);
        }
        $usuario = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        try {
            (new BibliotecaDigitalService())->archivoDigitalGuardar(
                config('rrhh.docs_sistema'), 'ENTREVISTAS_FOTO', 'ETV' . $cod . '.JPG', 'JPG',
                file_get_contents($d['foto']->getRealPath()), $usuario);
        } catch (\Throwable $e) {
            Log::error("[entrevistaFoto] {$cod}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar la foto.'], 500);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/entrevistas/{cod}/foto — elimina la foto. */
    public function eliminarFoto(int $cod): JsonResponse
    {
        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'ENTREVISTAS_FOTO', 'ETV' . $cod . '.JPG'); }
        catch (\Throwable $e) { Log::warning("[entrevistaDelFoto] {$cod}: " . $e->getMessage()); }
        return response()->json(['ok' => true]);
    }

    // ── Documentos (DOC_TIP='M') ────────────────────────────────

    /** @route POST /api/entrevistas/{cod}/documento — adjunta un documento. */
    public function subirDocumento(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'tipo'    => 'required|string|max:2',
            'obs'     => 'nullable|string|max:60',
            'archivo' => 'required|file|max:51200',
        ], ['tipo.required' => 'Debe ingresar el tipo de documento.', 'archivo.required' => 'Debe seleccionar el documento.']);

        if (!DB::table('entrevistas')->where('ETV_COD', $cod)->exists()) {
            return response()->json(['message' => 'Entrevistado no encontrado.'], 404);
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
            'DOC_ORD' => $docOrd, 'DOC_TDO' => $tipo, 'DOC_NRO' => $docNro, 'DOC_TIP' => 'M', 'DOC_REF' => $cod,
            'DOC_DET' => mb_substr('Entrevistado ' . $cod, 0, 60), 'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES',
            'DOC_TDD' => mb_substr($tdd, 0, 30), 'DOC_FUL' => mb_substr((string) $file->getClientOriginalName(), 0, 120),
            'DOC_DIR' => '', 'DOC_NOM' => mb_substr($nombre, 0, 120), 'DOC_EXT' => mb_substr($ext, 0, 5),
            'DOC_CRE' => $ahora->format('d/m/Y H:i'), 'DOC_TAM' => $tam, 'DOC_KB' => (int) round($tam / 1024),
            'DOC_OBS' => mb_substr(trim((string) ($d['obs'] ?? '')), 0, 60), 'DOC_USU' => $usuario,
            'DOC_TER' => $terminal, 'DOC_GRA' => $ahora->format('d/m/y H:i'),
        ], 'UNICO');

        try {
            (new BibliotecaDigitalService())->archivoDigitalGuardar(
                config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->referencia($tipo, $docNro, $cod, $ext), $ext,
                file_get_contents($file->getRealPath()), $usuario);
        } catch (\Throwable $e) {
            DB::table('documentos')->where('UNICO', $unico)->delete();
            Log::error("[entrevistaAddDoc] {$cod}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }
        return response()->json(['ok' => true, 'documentos' => $this->docs($cod)]);
    }

    /** @route GET /api/entrevistas/documento/{id}/ver — visualiza un documento. */
    public function verDocumento(int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'M')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $ref  = $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, mb_strtoupper(trim((string) $doc->DOC_EXT)));
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $ref);
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** @route DELETE /api/entrevistas/documento/{id} — elimina un documento. */
    public function eliminarDocumento(int $id): JsonResponse
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'M')->first();
        if (!$doc) {
            return response()->json(['message' => 'Documento no encontrado.'], 404);
        }
        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, mb_strtoupper(trim((string) $doc->DOC_EXT)))); }
        catch (\Throwable $e) { Log::warning("[entrevistaDelDoc] {$id}: " . $e->getMessage()); }
        DB::table('documentos')->where('UNICO', $id)->delete();
        return response()->json(['ok' => true, 'documentos' => $this->docs((int) $doc->DOC_REF)]);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function docs(int $cod): array
    {
        return DB::table('documentos')->where('DOC_TIP', 'M')->where('DOC_REF', $cod)->orderByDesc('DOC_ORD')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->UNICO, 'nro' => (int) $r->DOC_ORD, 'tipo' => trim((string) $r->DOC_TDO),
                'detalle' => trim((string) $r->DOC_TDD), 'nombre' => trim((string) $r->DOC_NOM),
                'ext' => trim((string) $r->DOC_EXT), 'creado' => trim((string) $r->DOC_CRE),
                'observaciones' => trim((string) $r->DOC_OBS), 'usuario' => trim((string) $r->DOC_USU),
            ])->values()->all();
    }

    private function mapear(object $e): array
    {
        return [
            'cod'            => (int) $e->ETV_COD,
            'tipo_documento' => trim((string) $e->ETV_TDO),
            'numero_documento' => (int) $e->ETV_DOC,
            'nombre'         => trim((string) $e->ETV_NOM),
            'domicilio'      => trim((string) $e->ETV_DOM),
            'email'          => mb_strtolower(trim((string) $e->ETV_EMA)),
            'telefono'       => trim((string) $e->ETV_TEL),
            'fecha'          => $this->fecha($e->ETV_FEC),
            'lugar'          => trim((string) $e->ETV_LUG),
            'sector_cod'     => (int) $e->ETV_SEC,
            'sector_desc'    => trim((string) $e->ETV_SED),
            'subsector'      => trim((string) $e->ETV_SUD),
            'formacion'      => trim((string) $e->ETV_FOR_ACA),
            'notas'          => trim((string) $e->ETV_NOT),
        ];
    }

    private function datos(array $d): array
    {
        return [
            'ETV_TDO'     => mb_substr(mb_strtoupper(trim((string) ($d['tipo_documento'] ?? ''))), 0, 3),
            'ETV_DOC'     => (int) ($d['numero_documento'] ?? 0),
            'ETV_NOM'     => mb_substr(mb_strtoupper(trim($d['nombre'])), 0, 100),
            'ETV_DOM'     => mb_substr(mb_strtoupper(trim($d['domicilio'])), 0, 100),
            'ETV_EMA'     => mb_substr(mb_strtolower(trim((string) ($d['email'] ?? ''))), 0, 100),
            'ETV_TEL'     => mb_substr(mb_strtoupper(trim($d['telefono'])), 0, 100),
            'ETV_LUG'     => mb_substr(mb_strtoupper(trim((string) ($d['lugar'] ?? ''))), 0, 100),
            'ETV_SEC'     => (int) $d['sector_cod'],
            'ETV_SED'     => mb_substr(mb_strtoupper(trim((string) ($d['sector_desc'] ?? ''))), 0, 100),
            'ETV_SUD'     => mb_substr(mb_strtoupper(trim($d['subsector'])), 0, 100),
            'ETV_FOR_ACA' => mb_substr(mb_strtoupper(trim($d['formacion'])), 0, 100),
            'ETV_NOT'     => trim((string) ($d['notas'] ?? '')),
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'           => 'required|string|max:100',
            'domicilio'        => 'required|string|max:100',
            'telefono'         => 'required|string|max:100',
            'sector_cod'       => 'required|integer|min:1',
            'sector_desc'      => 'nullable|string|max:100',
            'subsector'        => 'required|string|max:100',
            'formacion'        => 'required|string|max:100',
            'tipo_documento'   => 'nullable|string|max:3',
            'numero_documento' => 'nullable|integer',
            'email'            => 'nullable|string|max:100',
            'lugar'            => 'nullable|string|max:100',
            'fecha'            => 'nullable|date',
            'notas'            => 'nullable|string|max:2000',
        ], [
            'nombre.required'     => 'Debe ingresar el nombre del entrevistado.',
            'domicilio.required'  => 'Debe ingresar el domicilio del entrevistado.',
            'telefono.required'   => 'Debe ingresar el teléfono del entrevistado.',
            'sector_cod.required' => 'Debe ingresar el sector para donde fue entrevistado.',
            'subsector.required'  => 'Debe ingresar el subsector / puesto.',
            'formacion.required'  => 'Debe ingresar la formación académica del entrevistado.',
        ]);
    }

    private function fecha($v): string
    {
        return ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
    }

    /** Identificación del archivo en la biblioteca: TDO + NRO(6) + REF(6) + .EXT */
    private function referencia(string $tipo, int $nro, int $ref, string $ext): string
    {
        return $tipo . str_pad((string) $nro, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $ref, 6, '0', STR_PAD_LEFT) . '.' . $ext;
    }
}
