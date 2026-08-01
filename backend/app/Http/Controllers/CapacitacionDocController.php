<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CapacitacionDocController — Documentación asociada a una capacitación
 * (capacitacion_documentacion.scx).
 *
 * Sube un archivo a la biblioteca digital (proceso CAPACITACION), lo registra en
 * cap_documentacion (DOC_TIP='K', DOC_REF=código de la jornada) y lo vincula a
 * los empleados (cursantes) seleccionados mediante cap_empdoc.
 */
class CapacitacionDocController extends Controller
{
    private const EXT_BLOQUEADAS = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'];

    /** @route GET /api/cap-doc/init — tipos de documento. */
    public function init(): JsonResponse
    {
        return response()->json([
            'tipos' => DB::table('tipo_doc')->orderBy('TDO_DES')->get(['TDO_COD', 'TDO_DES'])
                ->map(fn ($t) => ['cod' => trim((string) $t->TDO_COD), 'nombre' => trim((string) $t->TDO_DES)])->values(),
        ]);
    }

    /** @route GET /api/cap-doc/capacitacion/{cod} — cabecera + cursantes + documentos. */
    public function capacitacion(int $cod): JsonResponse
    {
        $c = DB::table('capacitacion')->where('CAP_COD', $cod)->first();
        if (!$c) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';

        $alumnos = DB::table('cap_emp as ce')->join('personal as p', 'ce.PER_COD', '=', 'p.PER_COD')
            ->where('ce.CAP_COD', $cod)->orderBy('p.PER_NOM')->orderBy('p.PER_COD')
            ->get(['p.PER_COD', 'p.PER_NOM', 'p.PER_CUI', 'p.PER_AOP'])
            ->map(fn ($r) => [
                'codigo' => (int) $r->PER_COD, 'nombre' => trim((string) $r->PER_NOM),
                'cuil' => trim((string) $r->PER_CUI), 'activo' => trim((string) $r->PER_AOP) === 'A',
            ])->values();

        return response()->json([
            'cabecera' => [
                'cod' => (int) $c->CAP_COD, 'capacitacion' => trim((string) $c->CAP_CAPA),
                'fecha' => $fecha($c->CAP_FEC), 'tematica' => trim((string) $c->CAP_TEM_DET),
            ],
            'alumnos'    => $alumnos,
            'documentos' => $this->documentos($cod),
        ]);
    }

    /** @route POST /api/cap-doc/{cod} — sube el documento y lo vincula a los cursantes. */
    public function subir(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'tipo'          => 'required|string|max:2',
            'fecha'         => 'required|date',
            'observaciones' => 'nullable|string|max:60',
            'archivo'       => 'required|file|max:51200',
            'empleados'     => 'required|array|min:1',
            'empleados.*'   => 'integer',
        ], ['empleados.min' => 'Seleccione al menos un empleado para asociar el documento.']);

        if (!DB::table('capacitacion')->where('CAP_COD', $cod)->exists()) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }

        $tipo = mb_strtoupper(trim($d['tipo']));
        $file = $request->file('archivo');
        $ext  = mb_strtoupper((string) $file->getClientOriginalExtension());
        if (in_array($ext, self::EXT_BLOQUEADAS, true) || $ext === '') {
            return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
        }
        $tdd    = trim((string) DB::table('tipo_doc')->where('TDO_COD', $tipo)->value('TDO_DES'));
        $nombre = mb_strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $tam    = (int) $file->getSize();
        $ahora  = now();
        $usuario  = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        $terminal = mb_substr(gethostname() ?: 'RRHH.NET', 0, 20);

        // DOC_ORD global, DOC_NRO por tipo (lógica FoxPro sobre cap_documentacion).
        $docOrd = (int) DB::table('cap_documentacion')->max('DOC_ORD') + 1;
        $docNro = (int) DB::table('cap_documentacion')->where('DOC_TDO', $tipo)->max('DOC_NRO') + 1;

        $unico = DB::table('cap_documentacion')->insertGetId([
            'DOC_ORD' => $docOrd, 'DOC_TDO' => $tipo, 'DOC_NRO' => $docNro, 'DOC_TIP' => 'K',
            'DOC_REF' => $cod, 'DOC_DET' => mb_substr('Capacitación Nro.' . $cod, 0, 60),
            'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES', 'DOC_TDD' => mb_substr($tdd, 0, 30),
            'DOC_FUL' => mb_substr((string) $file->getClientOriginalName(), 0, 120), 'DOC_DIR' => '',
            'DOC_NOM' => mb_substr($nombre, 0, 120), 'DOC_EXT' => mb_substr($ext, 0, 5),
            'DOC_CRE' => $ahora->format('d/m/Y H:i'), 'DOC_TAM' => $tam, 'DOC_KB' => (int) round($tam / 1024),
            'DOC_OBS' => mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 60),
            'DOC_USU' => $usuario, 'DOC_TER' => $terminal, 'DOC_GRA' => $ahora->format('d/m/y H:i'),
            'DOC_FEC' => substr((string) $d['fecha'], 0, 10),
        ], 'UNICO');

        // Guardar el archivo físico en la biblioteca digital (proceso CAPACITACION).
        $referencia = $this->referencia($tipo, $docNro, $cod, $ext);
        try {
            (new BibliotecaDigitalService())->archivoDigitalGuardar(
                config('rrhh.docs_sistema'), 'CAPACITACION', $referencia, $ext,
                file_get_contents($file->getRealPath()), $usuario
            );
        } catch (\Throwable $e) {
            DB::table('cap_documentacion')->where('UNICO', $unico)->delete();
            Log::error("[capDocSubir] cap {$cod}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }

        // Vincular a los empleados seleccionados (sin duplicar).
        foreach (array_unique(array_map('intval', $d['empleados'])) as $emp) {
            DB::table('cap_empdoc')->where('cap_nro', $cod)->where('cap_emp', $emp)->where('cap_doc', $unico)->delete();
            DB::table('cap_empdoc')->insert(['cap_nro' => $cod, 'cap_emp' => $emp, 'cap_doc' => $unico]);
        }

        return response()->json(['ok' => true, 'unico' => $unico, 'documentos' => $this->documentos($cod)]);
    }

    /** @route GET /api/cap-doc/documento/{unico}/ver — visualiza/descarga el archivo. */
    public function ver(int $unico)
    {
        $doc = DB::table('cap_documentacion')->where('UNICO', $unico)->where('DOC_TIP', 'K')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $ref  = $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, trim((string) $doc->DOC_EXT));
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'CAPACITACION', $ref);
        if ($resp === null) {
            return response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
        }
        return $resp;
    }

    /** @route DELETE /api/cap-doc/documento/{unico} — elimina el documento y sus vínculos. */
    public function eliminar(int $unico): JsonResponse
    {
        $doc = DB::table('cap_documentacion')->where('UNICO', $unico)->where('DOC_TIP', 'K')->first();
        if (!$doc) {
            return response()->json(['message' => 'Documento no encontrado.'], 404);
        }
        $ref = $this->referencia(trim((string) $doc->DOC_TDO), (int) $doc->DOC_NRO, (int) $doc->DOC_REF, trim((string) $doc->DOC_EXT));
        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'CAPACITACION', $ref); }
        catch (\Throwable $e) { Log::warning("[capDocEliminar] {$unico}: " . $e->getMessage()); }
        DB::table('cap_empdoc')->where('cap_doc', $unico)->delete();
        DB::table('cap_documentacion')->where('UNICO', $unico)->delete();
        return response()->json(['ok' => true, 'documentos' => $this->documentos((int) $doc->DOC_REF)]);
    }

    /** Documentos asociados a la capacitación (con cantidad de empleados vinculados). */
    private function documentos(int $cod): array
    {
        return DB::table('cap_documentacion')->where('DOC_REF', $cod)->where('DOC_TIP', 'K')
            ->orderByDesc('DOC_ORD')->get()
            ->map(fn ($r) => [
                'unico'         => (int) $r->UNICO,
                'tipo'          => trim((string) $r->DOC_TDO),
                'detalle_tipo'  => trim((string) $r->DOC_TDD),
                'nombre'        => trim((string) $r->DOC_NOM),
                'ext'           => trim((string) $r->DOC_EXT),
                'fecha'         => $r->DOC_FEC ? substr((string) $r->DOC_FEC, 0, 10) : '',
                'creado'        => trim((string) $r->DOC_CRE),
                'observaciones' => trim((string) $r->DOC_OBS),
                'empleados'     => (int) DB::table('cap_empdoc')->where('cap_doc', (int) $r->UNICO)->count(),
            ])->values()->all();
    }

    /** Identificación del archivo en la biblioteca: TDO + NRO(6) + REF(6) + .EXT */
    private function referencia(string $tipo, int $nro, int $ref, string $ext): string
    {
        return $tipo . str_pad((string) $nro, 6, '0', STR_PAD_LEFT)
            . str_pad((string) $ref, 6, '0', STR_PAD_LEFT) . '.' . $ext;
    }
}
