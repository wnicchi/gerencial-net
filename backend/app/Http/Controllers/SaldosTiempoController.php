<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * SaldosTiempoController — Estadísticas › Comparativo Saldos en el Tiempo.
 *
 * Migra estadistica_comparativa_saldos_tiempo.scx. LEE directamente la tabla
 * precalculada SALDOS_TIEMPO (no hace cálculo): para cada mes/año, el saldo de
 * cuenta corriente abierto por tramo de antigüedad. El gráfico (barra apilada al
 * 100%) muestra la PROPORCIÓN de cada tramo = 100 × tramo / total.
 *
 * SOLO LECTURA.
 */
class SaldosTiempoController extends Controller
{
    /** GET /api/estadisticas/comparativo-saldos — filas de SALDOS_TIEMPO ordenadas. */
    public function index(): JsonResponse
    {
        $rows = DB::connection('gestion')->table('SALDOS_TIEMPO')
            ->orderBy('SAL_ANO')->orderBy('SAL_MES')->get();

        $filas = $rows->map(fn ($r) => [
            'mes'   => (int) $r->SAL_MES,
            'anio'  => (int) $r->SAL_ANO,
            'm360'  => (float) $r->SAL_M360,   // más de 1 año
            'm180'  => (float) $r->SAL_M180,   // de 6 meses a 1 año
            'm120'  => (float) $r->SAL_M120,   // de 4 a 6 meses
            'm090'  => (float) $r->SAL_M090,   // de 91 a 120 días
            'm060'  => (float) $r->SAL_M060,   // de 61 a 90 días
            'm030'  => (float) $r->SAL_M030,   // de 31 a 60 días
            'u030'  => (float) $r->SAL_U030,   // hasta 30 días
            'total' => (float) $r->SAL_TOT,
        ])->values();

        return response()->json(['filas' => $filas]);
    }
}
