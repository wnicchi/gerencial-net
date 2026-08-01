<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CostoIndividualController — Costos Laborales: Calcular Costo Individual
 * (empleados_costos_individual.scx).
 *
 * Calcula el costo laboral de un empleado para un período (mes/año) según su
 * convenio (SMATA / Fuera de convenio / Camioneros larga y no-larga), armando
 * los rubros REMUNERATIVOS / CARGAS / PREVISIÓN a partir de la última
 * liquidación, más los costos fijos del período (costo_laboral_fijo) como
 * "GASTOS VARIOS". Réplica del LostFocus del código de empleado del FoxPro.
 */
class CostoIndividualController extends Controller
{
    /** @route GET /api/costos-individual?empleado=&mes=&anio= */
    public function calcular(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado' => 'required|integer|min:1',
            'mes'      => 'required|integer|min:1|max:12',
            'anio'     => 'required|integer|min:2000|max:2200',
        ]);

        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first();
        if (!$per) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }

        // SISTEMA_RRHH_SILCAR: verdadero en la empresa Silcar, falso en Logística.
        $silcar = config('rrhh.empresa', 'silcar') !== 'logist';
        $con = (int) $per->PER_CON;
        $cat = (int) $per->PER_CAT;

        $fechaPeriodo   = Carbon::create($d['anio'], $d['mes'], 1);
        $fechaComputada = Carbon::create($d['anio'] - 1, 12, 31);   // último día del año anterior
        $ing = $this->fecha($per->PER_ING) ? Carbon::parse(substr((string) $per->PER_ING, 0, 10)) : $fechaPeriodo->copy();
        $base = $fechaComputada->lt($ing) ? $fechaPeriodo : $fechaComputada;
        $diasTrab = (int) $ing->diffInDays($base, false);           // días trabajados (base - ingreso)

        // Días de vacaciones que corresponden (tabla vacadias, por rango de antigüedad en días).
        $corresponden = 0;
        foreach (DB::table('vacadias')->get() as $v0) {
            $v = array_change_key_case((array) $v0, CASE_UPPER);   // columnas en minúscula en la BD
            $diasIni = (int) $v['VRE_AINI'] * 365 + (int) $v['VRE_MINI'] * 30;
            $diasFin = (int) $v['VRE_AFIN'] * 365 + (int) $v['VRE_MFIN'] * 30;
            if ($diasTrab >= $diasIni && $diasTrab <= $diasFin && (int) $v['VRE_DIAS'] > $corresponden) {
                $corresponden = (int) $v['VRE_DIAS'];
                break;
            }
        }
        $antiguedadAnios = $diasTrab > 0 ? intdiv($diasTrab, 365) : 0;

        $calc = [];
        $add = function (string $rubro, string $detalle, float $importe) use (&$calc) {
            $calc[] = ['rubro' => $rubro, 'detalle' => $detalle, 'importe' => round($importe, 2)];
        };
        $sumRemun = fn () => array_sum(array_column($calc, 'importe'));

        if ((!$silcar && $con === 1) || ($silcar && $con === 7)) {
            // ── SMATA (cargas 24 %) ──
            $bruto = $this->sueldoBrutoPeriodo((int) $per->PER_COD, (int) $d['anio'], (int) $d['mes']);
            $add('REMUNERATIVOS', 'SUELDO BRUTO - SALARIO BASE', $bruto);
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 24%', $sumRemun() * 24 / 100);
            $add('PREVISION', 'DESPIDOS - CONTRATO 9 AÑOS', ($bruto * 9) / 108);
        } elseif ($con === 3) {
            // ── Fuera de convenio (cargas 21 %) ──
            $bruto = $this->sueldoBrutoPeriodo((int) $per->PER_COD, (int) $d['anio'], (int) $d['mes']);
            $add('REMUNERATIVOS', 'SUELDO BRUTO - SALARIO BASE', $bruto);
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 21%', $sumRemun() * 21 / 100);
            $add('PREVISION', 'DESPIDOS - CONTRATO 9 AÑOS', ($bruto * 9) / 108);
        } elseif (!$silcar && $con === 6 && $cat !== 48) {
            // ── Camioneros NO larga distancia (cargas 26 %, previsión 5/108) ──
            $item = $this->itemsUltimos90($fechaPeriodo, (int) $per->PER_COD);
            $sal = $item(10, 'SALARIO BASE');
            $bruto = $sal['importe'];
            $add('REMUNERATIVOS', $sal['detalle'], $sal['importe']);
            $ant = $item(255, 'ANTIGÜEDAD');
            $add('REMUNERATIVOS', $ant['detalle'], $ant['importe']);
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 26%', $sumRemun() * 26 / 100);
            $add('PREVISION', 'DESPIDOS - CONTRATO 5 AÑOS', ($bruto * 5) / 108);
        } elseif ((!$silcar && $con === 6 && $cat === 48) || ($silcar && $con === 4)) {
            // ── Camioneros LARGA distancia (cargas 26 %, previsión 5/60, + viático) ──
            $item = $this->itemsUltimos90($fechaPeriodo, (int) $per->PER_COD);
            $sal = $item(10, 'SALARIO BASE');
            $bruto = $sal['importe'];
            foreach ([
                [10, 'SALARIO BASE'], [241, 'KM RECORRIDO SIMPLE'], [75, 'KM RECORRIDO AL 100%'],
                [76, 'CONTROL DE DESCARGA'], [263, 'PERMANENCIA FUERA RESIDENCIA'], [78, 'SIMPLE PRESENCIA'],
                [255, 'ANTIGÜEDAD'],
            ] as [$cod, $def]) {
                $it = $item($cod, $def);
                $add('REMUNERATIVOS', $it['detalle'], $it['importe']);
            }
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 26%', $sumRemun() * 26 / 100);
            $add('PREVISION', 'DESPIDOS - CONTRATO 5 AÑOS', ($bruto * 5) / 60);
            $via = $item(74, 'VIATICO POR KM RECORRIDO');
            $add('NO REMUNERATIVOS', $via['detalle'], $via['importe']);
        }

        // Costos fijos del período (siempre se suman como GASTOS VARIOS).
        foreach (DB::table('costo_laboral_fijo')->where('COS_MES', $d['mes'])->where('COS_ANIO', $d['anio'])->get() as $c) {
            $add('GASTOS VARIOS', trim((string) $c->COS_DET), (float) $c->COS_IMP);
        }

        $total = array_sum(array_column($calc, 'importe'));

        return response()->json([
            'empleado' => [
                'cod'             => (int) $per->PER_COD,
                'nombre'          => trim((string) $per->PER_NOM),
                'convenio'        => mb_strtoupper(trim((string) ($per->PER_CDE ?? ''))),
                'categoria'       => mb_strtoupper(trim((string) ($per->PER_CAD ?? ''))),
                'antiguedad'      => $antiguedadAnios . ' años',
                'dias_vacaciones' => 'Corresponden ' . $corresponden . ' días',
            ],
            'calculo' => $calc,
            'total'   => round($total, 2),
        ]);
    }

    private function fecha($v): string
    {
        return ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
    }

    /** Suma de haberes (LIT_HAB>0) de la liquidación del período (LIQ_TIP=1). */
    private function sueldoBrutoPeriodo(int $emp, int $anio, int $mes): float
    {
        $nros = DB::table('liquidac')
            ->where('LIQ_COD', $emp)->where('LIQ_TIP', 1)->where('LIQ_ANO', $anio)->where('LIQ_MES', $mes)
            ->pluck('LIQ_NRO');
        if ($nros->isEmpty()) {
            return 0.0;
        }
        return (float) DB::table('liq_ite')->whereIn('LIT_NRO', $nros)->where('LIT_HAB', '>', 0)->sum('LIT_HAB');
    }

    /**
     * Devuelve un closure que busca un ítem por LIT_COD entre las liquidaciones
     * de los últimos 90 días del empleado (para camioneros). Retorna importe + detalle.
     */
    private function itemsUltimos90(Carbon $fechaPeriodo, int $emp): callable
    {
        $desde = $fechaPeriodo->copy()->subDays(90)->format('Y-m-d');
        $nros = DB::table('liquidac')->where('LIQ_COD', $emp)->where('LIQ_FEC', '>', $desde)->pluck('LIQ_NRO');

        return function (int $litCod, string $def) use ($nros): array {
            if ($nros->isEmpty()) {
                return ['detalle' => $def, 'importe' => 0.0];
            }
            $it = DB::table('liq_ite')->whereIn('LIT_NRO', $nros)
                ->where('LIT_COD', $litCod)->where('LIT_HAB', '>', 0)
                ->orderByDesc('LIT_NRO')->first();
            if (!$it) {
                return ['detalle' => $def, 'importe' => 0.0];
            }
            $a = array_change_key_case((array) $it, CASE_UPPER);
            $des = trim((string) ($a['LIT_DES'] ?? ''));
            return ['detalle' => $des !== '' ? $des : $def, 'importe' => (float) ($a['LIT_HAB'] ?? 0)];
        };
    }
}
