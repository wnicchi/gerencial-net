<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ComparativaLiquidacionController — Comparativa Liquidaciones vs Neto (sueldos_comparar_liq_neto).
 *
 * Por mes/año compara, por empleado, el total LIQUIDADO (SUM(LIT_HAB - LIT_DED) de todas sus
 * liquidaciones del período) contra el NETO de la ficha (personal.PER_SUE), mostrando la diferencia.
 * Filtros opcionales: empresa, contratista, lugar, convenio, categoría y banco.
 */
class ComparativaLiquidacionController extends Controller
{
    /** @route GET /api/comparativa-liquidaciones?mes=&anio=&empresa=&contratista=&lugar=&convenio=&categoria=&banco= */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate(['mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer']);
        $mes = (int) $d['mes']; $anio = (int) $d['anio'];

        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->where('l.LIQ_MES', $mes)->where('l.LIQ_ANO', $anio);

        foreach ([
            'empresa' => 'p.PER_EMP', 'contratista' => 'p.PER_CONTRA', 'lugar' => 'p.PER_LUGAR',
            'convenio' => 'p.PER_CON', 'categoria' => 'p.PER_CAT', 'banco' => 'p.PER_BAN',
        ] as $param => $col) {
            if ($v = (int) $request->input($param, 0)) $q->where($col, $v);
        }

        $rows = $q->groupBy('p.PER_LEG', 'p.PER_NOM', 'p.PER_SUE')
            ->orderBy('p.PER_NOM')
            ->get([
                'p.PER_LEG', 'p.PER_NOM', 'p.PER_SUE',
                DB::raw('SUM(li.LIT_HAB - li.LIT_DED) as liquidado'),
            ])->map(function ($r) {
                $liq = round((float) $r->liquidado, 2);
                $neto = round((float) $r->PER_SUE, 2);
                return [
                    'legajo'     => (int) $r->PER_LEG,
                    'nombre'     => trim((string) $r->PER_NOM),
                    'liquidado'  => $liq,
                    'neto'       => $neto,
                    'diferencia' => round($liq - $neto, 2),
                ];
            });

        return response()->json(['filas' => $rows->values(), 'mes' => $mes, 'anio' => $anio]);
    }
}
