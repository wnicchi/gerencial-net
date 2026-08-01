<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SueldosListadosController — Listados de Liquidaciones (sueldos_listados).
 *
 * Informe de liquidaciones (LIQUIDAC + LIQ_ITE + personal) con muchos filtros. Devuelve las
 * liquidaciones agrupadas por número. El frontend arma 3 salidas: Completo (con conceptos),
 * Resumido (total por liquidación) y Sueldos Bruto (solo haberes).
 */
class SueldosListadosController extends Controller
{
    /**
     * @route GET /api/sueldos-listados?empresa=&contratista=&lugar=&convenio=&categoria=&empleado=&banco=
     *   &tipo=&concepto=&periodo=0|1&fecha1=&fecha2=&pagado=0|1&pag1=&pag2=&modo=completo|resumido|bruto
     */
    public function index(Request $request): JsonResponse
    {
        $modo = in_array($request->input('modo'), ['resumido', 'bruto']) ? $request->input('modo') : 'completo';

        // Cabeceras de liquidaciones que cumplen filtros de empleado + período + pagado + tipo
        $q = DB::table('LIQUIDAC as l')->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD');
        foreach ([
            'empresa' => 'p.PER_EMP', 'contratista' => 'p.PER_CONTRA', 'lugar' => 'p.PER_LUGAR', 'convenio' => 'p.PER_CON',
            'categoria' => 'p.PER_CAT', 'empleado' => 'p.PER_COD', 'banco' => 'p.PER_BAN',
        ] as $param => $col) {
            if ($v = (int) $request->input($param, 0)) $q->where($col, $v);
        }
        if ($t = (int) $request->input('tipo', 0)) $q->where('l.LIQ_TIP', $t);
        if ((int) $request->input('periodo', 0) === 1) {
            if ($f1 = $request->input('fecha1')) $q->whereDate('l.LIQ_FEC', '>=', $f1);
            if ($f2 = $request->input('fecha2')) $q->whereDate('l.LIQ_FEC', '<=', $f2);
        }
        if ((int) $request->input('pagado', 0) === 1) {
            if ($p1 = $request->input('pag1')) $q->whereDate('l.LIQ_PAG', '>=', $p1);
            if ($p2 = $request->input('pag2')) $q->whereDate('l.LIQ_PAG', '<=', $p2);
        }

        $cabeceras = $q->orderBy('p.PER_NOM')->orderBy('l.LIQ_NRO')
            ->get(['l.LIQ_NRO', 'l.LIQ_COD', 'p.PER_LEG', 'p.PER_NOM', 'l.LIQ_TIP', 'l.LIQ_TID', 'l.LIQ_MES', 'l.LIQ_ANO', 'l.LIQ_CUI', 'l.LIQ_ING', 'l.LIQ_EMP']);

        $nros = $cabeceras->pluck('LIQ_NRO')->map(fn ($v) => (int) $v)->all();
        if (!$nros) return response()->json(['liquidaciones' => [], 'total_general' => 0, 'modo' => $modo]);

        $concepto = (int) $request->input('concepto', 0);

        // Ítems agrupados por LIQ_NRO (con filtro de concepto opcional)
        $itemsByNro = []; $totalByNro = [];
        if ($modo === 'resumido') {
            $iq = DB::table('LIQ_ITE')->whereIn('LIT_NRO', $nros);
            if ($concepto) $iq->where('LIT_COD', $concepto);
            foreach ($iq->groupBy('LIT_NRO')->selectRaw('LIT_NRO, SUM(LIT_HAB - LIT_DED) as total')->get() as $r) {
                $totalByNro[(int) $r->LIT_NRO] = round((float) $r->total, 2);
            }
        } else {
            $iq = DB::table('LIQ_ITE')->whereIn('LIT_NRO', $nros);
            if ($concepto) $iq->where('LIT_COD', $concepto);
            foreach ($iq->orderBy('LIT_COD')->get(['LIT_NRO', 'LIT_COD', 'LIT_DES', 'LIT_CAN', 'LIT_HAB', 'LIT_DED']) as $it) {
                $nro = (int) $it->LIT_NRO;
                $itemsByNro[$nro][] = [
                    'codigo' => (int) $it->LIT_COD, 'detalle' => trim((string) $it->LIT_DES),
                    'cantidad' => (float) $it->LIT_CAN, 'haber' => (float) $it->LIT_HAB, 'deduccion' => (float) $it->LIT_DED,
                ];
                $totalByNro[$nro] = round(($totalByNro[$nro] ?? 0) + ((float) $it->LIT_HAB - (float) $it->LIT_DED), 2);
            }
        }

        $liqs = []; $totalGeneral = 0;
        foreach ($cabeceras as $c) {
            $nro = (int) $c->LIQ_NRO;
            // si filtró por concepto y esta liquidación no tiene ese concepto, se omite
            if ($concepto && !isset($totalByNro[$nro])) continue;
            $total = $totalByNro[$nro] ?? 0;
            $totalGeneral += $total;
            $ing = \Carbon\Carbon::parse($c->LIQ_ING);
            $liqs[] = [
                'nro' => $nro, 'codigo' => (int) $c->LIQ_COD, 'legajo' => (int) $c->PER_LEG, 'nombre' => trim((string) $c->PER_NOM),
                'cuil' => trim((string) $c->LIQ_CUI), 'ingreso' => $ing->year <= 1900 ? '' : $ing->format('d/m/Y'), 'empresa' => (int) $c->LIQ_EMP,
                'tipo' => trim((string) $c->LIQ_TID), 'mes' => (int) $c->LIQ_MES, 'anio' => (int) $c->LIQ_ANO,
                'items' => $itemsByNro[$nro] ?? [], 'total' => $total,
            ];
        }

        return response()->json(['liquidaciones' => $liqs, 'total_general' => round($totalGeneral, 2), 'modo' => $modo]);
    }
}
