<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ControlSueldoController — Planilla Control de Sueldos (planilla_control_sueldos).
 *
 * Por mes/año arma un control de los empleados activos de la empresa cruzando las
 * liquidaciones (LIQUIDAC + LIQ_ITE) por tipo, más el almuerzo del período, y permite
 * grabar la planilla resultante (planilla_sueldo) o recuperar una guardada.
 *
 * Tipos de liquidación (LIQ_TIP): 1=Sueldo mensual, 3=Anticipo, 9=Gratificaciones/Premio,
 * 4=Horas extras, 2=Vacaciones, 10=SAC. Monto de cada una = SUM(LIT_HAB - LIT_DED).
 */
class ControlSueldoController extends Controller
{
    /** @route GET /api/control-sueldos/generar?mes=&anio=&empresa= */
    public function generar(Request $request): JsonResponse
    {
        $d = $request->validate(['mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer', 'empresa' => 'nullable|integer']);
        $mes = (int) $d['mes']; $anio = (int) $d['anio']; $empresa = (int) ($d['empresa'] ?? 1) ?: 1;

        $emps = DB::table('personal')->where('PER_EMP', $empresa)->where('PER_AOP', 'A')->orderBy('PER_NOM')
            ->get(['PER_COD', 'PER_LEG', 'PER_NOM', 'PER_BAD', 'PER_SUE', 'PER_SED', 'PER_SUD']);
        $cods = $emps->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();
        if (!$cods) return response()->json(['filas' => [], 'mes' => $mes, 'anio' => $anio]);

        // (cod, tip) -> LIQ_NRO  para el período
        $nroBy = []; $nros = [];
        foreach (DB::table('LIQUIDAC')->where('LIQ_MES', $mes)->where('LIQ_ANO', $anio)->whereIn('LIQ_COD', $cods)
            ->get(['LIQ_NRO', 'LIQ_COD', 'LIQ_TIP']) as $l) {
            $nroBy[(int) $l->LIQ_COD][(int) $l->LIQ_TIP] = (int) $l->LIQ_NRO;
            $nros[] = (int) $l->LIQ_NRO;
        }
        // LIQ_NRO -> SUM(LIT_HAB - LIT_DED)
        $sumByNro = [];
        if ($nros) {
            foreach (DB::table('LIQ_ITE')->whereIn('LIT_NRO', $nros)
                ->groupBy('LIT_NRO')->selectRaw('LIT_NRO, SUM(LIT_HAB - LIT_DED) as s')->get() as $r) {
                $sumByNro[(int) $r->LIT_NRO] = (float) $r->s;
            }
        }
        $monto = fn (int $cod, int $tip) => $sumByNro[$nroBy[$cod][$tip] ?? -1] ?? 0.0;

        // Almuerzos del período (del 20 del mes anterior al 19 del mes elegido)
        $mesAlm = $mes - 1; $anioAlm = $anio;
        if ($mesAlm < 1) { $mesAlm = 12; $anioAlm--; }
        $f1 = \Carbon\Carbon::create($anioAlm, $mesAlm, 20)->toDateString();
        $f2 = \Carbon\Carbon::create($anio, $mes, 19)->toDateString();
        $costo = (float) (DB::table('ALM_COSTO')->where('MES', $mes)->where('ANIO', $anio)->value('IMPORTE') ?? 0);
        $costoUnit = $costo > 0 ? $costo : 1;

        // Vacaciones y licencias por empleado (para excluir días de almuerzo)
        $vacBy = []; $licBy = [];
        foreach (DB::table('vacaciones')->whereIn('VAC_PER', $cods)->get(['VAC_PER', 'VAC_FDE', 'VAC_FHA']) as $v) {
            $vacBy[(int) $v->VAC_PER][] = [$v->VAC_FDE, $v->VAC_FHA];
        }
        foreach (DB::table('reloj_faltas_diarias')->whereIn('AFD_PER', $cods)->get(['AFD_PER', 'AFD_FE1', 'AFD_FE2']) as $l) {
            $licBy[(int) $l->AFD_PER][] = [$l->AFD_FE1, $l->AFD_FE2];
        }
        $enRango = function ($rangos, $fec) {
            foreach ($rangos ?? [] as [$de, $ha]) {
                if ($de && $ha && $fec >= \Carbon\Carbon::parse($de)->toDateString() && $fec <= \Carbon\Carbon::parse($ha)->toDateString()) return true;
            }
            return false;
        };
        $almBy = [];
        foreach (DB::table('almuerzo')->whereBetween('ALM_FEC', [$f1 . ' 00:00:00', $f2 . ' 23:59:59'])
            ->where('ALM_CAN', '>', 0)->whereIn('ALM_PER', $cods)->get(['ALM_PER', 'ALM_FEC', 'ALM_CAN']) as $a) {
            $per = (int) $a->ALM_PER; $fec = \Carbon\Carbon::parse($a->ALM_FEC)->toDateString();
            if ($enRango($vacBy[$per] ?? [], $fec) || $enRango($licBy[$per] ?? [], $fec)) continue;
            $almBy[$per] = ($almBy[$per] ?? 0) + $costoUnit * (float) $a->ALM_CAN;
        }

        $filas = $emps->map(function ($e) use ($monto, $almBy) {
            $cod = (int) $e->PER_COD;
            $sueldo = round($monto($cod, 1), 2); $anticipo = round($monto($cod, 3), 2); $premio = round($monto($cod, 9), 2);
            $extras = round($monto($cod, 4), 2); $vacacion = round($monto($cod, 2), 2); $sac = round($monto($cod, 10), 2);
            $almuerzo = round($almBy[$cod] ?? 0, 2); $neto = round((float) $e->PER_SUE, 2); $ganancia = 0.0; $otros = 0.0;
            $deposito = round($sueldo + $extras + $vacacion + $anticipo + $sac + $premio, 2);
            $diferencia = round(($sueldo + $anticipo + $premio + $ganancia + $almuerzo + $otros + $vacacion) - $neto, 2);
            return [
                'legajo' => (int) $e->PER_LEG, 'codigo' => $cod, 'nombre' => trim((string) $e->PER_NOM), 'banco' => trim((string) $e->PER_BAD),
                'neto' => $neto, 'sueldo' => $sueldo, 'extras' => $extras, 'stotal' => round($sueldo + $extras, 2),
                'anticipo' => $anticipo, 'premio' => $premio, 'vacacion' => $vacacion, 'ganancia' => $ganancia,
                'almuerzo' => $almuerzo, 'sac' => $sac, 'otros' => $otros, 'deposito' => $deposito, 'diferencia' => $diferencia,
                'sector' => trim((string) $e->PER_SED), 'subsector' => trim((string) $e->PER_SUD),
            ];
        });

        return response()->json(['filas' => $filas->values(), 'mes' => $mes, 'anio' => $anio]);
    }

    /** Lista de planillas guardadas. @route GET /api/control-sueldos/guardadas */
    public function guardadas(): JsonResponse
    {
        $rows = DB::table('planilla_sueldo')
            ->select('PSUE_NRO', DB::raw('MAX(LTRIM(RTRIM(PSUE_DET))) as det'), DB::raw('MAX(PSUE_FEC) as fec'), DB::raw('COUNT(*) as filas'))
            ->groupBy('PSUE_NRO')->orderByDesc('PSUE_NRO')->get()
            ->map(fn ($p) => [
                'nro' => (int) $p->PSUE_NRO, 'detalle' => trim((string) $p->det),
                'fecha' => $p->fec ? \Carbon\Carbon::parse($p->fec)->format('d/m/Y H:i') : '', 'filas' => (int) $p->filas,
            ]);
        return response()->json($rows);
    }

    /** Recupera una planilla guardada. @route GET /api/control-sueldos/{nro} */
    public function show(int $nro): JsonResponse
    {
        $rows = DB::table('planilla_sueldo')->where('PSUE_NRO', $nro)->orderBy('PSUE_NOM')->get()->map(fn ($p) => [
            'legajo' => (int) $p->PSUE_LEG, 'codigo' => (int) $p->PSUE_COD, 'nombre' => trim((string) $p->PSUE_NOM), 'banco' => trim((string) $p->PSUE_BAN),
            'neto' => (float) $p->PSUE_NET, 'sueldo' => (float) $p->PSUE_SUE, 'extras' => (float) $p->PSUE_EXT, 'stotal' => (float) $p->PSUE_STO,
            'anticipo' => (float) $p->PSUE_ANT, 'premio' => (float) $p->PSUE_PRE, 'vacacion' => (float) $p->PSUE_VAC, 'ganancia' => (float) $p->PSUE_GAN,
            'almuerzo' => (float) $p->PSUE_ALM, 'sac' => (float) $p->PSUE_SAC, 'otros' => (float) $p->PSUE_OTR, 'deposito' => (float) $p->PSUE_DEP, 'diferencia' => (float) $p->PSUE_DIF,
            'sector' => '', 'subsector' => '',
        ]);
        return response()->json(['filas' => $rows->values()]);
    }

    /** Graba la planilla. @route POST /api/control-sueldos  body: { nombre, rows:[...] } */
    public function grabar(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:100', 'rows' => 'required|array|min:1']);
        $usuario = substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30);
        $terminal = substr((string) $request->ip(), 0, 20);
        $nombre = mb_strtoupper(trim($d['nombre']));

        $nro = 0;
        DB::transaction(function () use ($d, $nombre, $usuario, $terminal, &$nro) {
            $nro = ((int) DB::table('planilla_sueldo')->max('PSUE_NRO')) + 1;
            foreach ($d['rows'] as $r) {
                DB::table('planilla_sueldo')->insert(Registro::completar('planilla_sueldo', [
                    'PSUE_NRO' => $nro, 'PSUE_DET' => $nombre, 'PSUE_FEC' => now(), 'PSUE_USU' => $usuario, 'PSUE_TER' => $terminal,
                    'PSUE_LEG' => (int) ($r['legajo'] ?? 0), 'PSUE_NOM' => trim((string) ($r['nombre'] ?? '')), 'PSUE_BAN' => trim((string) ($r['banco'] ?? '')),
                    'PSUE_NET' => (float) ($r['neto'] ?? 0), 'PSUE_SUE' => (float) ($r['sueldo'] ?? 0), 'PSUE_EXT' => (float) ($r['extras'] ?? 0), 'PSUE_STO' => (float) ($r['stotal'] ?? 0),
                    'PSUE_ANT' => (float) ($r['anticipo'] ?? 0), 'PSUE_PRE' => (float) ($r['premio'] ?? 0), 'PSUE_VAC' => (float) ($r['vacacion'] ?? 0), 'PSUE_GAN' => (float) ($r['ganancia'] ?? 0),
                    'PSUE_ALM' => (float) ($r['almuerzo'] ?? 0), 'PSUE_SAC' => (float) ($r['sac'] ?? 0), 'PSUE_OTR' => (float) ($r['otros'] ?? 0),
                    'PSUE_DEP' => (float) ($r['deposito'] ?? 0), 'PSUE_DIF' => (float) ($r['diferencia'] ?? 0), 'PSUE_COD' => (int) ($r['codigo'] ?? 0),
                ]));
            }
        });

        return response()->json(['ok' => true, 'nro' => $nro], 201);
    }
}
