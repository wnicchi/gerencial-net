<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MultasController — Multas - Listados de Control (multas_listados.scx).
 *
 * Arma un control de cuenta corriente de multas por empleado, cruzando:
 *  - EGRESOS: las multas del sistema de Gestión (VEH_MULT, conexión gestion) imputadas al empleado,
 *  - INGRESOS: los descuentos de multas en las liquidaciones de sueldo (LIQ_ITE cuyo detalle contiene "MULTA").
 *
 * Por cada empleado se ordenan los movimientos por fecha y se calcula el SALDO acumulado (egreso - ingreso).
 * Solo se informan los empleados que quedan con SALDO > 0 (es decir, que todavía adeudan multas).
 *
 * Período: el sistema solo considera movimientos desde el 01/07/2020. "Histórico" = 01/07/2020 hasta hoy;
 * "Rango" = fechas elegidas (la fecha inicial nunca es anterior al 01/07/2020).
 */
class MultasController extends Controller
{
    /** Fecha mínima de corte fijada por el sistema original. */
    private const PISO = '2020-07-01';

    /** @route GET /api/multas-listados?empleado=&periodo=1|2&fecha1=&fecha2=&modo=detallado|resumido */
    public function index(Request $request): JsonResponse
    {
        $empleado = (int) $request->input('empleado', 0);
        $periodo  = (int) $request->input('periodo', 1);

        [$desde, $hasta] = $this->rango($request, $periodo);

        // Empleados activos (opcionalmente uno solo)
        $emp = DB::table('personal')->where('PER_AOP', 'A');
        if ($empleado > 0) $emp->where('PER_COD', $empleado);
        $empleados = $emp->orderBy('PER_NOM')->get(['PER_COD', 'PER_NOM'])
            ->keyBy(fn ($p) => (int) $p->PER_COD);

        // EGRESOS: multas del sistema de Gestión (VEH_MULT) en el período
        $multas = [];
        try {
            $rows = DB::connection('gestion')->table('VEH_MULT')
                ->whereDate('VEM_VE1', '>=', $desde)->whereDate('VEM_VE1', '<=', $hasta)
                ->orderBy('VEM_PER')->orderBy('VEM_VE1')
                ->get(['VEM_PER', 'VEM_DOM', 'VEM_NRO', 'VEM_VE1', 'VEM_IMP']);
            foreach ($rows as $r) {
                $cod = (int) $r->VEM_PER;
                if (!$empleados->has($cod)) continue;
                $multas[] = [
                    'cod'     => $cod,
                    'fecha'   => $r->VEM_VE1,
                    'detalle' => 'MULTA Nº ' . trim((string) $r->VEM_NRO) . ' DOMINIO ' . trim((string) $r->VEM_DOM),
                    'egreso'  => round((float) $r->VEM_IMP, 2),
                    'ingreso' => 0.0,
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('[Multas] gestión VEH_MULT: ' . $e->getMessage());
        }

        // INGRESOS: descuentos de multas en liquidaciones
        $items = [];
        $liq = DB::table('LIQ_ITE')->where('LIT_DES', 'like', '%MULTA%')
            ->whereDate('LIT_FEC', '>=', $desde)->whereDate('LIT_FEC', '<=', $hasta)
            ->orderBy('LIT_ANO')->orderBy('LIT_MES')
            ->get(['LIT_PER', 'LIT_NRO', 'LIT_MES', 'LIT_ANO', 'LIT_FEC', 'LIT_DED']);
        foreach ($liq as $r) {
            $cod = (int) $r->LIT_PER;
            if (!$empleados->has($cod)) continue;
            $items[] = [
                'cod'     => $cod,
                'fecha'   => $r->LIT_FEC,
                'detalle' => 'LIQUIDACIÓN Nº ' . (int) $r->LIT_NRO . ' PERÍODO ' . (int) $r->LIT_MES . '/' . (int) $r->LIT_ANO,
                'egreso'  => 0.0,
                'ingreso' => round((float) $r->LIT_DED, 2),
            ];
        }

        // Agrupar por empleado, ordenar por fecha y calcular saldo acumulado
        $porEmp = [];
        foreach (array_merge($multas, $items) as $m) {
            $porEmp[$m['cod']][] = $m;
        }

        $resultado = [];
        foreach ($porEmp as $cod => $movs) {
            usort($movs, fn ($a, $b) => strcmp((string) $a['fecha'], (string) $b['fecha']));
            $saldo = 0.0;
            $lista = [];
            foreach ($movs as $m) {
                $saldo = round($saldo + $m['egreso'] - $m['ingreso'], 2);
                $lista[] = [
                    'fecha'   => $this->fecha($m['fecha']),
                    'detalle' => $m['detalle'],
                    'egreso'  => $m['egreso'],
                    'ingreso' => $m['ingreso'],
                    'saldo'   => $saldo,
                ];
            }
            if ($saldo > 0) { // solo quienes adeudan
                $p = $empleados->get($cod);
                $resultado[] = [
                    'cod'         => $cod,
                    'nombre'      => trim((string) $p->PER_NOM),
                    'saldo'       => $saldo,
                    'movimientos' => $lista,
                ];
            }
        }
        usort($resultado, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $totalSaldo = round(array_sum(array_column($resultado, 'saldo')), 2);

        return response()->json([
            'modo'        => (string) $request->input('modo', 'detallado'),
            'periodo'     => $periodo,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'empleados'   => $resultado,
            'totalSaldo'  => $totalSaldo,
            'cantidad'    => count($resultado),
        ]);
    }

    /** Resuelve el rango de fechas según el período, respetando el piso del sistema. */
    private function rango(Request $request, int $periodo): array
    {
        $hoy = now()->format('Y-m-d');
        if ($periodo === 2) {
            $f1 = $request->input('fecha1') ?: self::PISO;
            $f2 = $request->input('fecha2') ?: $hoy;
            if ($f1 < self::PISO) $f1 = self::PISO;
            return [$f1, $f2];
        }
        return [self::PISO, $hoy];
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y');
    }
}
