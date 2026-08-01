<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * NovedadController — Planilla de Novedades de Sueldos.
 *
 * Arma, por empresa + período (mes/año), una planilla por empleado activo,
 * calculando: días trabajados (marcaciones de reloj + ajustes), licencias con
 * y sin goce, vacaciones, valores y montos de horas extra por convenio, básico
 * y los datos cargados en novedades. El período va del 20 del mes anterior al
 * 19 de este mes.
 *
 * NOTA: esta primera etapa es la CONSULTA (grilla). Los procesos de
 * almuerzos/anticipos/sueldos automáticos, confirmar (grabar) y exportar se
 * agregan después con su código FoxPro.
 */
class NovedadController extends Controller
{
    /** @route GET /api/novedades/planilla?empresa=&mes=&anio=&contratista=&nro_planilla= */
    public function planilla(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empresa'      => 'nullable|integer',
            'mes'          => 'required|integer|min:1|max:12',
            'anio'         => 'required|integer|min:2000|max:2999',
            'contratista'  => 'nullable|integer',
            'nro_planilla' => 'nullable|integer',
        ]);

        $empresa = (int) ($datos['empresa'] ?? 1);
        $mes = (int) $datos['mes']; $anio = (int) $datos['anio'];
        $contratista = (int) ($datos['contratista'] ?? 0);
        $nroPlanilla = (int) ($datos['nro_planilla'] ?? 0);

        // Período: del 20 del mes anterior al 19 de este mes
        $ini = Carbon::create($anio, $mes, 1)->subMonth()->day(20)->startOfDay();
        $fin = Carbon::create($anio, $mes, 19)->startOfDay();
        $iniS = $ini->format('Y-m-d'); $finS = $fin->format('Y-m-d');

        // Bloqueo del período (tipo Novedades)
        $bloqueado = DB::table('bloqueos')
            ->whereRaw("UPPER(LTRIM(RTRIM(BLO_TIP))) = 'N'")
            ->where('BLO_MES', $mes)->where('BLO_ANO', $anio)
            ->whereRaw("UPPER(LTRIM(RTRIM(BLO_EST))) = 'S'")->exists();

        // Personal activo de la empresa (+ convenio), opcional por contratista
        $persQ = DB::table('personal as a')->join('convenio as b', 'b.CON_COD', '=', 'a.PER_CON')
            ->where('a.PER_EMP', $empresa)->where('a.PER_AOP', 'A');
        if ($contratista > 0) $persQ->where('a.PER_CONTRA', $contratista);
        $personas = $persQ->get([
            'a.PER_COD', 'a.PER_NOM', 'a.PER_LEG', 'a.PER_CON', 'a.PER_SUE', 'a.PER_REMU',
            'a.PER_VIA', 'a.PER_ADI_ENC', 'a.PER_ADI_SUS', 'a.PER_ANTI', 'a.PER_NREM',
            DB::raw('LTRIM(RTRIM(b.CON_DES)) as con_des'),
        ]);
        $codes = $personas->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();
        if (empty($codes)) {
            return response()->json(['rows' => [], 'bloqueado' => $bloqueado, 'periodo' => $this->periodoTexto($ini, $fin)]);
        }

        // Novedades del período (mapa por empleado)
        $nov = DB::table('novedades')->where('NOV_MES', $mes)->where('NOV_ANO', $anio)
            ->whereIn('NOV_PER', $codes)->get()->keyBy('NOV_PER');

        // Adelantos: suma de anticipos del período por empleado (tabla anticipo)
        $adel = DB::table('anticipo')->where('ANT_MES', $mes)->where('ANT_ANO', $anio)
            ->whereIn('ANT_PER', $codes)
            ->select('ANT_PER', DB::raw('SUM(ANT_IMP) as total'))
            ->groupBy('ANT_PER')->pluck('total', 'ANT_PER');

        // Horas extras de la planilla indicada
        $hex = DB::table('horas_extras')->where('HRE_NUMERO', $nroPlanilla)
            ->whereIn('HRE_PER_COD', $codes)->get()->keyBy('HRE_PER_COD');

        // Días trabajados: marcaciones de reloj + ajustes manuales
        $diasSet = [];
        foreach (DB::table('reloj')->whereIn('REL_COD', $codes)->where('IGNORAR', 0)
            ->whereRaw('CAST(REL_FEC AS DATE) BETWEEN ? AND ?', [$iniS, $finS])
            ->get([DB::raw('REL_COD as cod'), DB::raw('CAST(REL_FEC AS DATE) as f')]) as $r) {
            $diasSet[(int) $r->cod][substr((string) $r->f, 0, 10)] = true;
        }
        foreach (DB::table('reloj_ajustes')->whereIn('AJR_PER', $codes)
            ->whereRaw('CAST(AJR_FEC AS DATE) BETWEEN ? AND ?', [$iniS, $finS])->get() as $a) {
            if ((int) $a->AJR_VE1 === 1 || (int) $a->AJR_VS1 === 1 || (int) $a->AJR_VE2 === 1 || (int) $a->AJR_VS2 === 1) {
                $diasSet[(int) $a->AJR_PER][substr((string) $a->AJR_FEC, 0, 10)] = true;
            }
        }

        // Licencias (con goce = enfermedad; sin goce = plan) y su detalle
        $goceDays = []; $singoceDays = []; $goceDet = []; $singoceDet = [];
        foreach (DB::table('reloj_faltas_diarias as a')->join('licencias as b', 'a.AFD_LIC', '=', 'b.LIC_COD')
            ->whereIn('a.AFD_PER', $codes)
            ->whereRaw('CAST(a.AFD_FE1 AS DATE) <= ?', [$finS])
            ->whereRaw('CAST(a.AFD_FE2 AS DATE) >= ?', [$iniS])
            ->get(['a.AFD_PER', 'a.AFD_LIC', 'a.AFD_LID', 'b.LIC_ENF', 'b.LIC_NOA', 'b.LIC_PLA',
                DB::raw('CAST(a.AFD_FE1 AS DATE) as fe1'), DB::raw('CAST(a.AFD_FE2 AS DATE) as fe2')]) as $f) {
            if ((int) $f->LIC_NOA === 1) continue;
            $cod = (int) $f->AFD_PER;
            $d1 = Carbon::parse($f->fe1)->max($ini); $d2 = Carbon::parse($f->fe2)->min($fin);
            if ($d1->gt($d2)) continue;
            // El orden importa: diffInDays devuelve un valor CON SIGNO (Carbon 3), así que
            // va del menor al mayor. Invertido daba días negativos en la planilla y el detalle.
            $dias = $d1->diffInDays($d2) + 1;
            for ($d = $d1->copy(); $d->lte($d2); $d->addDay()) {
                $k = $d->format('Y-m-d');
                if ((int) $f->LIC_ENF === 1) $goceDays[$cod][$k] = true;
                if ((int) $f->LIC_PLA === 1) $singoceDays[$cod][$k] = true;
            }
            if ((int) $f->LIC_ENF === 1) {
                $goceDet[$cod][(int) $f->AFD_LIC]['det'] = trim((string) $f->AFD_LID);
                $goceDet[$cod][(int) $f->AFD_LIC]['n'] = ($goceDet[$cod][(int) $f->AFD_LIC]['n'] ?? 0) + $dias;
            }
            if ((int) $f->LIC_PLA === 1) {
                $singoceDet[$cod][(int) $f->AFD_LIC]['det'] = trim((string) $f->AFD_LID);
                $singoceDet[$cod][(int) $f->AFD_LIC]['n'] = ($singoceDet[$cod][(int) $f->AFD_LIC]['n'] ?? 0) + $dias;
            }
        }

        // Vacaciones del período
        $vacDays = [];
        foreach (DB::table('vacaciones')->whereIn('VAC_PER', $codes)->where('VAC_VTR', 0)
            ->whereRaw('CAST(VAC_FDE AS DATE) <= ?', [$finS])
            ->whereRaw('CAST(VAC_FHA AS DATE) >= ?', [$iniS])
            ->get(['VAC_PER', DB::raw('CAST(VAC_FDE AS DATE) as fde'), DB::raw('CAST(VAC_FHA AS DATE) as fha')]) as $v) {
            // Se cuentan los días COMPLETOS del tramo de vacaciones (no se recorta al período),
            // igual que FoxPro: si las vacaciones arrancan antes del 20 o terminan después del 19,
            // igual se informan enteras. El filtro de arriba ya exige que el tramo toque el período.
            $d1 = Carbon::parse($v->fde); $d2 = Carbon::parse($v->fha);
            if ($d2->gte($d1)) $vacDays[(int) $v->VAC_PER] = ($vacDays[(int) $v->VAC_PER] ?? 0) + $d1->diffInDays($d2) + 1;
        }

        $rows = [];
        foreach ($personas as $p) {
            $cod = (int) $p->PER_COD;
            $con = (int) $p->PER_CON;
            // El convenio se identifica por su DESCRIPCIÓN y no por el código: los códigos
            // difieren entre empresas (en Logística 1=SMATA y 7=CARGA Y DESCARGA; en
            // Autoelevadores 7=SMATA ACARA). Con el número fijo, los boletos de SMATA se
            // calculaban en la empresa equivocada.
            $conDes     = mb_strtoupper(trim((string) $p->con_des));
            $esSmata    = str_contains($conDes, 'SMATA');
            $esCamioner = str_contains($conDes, 'CAMIONERO');

            $sueldo = $esCamioner ? (float) $p->PER_REMU : (float) $p->PER_SUE;   // camioneros usa el remunerativo
            $n = $nov[$cod] ?? null;
            $h = $hex[$cod] ?? null;

            // Valores de hora extra según convenio
            $div = $esCamioner ? 192 : 200;
            $add = $esSmata ? 15.8 : 0;   // SMATA suma un fijo
            $hn = $div ? $sueldo / $div : 0;
            // El valor de la hora se redondea a 2 decimales ANTES de multiplicarlo por la
            // cantidad de horas (así lo hace FoxPro). Redondeando recién al final salían
            // diferencias de centavos contra la planilla del estudio.
            $v50 = round($hn * 1.5 + $add, 2); $v100 = round($hn * 2.0 + $add, 2); $vnoc = round($hn * 0.1333 + $add, 2);

            $can100 = $h ? (float) $h->HRE_HS_100 : 0;
            $can50  = $h ? (float) $h->HRE_HS_50 : 0;
            $cannoc = $h ? (float) $h->HRE_HS_NOC : 0;
            $canvia = $n ? (float) $n->NOV_CVIA : 0;
            $advia  = (float) $p->PER_VIA;

            $diasTrab    = isset($diasSet[$cod]) ? count($diasSet[$cod]) : 0;
            $diasVaca    = min((int) ($vacDays[$cod] ?? 0), 30);
            $diasCongoce = min(isset($goceDays[$cod]) ? count($goceDays[$cod]) : 0, 30);
            $diasSingoce = min(isset($singoceDays[$cod]) ? count($singoceDays[$cod]) : 0, 30);

            // Detalle de licencias + boletos + adicionales
            $det = '';
            if (!empty($goceDet[$cod])) {
                $partes = [];
                foreach ($goceDet[$cod] as $g) $partes[] = '(' . min($g['n'], 30) . ') ' . $g['det'];
                $det = 'Con goce: ' . implode(',', $partes);
            }
            if (!empty($singoceDet[$cod])) {
                $partes = [];
                foreach ($singoceDet[$cod] as $g) $partes[] = '(' . min($g['n'], 30) . ') ' . $g['det'];
                $det .= ($det !== '' ? '  ' : '') . 'Sin goce:' . implode(',', $partes);
            }
            $det = trim($det);
            if ($esSmata) {   // SMATA: cantidad de boletos = días trabajados * 2
                $det = ($diasTrab * 2) . ' Boletos' . ($det !== '' ? ',' . $det : '');
            }
            if ((int) $p->PER_ADI_ENC === 1) $det .= ' (ADICIONAL ENCARGADO)';
            if ((int) $p->PER_ADI_SUS === 1) $det .= ' (ADICIONAL SUSTANCIAS PELIGROSAS)';

            $m100 = $v100 * $can100; $m50 = $v50 * $can50; $mnoc = $vnoc * $cannoc;
            $mAvNeto = $canvia * $advia;
            $mAvExtra = $m100 + $m50 + $mnoc;

            // Etapa 2: sueldos / anticipos / almuerzo / días mes / total neto
            $noremu    = (float) $p->PER_NREM;                  // No remunerativo (de personal)
            $adelantos = (float) ($adel[$cod] ?? 0);            // suma de anticipos del período
            $anticipos = (float) $p->PER_ANTI;                  // anticipo fijo de personal
            $almuerzos = $diasTrab;                             // (cambio 16/01/2026) almuerzos = días trabajados
            $diasMes   = max(0, 30 - $diasVaca - $diasCongoce - $diasSingoce);
            $totalNeto = $sueldo + $mAvExtra;

            $rows[] = [
                'codigo'         => $cod,
                'nombre'         => trim((string) $p->PER_NOM),
                'legajo'         => trim((string) $p->PER_LEG),
                'convenio'       => trim((string) $p->con_des),
                'convenio_cod'   => $con,
                'noremu'         => $noremu,
                'dias_mes'       => $diasMes,
                'dias_trab'      => $diasTrab,
                'com_vtas'       => $n ? (float) $n->NOV_CVE : 0,
                'com_cob'        => $n ? (float) $n->NOV_CCO : 0,
                'adelantos'      => $adelantos,
                'anticipos'      => $anticipos,
                'almuerzos'      => $almuerzos,
                'neto'           => $sueldo,
                'adicional_neto' => $n ? (float) $n->NOV_AN1 : 0,
                'presentismo'    => $n ? (float) $n->NOV_OD1 : 0,
                'dias_vaca'      => $diasVaca,
                'lic_congoce'    => $diasCongoce,
                'lic_singoce'    => $diasSingoce,
                'can_h100'       => $can100,
                'horas_100'      => round($m100, 2),
                'can_h50'        => $can50,
                'horas_50'       => round($m50, 2),
                'can_noc'        => $cannoc,
                'horas_noc'      => round($mnoc, 2),
                'can_via'        => $canvia,
                'adi_via_neto'   => round($mAvNeto, 2),
                'neto_extra'     => round($mAvExtra, 2),
                'total_neto'     => round($totalNeto, 2),
                'basico'         => $sueldo,
                'observaciones'  => $n ? trim((string) $n->NOV_OB1) : '',
                'detalles'       => $det,
                'valor_h100'     => round($v100, 2),
                'valor_h50'      => round($v50, 2),
                'valor_noc'      => round($vnoc, 2),
                'valor_adi_via'  => $advia,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return response()->json([
            'rows'      => $rows,
            'bloqueado' => $bloqueado,
            'periodo'   => $this->periodoTexto($ini, $fin),
        ]);
    }

    /**
     * CONFIRMAR: graba la planilla calculada en las novedades del período.
     * Recalcula en el servidor (mismos parámetros) y hace upsert por empleado.
     * @route POST /api/novedades/confirmar
     */
    public function confirmar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empresa'      => 'nullable|integer',
            'mes'          => 'required|integer|min:1|max:12',
            'anio'         => 'required|integer|min:2000|max:2999',
            'contratista'  => 'nullable|integer',
            'nro_planilla' => 'nullable|integer',
        ]);

        $res = json_decode($this->planilla($request)->getContent(), true);
        if (!empty($res['bloqueado'])) {
            return response()->json(['message' => 'El período está bloqueado: no se puede confirmar.'], 422);
        }
        $rows = $res['rows'] ?? [];
        if (empty($rows)) {
            return response()->json(['message' => 'No hay novedades para confirmar.'], 422);
        }

        $mes = (int) $datos['mes']; $anio = (int) $datos['anio'];

        DB::transaction(function () use ($rows, $mes, $anio) {
            foreach ($rows as $r) {
                $vals = [
                    'NOV_DMES'    => $r['dias_mes'],
                    'NOV_DTR'     => $r['dias_trab'],
                    'NOV_CVE'     => $r['com_vtas'],
                    'NOV_CCO'     => $r['com_cob'],
                    'NOV_ADE'     => $r['adelantos'],
                    'NOV_ANT'     => $r['anticipos'],
                    'NOV_ALM'     => $r['almuerzos'],
                    'NOV_OD1'     => $r['presentismo'],
                    'NOV_NJ1'     => $r['neto'],
                    'NOV_AN1'     => $r['adicional_neto'],
                    'NOV_OB1'     => $r['observaciones'],
                    'NOV_BAS'     => $r['basico'],
                    'NOV_NOR'     => $r['noremu'],
                    'NOV_DVA'     => $r['dias_vaca'],
                    'NOV_DEN'     => $r['lic_congoce'],
                    'NOV_DLI'     => $r['lic_singoce'],
                    'NOV_C100'    => $r['can_h100'],
                    'NOV_C050'    => $r['can_h50'],
                    'NOV_CNOC'    => $r['can_noc'],
                    'NOV_CVIA'    => $r['can_via'],
                    'NOV_DETALLE' => mb_substr((string) $r['detalles'], 0, 200),
                ];
                $q = DB::table('novedades')->where('NOV_MES', $mes)->where('NOV_ANO', $anio)->where('NOV_PER', $r['codigo']);
                if ($q->exists()) {
                    $q->update($vals);
                } else {
                    DB::table('novedades')->insert(Registro::completar('novedades', array_merge($vals, [
                        'NOV_MES' => $mes, 'NOV_ANO' => $anio, 'NOV_QUI' => 1,
                        'NOV_PER' => $r['codigo'], 'NOV_NOM' => mb_substr((string) $r['nombre'], 0, 50),
                    ])));
                }
            }
        });

        return response()->json(['ok' => true, 'grabados' => count($rows)]);
    }

    /**
     * EXPORTAR NOVEDADES: trae las novedades ya confirmadas/grabadas del período
     * (lee de la tabla guardada, no recalcula), por empleado activo de la empresa.
     * @route GET /api/novedades/exportar-datos?empresa=&mes=&anio=
     */
    public function exportarDatos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empresa' => 'nullable|integer',
            'mes'     => 'required|integer|min:1|max:12',
            'anio'    => 'required|integer|min:2000|max:2999',
        ]);
        $empresa = (int) ($datos['empresa'] ?? 1);
        $mes = (int) $datos['mes']; $anio = (int) $datos['anio'];

        $personas = DB::table('personal as p')
            ->join('convenio as c', 'c.CON_COD', '=', 'p.PER_CON')
            ->leftJoin('contratista as t', 't.CONT_COD', '=', 'p.PER_CONTRA')
            ->where('p.PER_EMP', $empresa)->where('p.PER_AOP', 'A')
            ->get([
                'p.PER_COD', 'p.PER_NOM', 'p.PER_LEG', 'p.PER_CUI', 'p.PER_BAD',
                DB::raw('LTRIM(RTRIM(c.CON_DES)) as con_des'),
                DB::raw('LTRIM(RTRIM(t.CONT_DET)) as cont_det'),
            ]);
        $codes = $personas->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();

        $nov = empty($codes) ? collect() : DB::table('novedades')
            ->where('NOV_MES', $mes)->where('NOV_ANO', $anio)
            ->whereIn('NOV_PER', $codes)->get()->keyBy('NOV_PER');

        $rows = [];
        foreach ($personas as $p) {
            $cod = (int) $p->PER_COD;
            $n = $nov[$cod] ?? null;
            $rows[] = [
                'codigo'      => $cod,
                'nombre'      => trim((string) $p->PER_NOM),
                'legajo'      => trim((string) $p->PER_LEG),
                'cuil'        => trim((string) $p->PER_CUI),
                'convenio'    => trim((string) $p->con_des),
                'basico'      => $n ? (float) $n->NOV_BAS : 0,
                'noremu'      => $n ? (float) $n->NOV_NOR : 0,
                'm_dmes'      => $n ? (int) $n->NOV_DMES : 0,
                'm_dtr'       => $n ? (int) $n->NOV_DTR : 0,
                'm_cve'       => $n ? (float) $n->NOV_CVE : 0,
                'm_cco'       => $n ? (float) $n->NOV_CCO : 0,
                'm_ade'       => $n ? (float) $n->NOV_ADE : 0,
                'm_ant'       => $n ? (float) $n->NOV_ANT : 0,
                'm_alm'       => $n ? (float) $n->NOV_ALM : 0,
                'm_od1'       => $n ? (float) $n->NOV_OD1 : 0,
                'm_nj1'       => $n ? (float) $n->NOV_NJ1 : 0,
                'm_an1'       => $n ? (float) $n->NOV_AN1 : 0,
                'dias_vaca'   => $n ? (int) $n->NOV_DVA : 0,
                'dias_enfer'  => $n ? (int) $n->NOV_DEN : 0,
                'dias_licen'  => $n ? (int) $n->NOV_DLI : 0,
                'm_h100'      => 0,
                'm_h050'      => 0,
                'm_hnoc'      => 0,
                'm_av_neto'   => 0,
                'm_av_extra'  => 0,
                'm_totalneto' => 0,
                'm_ob1'       => $n ? trim((string) $n->NOV_OB1) : '',
                'contrata'    => trim((string) $p->cont_det),
                'banco'       => trim((string) $p->PER_BAD),
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return response()->json($rows);
    }

    /**
     * Buscador de planillas de horas extras: lista nro / detalle / fecha.
     * @route GET /api/novedades/planillas-hs-extras
     */
    public function planillasHsExtras(): JsonResponse
    {
        $filas = DB::table('horas_extras')
            ->select('HRE_NUMERO', 'HRE_PLANILLA', DB::raw('MAX(HRE_FECHA) as HRE_FECHA'))
            ->groupBy('HRE_NUMERO', 'HRE_PLANILLA')
            ->orderByDesc('HRE_NUMERO')
            ->get()
            ->map(fn ($r) => [
                'numero'  => (int) $r->HRE_NUMERO,
                'detalle' => trim((string) $r->HRE_PLANILLA),
                'fecha'   => $r->HRE_FECHA ? Carbon::parse($r->HRE_FECHA)->format('d/m/Y') : '',
            ]);

        return response()->json($filas);
    }

    private function periodoTexto(Carbon $ini, Carbon $fin): string
    {
        return 'Días trabajados del ' . $ini->format('d/m/Y') . ' al ' . $fin->format('d/m/Y') . '.';
    }
}
