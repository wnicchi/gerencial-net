<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExamenController — Control de Salud · Exámenes Generales · Agregar
 * (examenes_agregar.scx).
 *
 * Registra un examen médico (tabla examenes) de un empleado, con médico
 * responsable, tipo de examen, fechas y notas. Opcionalmente adjunta un
 * documento a la biblioteca digital (documentos, DOC_TIP='X', DOC_REF=examen).
 *
 * examenes: EXA_EMP, EXA_EMD, EXA_TIP, EXA_TID, EXA_FEC, EXA_VEN, EXA_MED,
 *           EXA_MDD, EXA_NOT, EXA_COE ('E' examen / 'C' certificado), UNICO.
 */
class ExamenController extends Controller
{
    private const EXT_BLOQUEADAS = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'];

    /** @route GET /api/examenes/init — combos (médicos, tipos de examen, tipos de documento). */
    public function init(): JsonResponse
    {
        return response()->json([
            'medicos' => DB::table('medicos')->orderBy('MED_NOM')->get(['MED_COD', 'MED_NOM'])
                ->map(fn ($m) => ['cod' => (int) $m->MED_COD, 'nombre' => trim((string) $m->MED_NOM)])->values(),
            'tipos_examen' => DB::table('examtipo')->orderBy('EXT_DET')->get(['EXT_COD', 'EXT_DET'])
                ->map(fn ($e) => ['cod' => (int) $e->EXT_COD, 'nombre' => trim((string) $e->EXT_DET)])->values(),
            'tipos_doc' => DB::table('tipo_doc')->where('TDO_TIP', 'X')->orderBy('TDO_DES')->get(['TDO_COD', 'TDO_DES'])
                ->map(fn ($t) => ['cod' => trim((string) $t->TDO_COD), 'nombre' => trim((string) $t->TDO_DES)])->values(),
        ]);
    }

    /** @route GET /api/examenes/empleado/{cod} — datos del empleado para la cabecera. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_AOP']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        return response()->json(['nombre' => trim((string) $p->PER_NOM), 'activo' => trim((string) $p->PER_AOP) === 'A']);
    }

    /** @route GET /api/examenes/listados?empleado=&periodo=&desde=&hasta=&medico=&tipo= — informe médico. */
    public function listados(Request $request): JsonResponse
    {
        $emp   = (int) $request->query('empleado', 0);
        $med   = (int) $request->query('medico', 0);
        $tipo  = (int) $request->query('tipo', 0);
        $rango = $request->query('periodo', 'hist') === 'rango';

        $q = DB::table('examenes as a')->join('personal as p', 'a.EXA_EMP', '=', 'p.PER_COD')->where('p.PER_AOP', 'A');
        if ($emp > 0)  $q->where('a.EXA_EMP', $emp);
        if ($med > 0)  $q->where('a.EXA_MED', $med);
        if ($tipo > 0) $q->where('a.EXA_TIP', $tipo);
        if ($rango) {
            $desde = substr((string) $request->query('desde', '1900-01-01'), 0, 10);
            $hasta = substr((string) $request->query('hasta', '2999-12-31'), 0, 10);
            $q->whereBetween('a.EXA_FEC', [$desde . ' 00:00:00', $hasta . ' 23:59:59']);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $items = $q->orderBy('p.PER_NOM')->orderByDesc('a.EXA_FEC')
            ->get(['a.EXA_EMP', 'a.EXA_EMD', 'a.EXA_TID', 'a.EXA_FEC', 'a.EXA_VEN', 'a.EXA_MDD', 'a.EXA_NOT', 'a.EXA_COE'])
            ->map(fn ($e) => [
                'emp_cod'    => (int) $e->EXA_EMP,
                'emp_nombre' => trim((string) $e->EXA_EMD),
                'examen'     => trim((string) $e->EXA_TID),
                'fecha'      => $fecha($e->EXA_FEC),
                'proximo'    => $fecha($e->EXA_VEN),
                'medico'     => trim((string) $e->EXA_MDD),
                'notas'      => trim((string) $e->EXA_NOT),
                'coe'        => trim((string) $e->EXA_COE) ?: 'E',
            ])->values();
        return response()->json(['items' => $items, 'total' => $items->count()]);
    }

    /**
     * @route GET /api/examenes/grilla?emp= — todos los exámenes para el ABM unificado.
     * emp opcional: acota a un empleado (uso futuro desde la ficha del empleado).
     */
    public function grilla(Request $request): JsonResponse
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $q = DB::table('examenes');
        if ($emp = (int) $request->input('emp', 0)) {
            $q->where('EXA_EMP', $emp);
        }
        $rows = $q->orderByDesc('EXA_FEC')->get()->map(fn ($e) => [
            'unico'           => (int) $e->UNICO,
            'empleado'        => (int) $e->EXA_EMP,
            'empleado_nombre' => trim((string) $e->EXA_EMD),
            'tipo_cod'        => (int) $e->EXA_TIP,
            'tipo_det'        => trim((string) $e->EXA_TID),
            'fecha'           => $fecha($e->EXA_FEC),
            'proximo'         => $fecha($e->EXA_VEN),
            'medico_cod'      => (int) $e->EXA_MED,
            'medico'          => trim((string) $e->EXA_MDD),
            'notas'           => trim((string) $e->EXA_NOT),
            'coe'             => trim((string) $e->EXA_COE) ?: 'E',
        ])->values();
        return response()->json($rows);
    }

    /** @route GET /api/examenes/proximos?empleado=&periodo=&desde=&hasta=&medico= — próximos vencimientos. */
    public function proximos(Request $request): JsonResponse
    {
        $emp   = (int) $request->query('empleado', 0);
        $med   = (int) $request->query('medico', 0);
        $rango = $request->query('periodo', 'general') === 'rango';

        $q = DB::table('examenes as a')->join('personal as p', 'a.EXA_EMP', '=', 'p.PER_COD')
            ->where('p.PER_AOP', 'A')->where('a.EXA_VEN', '>', now());
        if ($emp > 0) $q->where('a.EXA_EMP', $emp);
        if ($med > 0) $q->where('a.EXA_MED', $med);
        if ($rango) {
            $desde = substr((string) $request->query('desde', '1900-01-01'), 0, 10);
            $hasta = substr((string) $request->query('hasta', '2999-12-31'), 0, 10);
            $q->whereBetween('a.EXA_VEN', [$desde . ' 00:00:00', $hasta . ' 23:59:59']);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $items = $q->orderBy('a.EXA_VEN')
            ->get(['a.EXA_EMP', 'a.EXA_EMD', 'a.EXA_TID', 'a.EXA_FEC', 'a.EXA_VEN', 'a.EXA_MDD'])
            ->map(fn ($e) => [
                'emp_cod'    => (int) $e->EXA_EMP,
                'emp_nombre' => trim((string) $e->EXA_EMD),
                'examen'     => trim((string) $e->EXA_TID),
                'fecha'      => $fecha($e->EXA_FEC),
                'proximo'    => $fecha($e->EXA_VEN),
                'medico'     => trim((string) $e->EXA_MDD),
            ])->values();
        return response()->json(['items' => $items, 'total' => $items->count()]);
    }

    /** @route POST /api/examenes/eliminar — elimina los exámenes marcados. */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['unicos' => 'required|array|min:1', 'unicos.*' => 'integer']);
        $n = DB::table('examenes')->whereIn('UNICO', array_map('intval', $d['unicos']))->delete();
        return response()->json(['ok' => true, 'eliminados' => $n]);
    }

    /** @route GET /api/examenes/empleado/{cod}/lista — exámenes del empleado (para Modificar). */
    public function examenesEmpleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $items = DB::table('examenes')->where('EXA_EMP', $cod)->orderByDesc('EXA_FEC')->get()
            ->map(fn ($e) => [
                'unico'      => (int) $e->UNICO,
                'tipo_cod'   => (int) $e->EXA_TIP,
                'tipo_det'   => trim((string) $e->EXA_TID),
                'fecha'      => $fecha($e->EXA_FEC),
                'proximo'    => $fecha($e->EXA_VEN),
                'medico_cod' => (int) $e->EXA_MED,
                'medico'     => trim((string) $e->EXA_MDD),
                'notas'      => trim((string) $e->EXA_NOT),
                'coe'        => trim((string) $e->EXA_COE) ?: 'E',
                'enf_cod'    => trim((string) ($e->EXA_ENF ?? '')),
                'enf_des'    => trim((string) ($e->EXA_END ?? '')),
            ])->values();
        return response()->json(['nombre' => trim((string) $p->PER_NOM), 'examenes' => $items]);
    }

    /** @route PUT /api/examenes/{unico} — actualiza tipo/fechas/notas/coe del examen. */
    public function actualizar(Request $request, int $unico): JsonResponse
    {
        $d = $request->validate([
            'tipo_documento'  => 'required|in:E,C',
            'tipo_examen_cod' => 'required|integer|min:1',
            'fecha_examen'    => 'required|date',
            'fecha_proximo'   => 'nullable|date',
            'notas'           => 'nullable|string|max:2000',
        ], ['tipo_examen_cod.min' => 'Debe identificar el tipo de examen médico.']);
        if (!DB::table('examenes')->where('UNICO', $unico)->exists()) {
            return response()->json(['message' => 'Examen inexistente.'], 404);
        }
        if ($d['tipo_documento'] === 'E' && empty($d['fecha_proximo'])) {
            return response()->json(['message' => 'Ingrese la fecha del próximo examen médico.'], 422);
        }
        $ext = DB::table('examtipo')->where('EXT_COD', $d['tipo_examen_cod'])->first(['EXT_COD', 'EXT_DET']);
        if (!$ext) {
            return response()->json(['message' => 'Tipo de examen no válido.'], 422);
        }
        DB::table('examenes')->where('UNICO', $unico)->update([
            'EXA_TIP' => (int) $ext->EXT_COD,
            'EXA_TID' => mb_substr(trim((string) $ext->EXT_DET), 0, 50),
            'EXA_FEC' => substr((string) $d['fecha_examen'], 0, 10),
            'EXA_VEN' => !empty($d['fecha_proximo']) ? substr((string) $d['fecha_proximo'], 0, 10) : '1900-01-01',
            'EXA_NOT' => mb_substr(trim((string) ($d['notas'] ?? '')), 0, 2000),
            'EXA_COE' => $d['tipo_documento'],
        ]);
        return response()->json(['ok' => true]);
    }

    /** @route GET /api/examenes/{unico}/documentos — documentos del examen. */
    public function documentos(int $unico): JsonResponse
    {
        return response()->json($this->docsExamen($unico));
    }

    /** @route POST /api/examenes/{unico}/documento — adjunta un documento al examen. */
    public function agregarDocumento(Request $request, int $unico): JsonResponse
    {
        $d = $request->validate([
            'doc_tipo'  => 'required|string|max:2',
            'doc_fecha' => 'required|date',
            'doc_obs'   => 'nullable|string|max:60',
            'archivo'   => 'required|file|max:51200',
        ]);
        $exa = DB::table('examenes')->where('UNICO', $unico)->first(['EXA_EMP', 'EXA_TIP', 'EXA_FEC']);
        if (!$exa) {
            return response()->json(['message' => 'Examen inexistente.'], 404);
        }
        $file = $request->file('archivo');
        $ext  = mb_strtoupper((string) $file->getClientOriginalExtension());
        if ($ext === '' || in_array($ext, self::EXT_BLOQUEADAS, true)) {
            return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
        }
        try {
            $this->guardarDocumento($file, $unico, mb_strtoupper(trim($d['doc_tipo'])), $d['doc_fecha'],
                (string) ($d['doc_obs'] ?? ''), (int) $exa->EXA_EMP, (int) $exa->EXA_TIP, substr((string) $exa->EXA_FEC, 0, 10), $request);
        } catch (\Throwable $e) {
            Log::error("[examenAddDoc] examen {$unico}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }
        return response()->json(['ok' => true, 'documentos' => $this->docsExamen($unico)]);
    }

    /** @route GET /api/examenes/documento/{id}/ver — visualiza un documento de examen. */
    public function verDocumento(int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'X')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->identDoc($doc));
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** @route DELETE /api/examenes/documento/{id} — elimina un documento de examen. */
    public function eliminarDocumento(int $id): JsonResponse
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'X')->first();
        if (!$doc) {
            return response()->json(['message' => 'Documento no encontrado.'], 404);
        }
        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'DOCUMENTACION', $this->identDoc($doc)); }
        catch (\Throwable $e) { Log::warning("[examenDelDoc] {$id}: " . $e->getMessage()); }
        DB::table('documentos')->where('UNICO', $id)->delete();
        return response()->json(['ok' => true, 'documentos' => $this->docsExamen((int) $doc->DOC_REF)]);
    }

    /** Documentos de un examen (DOC_TIP='X', DOC_REF=examen). */
    private function docsExamen(int $unico): array
    {
        return DB::table('documentos')->where('DOC_REF', $unico)->where('DOC_TIP', 'X')->orderByDesc('DOC_FEC')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->UNICO, 'nro' => (int) $r->DOC_ORD, 'tipo' => trim((string) $r->DOC_TDO),
                'detalle' => trim((string) $r->DOC_TDD), 'nombre' => trim((string) $r->DOC_NOM),
                'ext' => trim((string) $r->DOC_EXT), 'fecha' => $r->DOC_FEC ? substr((string) $r->DOC_FEC, 0, 10) : '',
                'creado' => trim((string) $r->DOC_CRE), 'observaciones' => trim((string) $r->DOC_OBS),
            ])->values()->all();
    }

    /** Identificación del archivo en la biblioteca: "M"+DOC_TDO+pad(DOC_REF,10)+"."+EXT */
    private function identDoc(object $doc): string
    {
        return 'M' . trim((string) $doc->DOC_TDO)
            . str_pad((string) (int) $doc->DOC_REF, 10, '0', STR_PAD_LEFT) . '.' . mb_strtoupper(trim((string) $doc->DOC_EXT));
    }

    /** @route POST /api/examenes — registra el examen (+ documento opcional). */
    public function agregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado'        => 'required|integer',
            'tipo_documento'  => 'required|in:E,C',          // E=examen, C=certificado
            'medico_cod'      => 'required|integer|min:1',
            'tipo_examen_cod' => 'required|integer|min:1',
            'fecha_examen'    => 'required|date',
            'fecha_proximo'   => 'nullable|date',
            'notas'           => 'nullable|string|max:2000',
            // Documento opcional
            'doc_tipo'        => 'nullable|string|max:2',
            'doc_fecha'       => 'nullable|date',
            'doc_obs'         => 'nullable|string|max:60',
            'archivo'         => 'nullable|file|max:51200',
        ], [
            'medico_cod.min'      => 'Debe identificar el médico responsable de este análisis.',
            'tipo_examen_cod.min' => 'Debe identificar el tipo de examen médico.',
        ]);

        // Si es "EXAMENES" (E) la fecha del próximo es obligatoria.
        if ($d['tipo_documento'] === 'E' && empty($d['fecha_proximo'])) {
            return response()->json(['message' => 'Ingrese la fecha del próximo examen médico.'], 422);
        }

        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }
        if (!\App\Support\Empleado::activo((int) $d['empleado'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }
        $med = DB::table('medicos')->where('MED_COD', $d['medico_cod'])->first(['MED_COD', 'MED_NOM']);
        $ext = DB::table('examtipo')->where('EXT_COD', $d['tipo_examen_cod'])->first(['EXT_COD', 'EXT_DET']);
        if (!$med || !$ext) {
            return response()->json(['message' => 'Médico o tipo de examen no válido.'], 422);
        }

        // ¿Se adjunta documento? (requiere tipo + archivo)
        $incluyeDoc = !empty($d['doc_tipo']) && $request->hasFile('archivo');
        if ($incluyeDoc && empty($d['doc_fecha'])) {
            return response()->json(['message' => 'Debe ingresar la fecha del documento.'], 422);
        }
        $file = null;
        if ($incluyeDoc) {
            $file = $request->file('archivo');
            $extArch = mb_strtoupper((string) $file->getClientOriginalExtension());
            if (in_array($extArch, self::EXT_BLOQUEADAS, true) || $extArch === '') {
                return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
            }
        }

        $unico = DB::table('examenes')->insertGetId(Registro::completar('examenes', [
            'EXA_EMP' => (int) $d['empleado'],
            'EXA_EMD' => mb_substr(trim((string) $per->PER_NOM), 0, 50),
            'EXA_TIP' => (int) $ext->EXT_COD,
            'EXA_TID' => mb_substr(trim((string) $ext->EXT_DET), 0, 50),
            'EXA_FEC' => substr((string) $d['fecha_examen'], 0, 10),
            'EXA_VEN' => !empty($d['fecha_proximo']) ? substr((string) $d['fecha_proximo'], 0, 10) : '1900-01-01',
            'EXA_MED' => (int) $med->MED_COD,
            'EXA_MDD' => mb_substr(trim((string) $med->MED_NOM), 0, 30),
            'EXA_NOT' => mb_substr(trim((string) ($d['notas'] ?? '')), 0, 2000),
            'EXA_COE' => $d['tipo_documento'],
        ]), 'UNICO');

        if ($incluyeDoc && $file) {
            try {
                $this->guardarDocumento($file, (int) $unico, mb_strtoupper(trim($d['doc_tipo'])), $d['doc_fecha'], (string) ($d['doc_obs'] ?? ''), (int) $d['empleado'], (int) $ext->EXT_COD, substr((string) $d['fecha_examen'], 0, 10), $request);
            } catch (\Throwable $e) {
                Log::error("[examenAgregar] doc examen {$unico}: " . $e->getMessage());
                // El examen ya quedó grabado; informamos que el documento falló.
                return response()->json(['ok' => true, 'unico' => $unico, 'doc_error' => 'El examen se guardó, pero no se pudo adjuntar el documento.'], 201);
            }
        }

        return response()->json(['ok' => true, 'unico' => $unico], 201);
    }

    /** @route GET /api/enfermedades?buscar= — buscador CIE10 (tabla enfermedades). */
    public function enfermedades(Request $request): JsonResponse
    {
        $b = trim((string) $request->query('buscar', ''));
        if (mb_strlen($b) < 1) {
            return response()->json([]);
        }
        return response()->json(
            DB::table('enfermedades')
                ->where(function ($w) use ($b) {
                    $w->where('ENF_DES', 'like', "%{$b}%")->orWhere('ENF_COD', 'like', "%{$b}%");
                })
                ->orderBy('ENF_DES')->limit(60)->get(['ENF_COD', 'ENF_DES', 'UNICO'])
                ->map(fn ($e) => [
                    'cod'    => trim((string) $e->ENF_COD),
                    'nombre' => trim((string) $e->ENF_DES),
                    'unico'  => (int) $e->UNICO,
                ])->values()
        );
    }

    /** @route POST /api/examenes-medicos — examen "exclusivo" con enfermedad CIE10 y varios documentos. */
    public function agregarMedico(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado'        => 'required|integer',
            'tipo_documento'  => 'required|in:E,C',
            'medico_cod'      => 'required|integer|min:1',
            'tipo_examen_cod' => 'required|integer|min:1',
            'fecha_examen'    => 'required|date',
            'fecha_proximo'   => 'nullable|date',
            'notas'           => 'nullable|string|max:2000',
            'enf_cod'         => 'nullable|string|max:10',
            'enf_des'         => 'nullable|string|max:100',
            'enf_uni'         => 'nullable|integer',
            'documentos'             => 'nullable|array',
            'documentos.*.tipo'      => 'required_with:documentos|string|max:2',
            'documentos.*.fecha'     => 'required_with:documentos|date',
            'documentos.*.obs'       => 'nullable|string|max:60',
            'documentos.*.archivo'   => 'required_with:documentos|file|max:51200',
        ], [
            'medico_cod.min'      => 'Debe identificar el médico responsable de este análisis.',
            'tipo_examen_cod.min' => 'Debe identificar el tipo de examen médico.',
        ]);
        if ($d['tipo_documento'] === 'E' && empty($d['fecha_proximo'])) {
            return response()->json(['message' => 'Ingrese la fecha del próximo examen médico.'], 422);
        }
        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }
        if (!\App\Support\Empleado::activo((int) $d['empleado'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }
        $med = DB::table('medicos')->where('MED_COD', $d['medico_cod'])->first(['MED_COD', 'MED_NOM']);
        $ext = DB::table('examtipo')->where('EXT_COD', $d['tipo_examen_cod'])->first(['EXT_COD', 'EXT_DET']);
        if (!$med || !$ext) {
            return response()->json(['message' => 'Médico o tipo de examen no válido.'], 422);
        }
        // Validar extensiones de los archivos antes de grabar el examen.
        foreach ($request->file('documentos', []) as $i => $doc) {
            $extA = mb_strtoupper((string) ($doc['archivo'] ?? null)?->getClientOriginalExtension());
            if ($extA === '' || in_array($extA, self::EXT_BLOQUEADAS, true)) {
                return response()->json(['message' => "Archivo de la fila " . ($i + 1) . " con extensión no válida."], 422);
            }
        }

        $unico = DB::table('examenes')->insertGetId(Registro::completar('examenes', [
            'EXA_EMP' => (int) $d['empleado'],
            'EXA_EMD' => mb_substr(trim((string) $per->PER_NOM), 0, 50),
            'EXA_TIP' => (int) $ext->EXT_COD,
            'EXA_TID' => mb_substr(trim((string) $ext->EXT_DET), 0, 50),
            'EXA_FEC' => substr((string) $d['fecha_examen'], 0, 10),
            'EXA_VEN' => !empty($d['fecha_proximo']) ? substr((string) $d['fecha_proximo'], 0, 10) : '1900-01-01',
            'EXA_MED' => (int) $med->MED_COD,
            'EXA_MDD' => mb_substr(trim((string) $med->MED_NOM), 0, 30),
            'EXA_NOT' => mb_substr(trim((string) ($d['notas'] ?? '')), 0, 2000),
            'EXA_COE' => $d['tipo_documento'],
            'EXA_ENF' => mb_strtoupper(mb_substr(trim((string) ($d['enf_cod'] ?? '')), 0, 10)),
            'EXA_END' => mb_strtoupper(mb_substr(trim((string) ($d['enf_des'] ?? '')), 0, 100)),
            'EXA_ENU' => (int) ($d['enf_uni'] ?? 0),
        ]), 'UNICO');

        $errores = 0;
        $docsMeta = $d['documentos'] ?? [];
        foreach ($request->file('documentos', []) as $i => $doc) {
            $meta = $docsMeta[$i] ?? [];
            try {
                $this->guardarDocumento($doc['archivo'], (int) $unico, mb_strtoupper(trim((string) ($meta['tipo'] ?? ''))),
                    (string) ($meta['fecha'] ?? $d['fecha_examen']), (string) ($meta['obs'] ?? ''),
                    (int) $d['empleado'], (int) $ext->EXT_COD, substr((string) $d['fecha_examen'], 0, 10), $request);
            } catch (\Throwable $e) {
                Log::error("[examenMedico] doc {$i} examen {$unico}: " . $e->getMessage());
                $errores++;
            }
        }

        return response()->json([
            'ok' => true, 'unico' => $unico,
            'doc_error' => $errores > 0 ? "El examen se guardó, pero {$errores} documento(s) no se pudieron adjuntar." : null,
        ], 201);
    }

    /** Inserta la metadata en documentos (DOC_TIP='X') y guarda el archivo físico. */
    private function guardarDocumento($file, int $examenUnico, string $tipo, string $fecha, string $obs, int $emp, int $tipExa, string $fecExa, Request $request): void
    {
        $extArch = mb_strtoupper((string) $file->getClientOriginalExtension());
        $tdd     = trim((string) DB::table('tipo_doc')->where('TDO_COD', $tipo)->value('TDO_DES'));
        $nombre  = mb_strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $tam     = (int) $file->getSize();
        $ahora   = now();
        $usuario  = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        $terminal = mb_substr(gethostname() ?: 'RRHH.NET', 0, 20);

        $docOrd = (int) DB::table('documentos')->max('DOC_ORD') + 1;
        $docNro = (int) DB::table('documentos')->where('DOC_TDO', $tipo)->max('DOC_NRO') + 1;

        DB::table('documentos')->insert([
            'DOC_ORD' => $docOrd, 'DOC_TDO' => $tipo, 'DOC_NRO' => $docNro, 'DOC_FEC' => substr($fecha, 0, 10),
            'DOC_TDD' => mb_substr($tdd, 0, 30), 'DOC_TIP' => 'X', 'DOC_REF' => $examenUnico,
            'DOC_DET' => mb_substr("Examen Médico: Emp.{$emp} Tip.{$tipExa} {$fecExa}", 0, 60),
            'DOC_FUL' => mb_substr((string) $file->getClientOriginalName(), 0, 120), 'DOC_DIR' => '',
            'DOC_NOM' => mb_substr($nombre, 0, 120), 'DOC_CRE' => $ahora->format('d/m/Y H:i'),
            'DOC_TAM' => $tam, 'DOC_KB' => (int) round($tam / 1024), 'DOC_EXT' => mb_substr($extArch, 0, 5),
            'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES', 'DOC_OBS' => mb_substr(trim($obs), 0, 60),
            'DOC_TER' => $terminal, 'DOC_USU' => $usuario, 'DOC_GRA' => $ahora->format('d/m/y H:i'),
        ]);

        // Referencia FoxPro: "M" + TDO + RIGHT("00000"+UNICO_EXAMEN,10) + "." + EXT
        $referencia = 'M' . $tipo . str_pad((string) $examenUnico, 10, '0', STR_PAD_LEFT) . '.' . $extArch;
        (new BibliotecaDigitalService())->archivoDigitalGuardar(
            config('rrhh.docs_sistema'), 'DOCUMENTACION', $referencia, $extArch,
            file_get_contents($file->getRealPath()), $usuario
        );
    }
}
