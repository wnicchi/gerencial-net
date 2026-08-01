<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SiniestroController — ART Siniestros: Agregar (art_siniestros_agregar.scx).
 *
 * Alta de siniestros (tabla siniestros, SIN_*). Réplica del botón CONFIRMAR del
 * FoxPro: valida fecha/empleado/detalle y graba el siniestro con nro correlativo
 * (SIN_NRO = max+1). Tipo fijo 'R' (registro ART).
 */
class SiniestroController extends Controller
{
    /** @route GET /api/siniestros/init — próximo número de siniestro. */
    public function init(): JsonResponse
    {
        return response()->json([
            'proximo_nro' => (int) DB::table('siniestros')->max('SIN_NRO') + 1,
        ]);
    }

    /** @route GET /api/siniestros/buscar — lupa: lista de siniestros RRHH (buscar_siniestros.scx). */
    public function buscar(): JsonResponse
    {
        $tipos = ['V' => 'VEHICULO', 'A' => 'AUTOELEVADOR', 'R' => 'R R H H', 'O' => 'OTRO'];
        $rows = DB::table('siniestros')->where('SIN_TIP', 'R')->orderByDesc('SIN_NRO')->get();
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        return response()->json($rows->map(fn ($s) => [
            'nro'      => (int) $s->SIN_NRO,
            'tipo'     => $tipos[trim((string) $s->SIN_TIP)] ?? trim((string) $s->SIN_TIP),
            'fecha'    => $fecha($s->SIN_FEC),
            'interno'  => (int) $s->SIN_VCO + (int) $s->SIN_AUT,
            'dominio'  => trim((string) $s->SIN_DOM),
            'empleado' => (int) $s->SIN_EMP,
            'detalle'  => trim((string) $s->SIN_EMN),
            'cerrado'  => (bool) $s->SIN_CER,
        ])->values());
    }

    /**
     * @route GET /api/siniestros/grilla — lista para el ABM unificado (grilla).
     *
     * Devuelve todos los siniestros RRHH (SIN_TIP='R') con los campos que muestra la grilla.
     * Filtro opcional ?emp= para acotar a un empleado (uso futuro desde la ficha del empleado).
     */
    public function grilla(Request $request): JsonResponse
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $q = DB::table('siniestros')->where('SIN_TIP', 'R');
        if ($emp = (int) $request->input('emp', 0)) {
            $q->where('SIN_EMP', $emp);
        }
        $rows = $q->orderByDesc('SIN_NRO')->get();
        return response()->json($rows->map(fn ($s) => [
            'nro'                  => (int) $s->SIN_NRO,
            'fecha'                => $fecha($s->SIN_FEC),
            'empleado'             => (int) $s->SIN_EMP,
            'empleado_nombre'      => trim((string) $s->SIN_EMN),
            'monto_estimado'       => (float) $s->SIN_MRE,
            'monto_cobrado'        => (float) $s->SIN_MCO,
            'pendiente_resolucion' => (bool) $s->SIN_PAG,
            'cobrado'              => (bool) $s->SIN_COB,
            'pendiente_judicial'   => trim((string) $s->SIN_JUD) === 'S',
            'denuncia_preventiva'  => (bool) $s->SIN_DPR,
            'cerrado'              => (bool) $s->SIN_CER,
            'detalle'              => trim((string) $s->SIN_DET),
        ])->values());
    }

    /** @route GET /api/siniestros/{nro} — consulta de un siniestro (datos + fotos). */
    public function show(int $nro): JsonResponse
    {
        $s = DB::table('siniestros')->where('SIN_NRO', $nro)->first();
        if (!$s) {
            return response()->json(['message' => 'Siniestro no encontrado.'], 404);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';

        // Fotos del siniestro (tabla SINI_FOTOS + binario en biblioteca SINIESTRO_FOTO).
        $svc = new BibliotecaDigitalService();
        $fotos = DB::table('SINI_FOTOS')->where('SIF_NRO', $nro)->orderBy('SIF_UNI')->get()
            ->map(function ($f) use ($svc, $nro) {
                $ref = 'SIN' . str_pad((string) (int) $f->SIF_NRO, 6, '0', STR_PAD_LEFT) . '_' . (int) $f->SIF_UNI . '.JPG';
                return [
                    'uni'         => (int) $f->SIF_UNI,
                    'comentario'  => trim((string) ($f->SIF_COM ?? '')),
                    'foto'        => $svc->archivoDigitalRecuperarDataUrl(config('rrhh.docs_sistema'), 'SINIESTRO_FOTO', $ref),
                ];
            })->values();

        // Documentos del siniestro (tabla sini_doc, DOC_TIP='S').
        $docs = DB::table('sini_doc')->where('DOC_SIN', $nro)->where('DOC_TIP', 'S')->orderBy('DOC_ORD')->get()
            ->map(fn ($d) => [
                'orden'         => (int) $d->DOC_ORD,
                'tipo'          => trim((string) $d->DOC_TDO),
                'detalle_tipo'  => trim((string) $d->DOC_TDD),
                'nombre'        => trim((string) $d->DOC_NOM),
                'ext'           => trim((string) $d->DOC_EXT),
                'creado'        => trim((string) $d->DOC_CRE),
                'observaciones' => trim((string) $d->DOC_OBS),
            ])->values();

        return response()->json([
            'documentos' => $docs,
            'siniestro' => [
                'nro'                   => (int) $s->SIN_NRO,
                'fecha'                 => $fecha($s->SIN_FEC),
                'fecha_alta'            => $fecha($s->SIN_FCA),
                'empleado'              => (int) $s->SIN_EMP,
                'empleado_nombre'       => trim((string) $s->SIN_EMN),
                'monto_estimado'        => (float) $s->SIN_MRE,
                'monto_cobrado'         => (float) $s->SIN_MCO,
                'fecha_cobro'           => $fecha($s->SIN_FCO),
                'banco_cobro'           => trim((string) $s->SIN_BAN),
                'nro_siniestro'         => trim((string) $s->SIN_NSIN),
                'fecha_proximo_control' => $fecha($s->SIN_FPC),
                'detalle'               => trim((string) $s->SIN_DET),
                'dictamen'              => trim((string) $s->SIN_DIC),
                'pendiente_resolucion'  => (bool) $s->SIN_PAG,
                'cobrado'               => (bool) $s->SIN_COB,
                'pendiente_judicial'    => trim((string) $s->SIN_JUD) === 'S',
                'denuncia_preventiva'   => (bool) $s->SIN_DPR,
                'reclamo'               => trim((string) $s->SIN_JTI), // P | T | ''
            ],
            'fotos' => $fotos,
        ]);
    }

    /** @route PUT /api/siniestros/{nro} — modifica un siniestro (réplica del CONFIRMAR del Modificar). */
    public function actualizar(Request $request, int $nro): JsonResponse
    {
        if (!DB::table('siniestros')->where('SIN_NRO', $nro)->exists()) {
            return response()->json(['message' => 'Siniestro no encontrado.'], 404);
        }
        $d = $request->validate([
            'fecha'                 => 'required|date',
            'empleado'              => 'required|integer|min:1',
            'detalle'               => 'required|string|max:2000',
            'fecha_alta'            => 'nullable|date',
            'dictamen'              => 'nullable|string|max:2000',
            'monto_estimado'        => 'nullable|numeric',
            'monto_cobrado'         => 'nullable|numeric',
            'pendiente_resolucion'  => 'boolean',
            'cobrado'               => 'boolean',
            'pendiente_judicial'    => 'boolean',
            'denuncia_preventiva'   => 'boolean',
            'reclamo'               => 'nullable|in:P,T',
            'fecha_cobro'           => 'nullable|date',
            'banco_cobro'           => 'nullable|string|max:100',
            'nro_siniestro'         => 'nullable|string|max:30',
            'fecha_proximo_control' => 'nullable|date',
        ], [
            'fecha.required'    => 'Debe ingresar la fecha.',
            'empleado.required' => 'Debe ingresar el empleado.',
            'empleado.min'      => 'Debe ingresar el empleado.',
            'detalle.required'  => 'Debe ingresar el detalle.',
        ]);

        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }
        $fecha0 = fn ($v) => ($v ?? '') !== '' ? substr((string) $v, 0, 10) : '1900-01-01';

        DB::table('siniestros')->where('SIN_NRO', $nro)->update([
            'SIN_VCO' => 0, 'SIN_DOM' => '',
            'SIN_EMP' => (int) $d['empleado'],
            'SIN_EMN' => mb_substr(trim((string) $per->PER_NOM), 0, 50),
            'SIN_EMD' => 0, 'SIN_EMC' => '',
            'SIN_DET' => mb_substr(trim($d['detalle']), 0, 2000),
            'SIN_DIC' => mb_substr(trim((string) ($d['dictamen'] ?? '')), 0, 2000),
            'SIN_PAG' => !empty($d['pendiente_resolucion']) ? 1 : 0,
            'SIN_COB' => !empty($d['cobrado']) ? 1 : 0,
            'SIN_FEC' => substr((string) $d['fecha'], 0, 10),
            'SIN_HOR' => 0, 'SIN_AUT' => 0,
            'SIN_MAR' => '', 'SIN_MOD' => '', 'SIN_MOT' => '', 'SIN_SER' => '',
            'SIN_ANO' => 0, 'SIN_VOA' => 'R', 'SIN_CER' => 0,
            'SIN_MRE' => (float) ($d['monto_estimado'] ?? 0),
            'SIN_MCO' => (float) ($d['monto_cobrado'] ?? 0),
            'SIN_JUD' => !empty($d['pendiente_judicial']) ? 'S' : 'N',
            'SIN_JTI' => (string) ($d['reclamo'] ?? ''),
            'SIN_DPR' => !empty($d['denuncia_preventiva']) ? 1 : 0,
            'SIN_TIP' => 'R',
            'SIN_FCA' => $fecha0($d['fecha_alta'] ?? ''),
            'SIN_FCO' => $fecha0($d['fecha_cobro'] ?? ''),
            'SIN_BAN' => mb_substr(trim((string) ($d['banco_cobro'] ?? '')), 0, 100),
            'SIN_NSIN' => mb_substr(trim((string) ($d['nro_siniestro'] ?? '')), 0, 30),
            'SIN_FPC' => $fecha0($d['fecha_proximo_control'] ?? ''),
        ]);

        return response()->json(['ok' => true]);
    }

    /** @route POST /api/siniestros/{nro}/reintegro — marca cobrado + monto y prepara el reintegro. */
    public function reintegro(Request $request, int $nro): JsonResponse
    {
        $d = $request->validate(['monto_cobrado' => 'required|numeric|gt:0'],
            ['monto_cobrado.gt' => 'Debe ingresar el monto cobrado.']);
        if (!DB::table('siniestros')->where('SIN_NRO', $nro)->exists()) {
            return response()->json(['message' => 'Siniestro no encontrado.'], 404);
        }
        DB::table('siniestros')->where('SIN_NRO', $nro)
            ->update(['SIN_COB' => 1, 'SIN_MCO' => (float) $d['monto_cobrado']]);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/siniestros/{nro} — elimina el siniestro con sus fotos y documentos. */
    public function eliminar(int $nro): JsonResponse
    {
        if (!DB::table('siniestros')->where('SIN_NRO', $nro)->exists()) {
            return response()->json(['message' => 'Siniestro no encontrado.'], 404);
        }
        $svc = new BibliotecaDigitalService();

        // Borrar los binarios de los documentos (biblioteca proceso SINIESTROS).
        foreach (DB::table('sini_doc')->where('DOC_SIN', $nro)->where('DOC_TIP', 'S')->get() as $d) {
            $ref = $this->refDoc((int) $d->DOC_ORD, (int) $d->DOC_NRO);
            try { $svc->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'SINIESTROS', $ref); }
            catch (\Throwable $e) { Log::warning("[siniestroDelDoc] {$nro}: " . $e->getMessage()); }
        }
        // Borrar los binarios de las fotos (biblioteca proceso SINIESTRO_FOTO).
        foreach (DB::table('SINI_FOTOS')->where('SIF_NRO', $nro)->get() as $f) {
            $ref = 'SIN' . str_pad((string) $nro, 6, '0', STR_PAD_LEFT) . '_' . (int) $f->SIF_UNI . '.JPG';
            try { $svc->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'SINIESTRO_FOTO', $ref); }
            catch (\Throwable $e) { Log::warning("[siniestroDelFoto] {$nro}: " . $e->getMessage()); }
        }

        DB::transaction(function () use ($nro) {
            DB::table('SINI_FOTOS')->where('SIF_NRO', $nro)->delete();
            DB::table('sini_doc')->where('DOC_SIN', $nro)->where('DOC_TIP', 'S')->delete();
            DB::table('siniestros')->where('SIN_NRO', $nro)->delete();
        });

        return response()->json(['ok' => true]);
    }

    /** @route GET /api/siniestros/{nro}/documento/{orden}/ver — visualiza un documento del siniestro. */
    public function verDocumento(int $nro, int $orden)
    {
        $doc = DB::table('sini_doc')->where('DOC_SIN', $nro)->where('DOC_ORD', $orden)->where('DOC_TIP', 'S')->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(
            config('rrhh.docs_sistema'), 'SINIESTROS', $this->refDoc((int) $doc->DOC_ORD, (int) $doc->DOC_NRO));
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** Identificación del documento de siniestro: "S" + Str(DOC_ORD,6) + Str(DOC_NRO,6) (relleno con espacios). */
    private function refDoc(int $ord, int $nro): string
    {
        return 'S' . str_pad((string) $ord, 6, ' ', STR_PAD_LEFT) . str_pad((string) $nro, 6, ' ', STR_PAD_LEFT);
    }

    /** @route POST /api/siniestros — alta del siniestro. */
    public function agregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'fecha'                 => 'required|date',
            'empleado'              => 'required|integer|min:1',
            'detalle'               => 'required|string|max:2000',
            'fecha_alta'            => 'nullable|date',
            'dictamen'              => 'nullable|string|max:2000',
            'monto_estimado'        => 'nullable|numeric',
            'monto_cobrado'         => 'nullable|numeric',
            'pendiente_resolucion'  => 'boolean',
            'cobrado'               => 'boolean',
            'pendiente_judicial'    => 'boolean',
            'denuncia_preventiva'   => 'boolean',
            'reclamo'               => 'nullable|in:P,T',   // P=Propio, T=Terceros
            'fecha_cobro'           => 'nullable|date',
            'banco_cobro'           => 'nullable|string|max:100',
            'nro_siniestro'         => 'nullable|string|max:30',
            'fecha_proximo_control' => 'nullable|date',
        ], [
            'fecha.required'    => 'Debe ingresar la fecha.',
            'empleado.required' => 'Debe ingresar el empleado.',
            'empleado.min'      => 'Debe ingresar el empleado.',
            'detalle.required'  => 'Debe ingresar el detalle.',
        ]);

        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }

        if (!\App\Support\Empleado::activo((int) $d['empleado'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }

        $fecha0 = fn ($v) => ($v ?? '') !== '' ? substr((string) $v, 0, 10) : '1900-01-01';
        $nro = (int) DB::table('siniestros')->max('SIN_NRO') + 1;

        DB::table('siniestros')->insert(Registro::completar('siniestros', [
            'SIN_NRO'  => $nro,
            'SIN_VCO'  => 0,
            'SIN_DOM'  => '',
            'SIN_EMP'  => (int) $d['empleado'],
            'SIN_EMN'  => mb_substr(trim((string) $per->PER_NOM), 0, 50),
            'SIN_EMD'  => 0,
            'SIN_EMC'  => '',
            'SIN_DET'  => mb_substr(trim($d['detalle']), 0, 2000),
            'SIN_DIC'  => mb_substr(trim((string) ($d['dictamen'] ?? '')), 0, 2000),
            'SIN_PAG'  => !empty($d['pendiente_resolucion']) ? 1 : 0,
            'SIN_COB'  => !empty($d['cobrado']) ? 1 : 0,
            'SIN_FEC'  => substr((string) $d['fecha'], 0, 10),
            'SIN_HOR'  => 0,
            'SIN_AUT'  => 0,
            'SIN_MAR'  => '', 'SIN_MOD' => '', 'SIN_MOT' => '', 'SIN_SER' => '',
            'SIN_ANO'  => 0,
            'SIN_VOA'  => 'R',
            'SIN_CER'  => 0,
            'SIN_MRE'  => (float) ($d['monto_estimado'] ?? 0),
            'SIN_OFR'  => 0,
            'SIN_MCO'  => (float) ($d['monto_cobrado'] ?? 0),
            'SIN_JUD'  => !empty($d['pendiente_judicial']) ? 'S' : 'N',
            'SIN_JTI'  => (string) ($d['reclamo'] ?? ''),
            'SIN_DPR'  => !empty($d['denuncia_preventiva']) ? 1 : 0,
            'SIN_TIP'  => 'R',
            'SIN_ALT'  => $fecha0($d['fecha_alta'] ?? ''),
            'SIN_DS'   => 0,
            // Fecha alta se guarda en SIN_FCA (así la lee Consultar y la escribe Modificar).
            'SIN_FCA'  => $fecha0($d['fecha_alta'] ?? ''),
            'SIN_FMO'  => '1900-01-01',
            'SIN_CUL'  => 0,
            'SIN_FCO'  => $fecha0($d['fecha_cobro'] ?? ''),
            'SIN_BAN'  => mb_substr(trim((string) ($d['banco_cobro'] ?? '')), 0, 100),
            'SIN_NSIN' => mb_substr(trim((string) ($d['nro_siniestro'] ?? '')), 0, 30),
            'SIN_FPC'  => $fecha0($d['fecha_proximo_control'] ?? ''),
        ]));

        return response()->json(['ok' => true, 'nro' => $nro], 201);
    }

    // ─────────────────────────── LISTADOS (art_siniestros_listados.scx) ───────────────────────────

    /** @route GET /api/siniestros/listado-rango — fechas mín/máx de los siniestros RRHH (defaults del filtro). */
    public function listadoRango(): JsonResponse
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $min = DB::table('siniestros')->where('SIN_TIP', 'R')->min('SIN_FEC');
        $max = DB::table('siniestros')->where('SIN_TIP', 'R')->max('SIN_FEC');
        return response()->json(['desde' => $fecha($min), 'hasta' => $fecha($max)]);
    }

    /**
     * @route GET /api/siniestros/listado — listado filtrado para el informe.
     *
     * Réplica del CONSULTAR del FoxPro: filtra SIN_TIP='R' por estado exacto de los
     * checkboxes (pendiente/cobrado/cerrado) y rango de fecha. Con con_fotos=1 adjunta
     * las fotos del siniestro y la foto del empleado (informe con fotos).
     */
    public function listado(Request $request): JsonResponse
    {
        $d = $request->validate([
            'desde'     => 'required|date',
            'hasta'     => 'required|date',
            'pendiente' => 'boolean',
            'cobrado'   => 'boolean',
            'cerrado'   => 'boolean',
            'con_fotos' => 'boolean',
        ]);
        $conFotos = !empty($d['con_fotos']);
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $hora  = function ($v) {
            $n = (int) $v;
            return $n > 0 ? sprintf('%02d:%02d', intdiv($n, 100), $n % 100) : '';
        };

        $rows = DB::table('siniestros')
            ->where('SIN_TIP', 'R')
            ->where('SIN_PAG', !empty($d['pendiente']) ? 1 : 0)
            ->where('SIN_COB', !empty($d['cobrado']) ? 1 : 0)
            ->where('SIN_CER', !empty($d['cerrado']) ? 1 : 0)
            ->whereBetween('SIN_FEC', [substr($d['desde'], 0, 10) . ' 00:00:00', substr($d['hasta'], 0, 10) . ' 23:59:59'])
            ->orderBy('SIN_FEC')
            ->get();

        $svc = new BibliotecaDigitalService();
        $siniestros = $rows->map(function ($s) use ($svc, $conFotos, $fecha, $hora) {
            $nro = (int) $s->SIN_NRO;
            $fotos = [];
            $fotoEmp = null;
            if ($conFotos) {
                $fotoEmp = $svc->archivoDigitalRecuperarDataUrl(config('rrhh.docs_sistema'), 'PERSONAL_FOTO', 'E' . (int) $s->SIN_EMP . '.JPG');
                foreach (DB::table('SINI_FOTOS')->where('SIF_NRO', $nro)->orderBy('SIF_UNI')->get() as $f) {
                    $ref = 'SIN' . str_pad((string) $nro, 6, '0', STR_PAD_LEFT) . '_' . (int) $f->SIF_UNI . '.JPG';
                    $fotos[] = [
                        'comentario' => trim((string) ($f->SIF_COM ?? '')),
                        'foto'       => $svc->archivoDigitalRecuperarDataUrl(config('rrhh.docs_sistema'), 'SINIESTRO_FOTO', $ref),
                    ];
                }
            }
            $docs = DB::table('sini_doc')->where('DOC_SIN', $nro)->where('DOC_TIP', 'S')->orderBy('DOC_ORD')->get()
                ->map(fn ($x) => trim((string) $x->DOC_NOM) . '.' . trim((string) $x->DOC_EXT)
                    . (trim((string) $x->DOC_OBS) !== '' ? '  =>  ' . trim((string) $x->DOC_OBS) : ''))
                ->values();

            return [
                'nro'                  => $nro,
                'fecha'                => $fecha($s->SIN_FEC),
                'hora'                 => $hora($s->SIN_HOR),
                'empleado'             => (int) $s->SIN_EMP,
                'empleado_nombre'      => trim((string) $s->SIN_EMN),
                'detalle'              => trim((string) $s->SIN_DET),
                'dictamen'             => trim((string) $s->SIN_DIC),
                'monto_reclamado'      => (float) $s->SIN_MRE,
                'ofrecimiento'         => (float) $s->SIN_OFR,
                'monto_cobrado'        => (float) $s->SIN_MCO,
                'pendiente_resolucion' => (bool) $s->SIN_PAG,
                'cobrado'              => (bool) $s->SIN_COB,
                'pendiente_judicial'   => trim((string) $s->SIN_JUD) === 'S',
                'reclamo'              => trim((string) $s->SIN_JTI),
                'denuncia_preventiva'  => (bool) $s->SIN_DPR,
                'foto_empleado'        => $fotoEmp,
                'fotos'                => $fotos,
                'documentos'           => $docs,
            ];
        })->values();

        return response()->json(['siniestros' => $siniestros]);
    }

    // ─────────────────────────── SEGUIMIENTO / AGENDA (art_siniestros_seguimiento.scx) ───────────────────────────

    /** @route GET /api/siniestros/{nro}/agenda — movimientos de agenda del siniestro. */
    public function agenda(int $nro): JsonResponse
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $rows = DB::table('SINIESTROS_AGENDA')->where('SIN_NUM', $nro)->orderBy('SIN_FEC')->orderBy('SIN_imp')->get()
            ->map(fn ($m) => [
                'imp'     => (int) $m->SIN_imp,
                'fecha'   => $fecha($m->SIN_FEC),
                'detalle' => trim((string) $m->SIN_DET),
            ])->values();
        return response()->json($rows);
    }

    /** @route POST /api/siniestros/{nro}/agenda — agrega un movimiento de agenda (SIN_imp = max+1). */
    public function agendaAgregar(Request $request, int $nro): JsonResponse
    {
        if (!DB::table('siniestros')->where('SIN_NRO', $nro)->exists()) {
            return response()->json(['message' => 'Siniestro no encontrado.'], 404);
        }
        $d = $request->validate([
            'fecha'   => 'required|date',
            'detalle' => 'required|string|max:250',
        ], [
            'fecha.required'   => 'Debe ingresar la fecha.',
            'detalle.required' => 'Debe ingresar el detalle.',
        ]);
        $imp = (int) DB::table('SINIESTROS_AGENDA')->max('SIN_imp') + 1;
        DB::table('SINIESTROS_AGENDA')->insert(Registro::completar('SINIESTROS_AGENDA', [
            'SIN_NUM' => $nro,
            'SIN_TIP' => 'R',
            'SIN_FEC' => substr((string) $d['fecha'], 0, 10),
            'SIN_imp' => $imp,
            'SIN_DET' => mb_substr(trim($d['detalle']), 0, 250),
            'SIN_usu' => mb_substr(strtoupper((string) (auth()->user()->DATO1 ?? 'RRHH.NET')), 0, 20),
            'SIN_FCa' => now()->format('Y-m-d'),
        ]));
        return response()->json(['ok' => true, 'imp' => $imp], 201);
    }

    /** @route PUT /api/siniestros/agenda/{imp} — modifica un movimiento de agenda. */
    public function agendaActualizar(Request $request, int $imp): JsonResponse
    {
        if (!DB::table('SINIESTROS_AGENDA')->where('SIN_imp', $imp)->exists()) {
            return response()->json(['message' => 'Movimiento no encontrado.'], 404);
        }
        $d = $request->validate([
            'fecha'   => 'required|date',
            'detalle' => 'required|string|max:250',
        ], [
            'fecha.required'   => 'Debe ingresar la fecha.',
            'detalle.required' => 'Debe ingresar el detalle.',
        ]);
        DB::table('SINIESTROS_AGENDA')->where('SIN_imp', $imp)->update([
            'SIN_FEC' => substr((string) $d['fecha'], 0, 10),
            'SIN_DET' => mb_substr(trim($d['detalle']), 0, 250),
        ]);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/siniestros/agenda/{imp} — borra un movimiento de agenda. */
    public function agendaEliminar(int $imp): JsonResponse
    {
        if (!DB::table('SINIESTROS_AGENDA')->where('SIN_imp', $imp)->exists()) {
            return response()->json(['message' => 'Movimiento no encontrado.'], 404);
        }
        DB::table('SINIESTROS_AGENDA')->where('SIN_imp', $imp)->delete();
        return response()->json(['ok' => true]);
    }
}
