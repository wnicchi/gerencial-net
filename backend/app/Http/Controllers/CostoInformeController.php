<?php

namespace App\Http\Controllers;

use App\Services\CostoLaboralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CostoInformeController — Costos Laborales: Informe General
 * (empleados_costos_informe_gral.scx).
 *
 * Calcula el costo laboral de TODOS los empleados activos para un período,
 * con el costo (sin previsión) y la previsión en columna aparte, agrupando por
 * sector/subsector. En Silcar excluye PER_CAT=55 y en Logística PER_CAT=5.
 */
class CostoInformeController extends Controller
{
    /** @route GET /api/costos-informe?mes=&anio= */
    public function informe(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000|max:2200',
        ]);

        $silcar = config('rrhh.empresa', 'silcar') !== 'logist';
        $catExcluida = $silcar ? 55 : 5;

        // sector/subsector tienen columnas en minúscula; alias para leerlas sin depender del casing.
        $sectores    = DB::table('sector')->selectRaw('sec_des AS des, sec_cod AS cod')->get()->pluck('des', 'cod');
        $subsectores = DB::table('subsector')->selectRaw('sub_des AS des, sub_cod AS cod')->get()->pluck('des', 'cod');

        $svc = new CostoLaboralService();
        $out = [];
        $empleados = DB::table('personal')->where('PER_AOP', 'A')->where('PER_CAT', '!=', $catExcluida)
            ->orderBy('PER_NOM')->get();

        foreach ($empleados as $per) {
            $r = $svc->calcular($per, (int) $d['mes'], (int) $d['anio']);
            $costo = array_sum(array_column($r['rows'], 'importe')) + array_sum(array_column($r['gastos'], 'importe'));
            $prevision = $svc->previsionInforme($r['branch'], $r['bruto']);

            $sec = $sectores[(int) $per->PER_SEC] ?? $per->PER_SED;
            $sub = $subsectores[(int) $per->PER_SUC] ?? $per->PER_SUD;

            $out[] = [
                'sector'      => trim((string) $sec),
                'subsector'   => trim((string) $sub),
                'costo'       => round($costo, 2),
                'prevision'   => round($prevision, 2),
                'nombre'      => mb_strtoupper(trim((string) $per->PER_NOM)),
                'contratista' => (int) $per->PER_CONTRA,
                'legajo'      => trim((string) $per->PER_LEG),
                'codigo'      => (int) $per->PER_COD,
            ];
        }

        return response()->json([
            'empleados'   => $out,
            'costo_total' => round(array_sum(array_column($out, 'costo')), 2),
        ]);
    }
}
