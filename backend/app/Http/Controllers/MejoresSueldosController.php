<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MejoresSueldosController — Mejores Sueldos / Promedio (sueldos_mejor_precios).
 *
 *  - mejorBruto: por empleado, el MEJOR sueldo BRUTO (mayor SUM(LIT_HAB) de una liquidación) y su período.
 *  - mejorSueldo: por empleado, el MEJOR neto de un período (SUM(LIT_HAB-LIT_DED) por período), solo de los
 *    tipos de liquidación seleccionados.
 *  - promedio: por empleado, el PROMEDIO de los netos por período (histórico: /cantidad de meses con datos;
 *    rango: /cantidad de meses del rango).
 *
 * Filtros comunes: empresa, contratista, lugar, convenio, categoría, empleado, banco + período (rango fechas).
 */
class MejoresSueldosController extends Controller
{
    /** @route GET /api/mejores-sueldos/bruto?...filtros&periodo=&fecha1=&fecha2= */
    public function mejorBruto(Request $request): JsonResponse
    {
        $q = $this->base($request)
            ->groupBy('l.LIQ_NRO', 'l.LIQ_COD', 'p.PER_LEG', 'l.LIQ_CUI', 'l.LIQ_DEP', 'l.LIQ_MES', 'l.LIQ_ANO')
            ->selectRaw('l.LIQ_COD, p.PER_LEG, l.LIQ_CUI, l.LIQ_DEP, l.LIQ_MES, l.LIQ_ANO, SUM(li.LIT_HAB) as bruto');

        $mejorPorEmp = [];
        foreach ($q->get() as $r) {
            $cod = (int) $r->LIQ_COD; $bruto = round((float) $r->bruto, 2);
            if (!isset($mejorPorEmp[$cod]) || $bruto > $mejorPorEmp[$cod]['mejor_bruto']) {
                $mejorPorEmp[$cod] = [
                    'codigo' => $cod, 'legajo' => (int) $r->PER_LEG, 'cuit' => trim((string) $r->LIQ_CUI),
                    'nombre' => trim((string) $r->LIQ_DEP), 'mejor_bruto' => $bruto, 'mes' => (int) $r->LIQ_MES, 'anio' => (int) $r->LIQ_ANO,
                ];
            }
        }
        $rows = array_values($mejorPorEmp);
        usort($rows, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return response()->json($rows);
    }

    /** @route GET /api/mejores-sueldos/sueldo?...filtros&periodo=&fecha1=&fecha2=&tipos[]=  (modo mejor) */
    public function mejorSueldo(Request $request): JsonResponse
    {
        return response()->json($this->porPeriodo($request, 'mejor'));
    }

    /** @route GET /api/mejores-sueldos/promedio?...filtros&periodo=&fecha1=&fecha2=&tipos[]= */
    public function promedio(Request $request): JsonResponse
    {
        return response()->json($this->porPeriodo($request, 'promedio'));
    }

    /** Suma por (cuil, período) de los tipos seleccionados, luego mejor o promedio por empleado. */
    private function porPeriodo(Request $request, string $modo): array
    {
        $tipos = array_map('intval', (array) $request->input('tipos', []));
        $q = $this->base($request);
        if ($tipos) $q->whereIn('l.LIQ_TIP', $tipos);

        // Se agrupa por el CUIL del EMPLEADO (personal.PER_CUI), no por LIQUIDAC.LIQ_CUI (suele venir vacío).
        $q->groupBy('p.PER_CUI', 'p.PER_NOM', 'li.LIT_PER')
            ->selectRaw('p.PER_CUI as cuil, p.PER_NOM as nombre, li.LIT_PER, MIN(li.LIT_MES) as mes, MIN(li.LIT_ANO) as anio, MIN(li.LIT_FEC) as fecha, SUM(li.LIT_HAB - li.LIT_DED) as monto');

        // Acumular por empleado (cuil del empleado)
        $byEmp = [];
        foreach ($q->get() as $r) {
            $cuil = preg_replace('/\D/', '', (string) $r->cuil) ?: '0'; $monto = round((float) $r->monto, 2);
            if (!isset($byEmp[$cuil])) {
                $byEmp[$cuil] = ['cuil' => $cuil, 'nombre' => trim((string) $r->nombre), 'mejor' => $monto, 'mes' => (int) $r->mes, 'anio' => (int) $r->anio, 'fecha' => $this->fecha($r->fecha), 'suma' => 0, 'cant' => 0];
            }
            $byEmp[$cuil]['suma'] += $monto; $byEmp[$cuil]['cant']++;
            if ($monto > $byEmp[$cuil]['mejor']) {
                $byEmp[$cuil]['mejor'] = $monto; $byEmp[$cuil]['mes'] = (int) $r->mes; $byEmp[$cuil]['anio'] = (int) $r->anio; $byEmp[$cuil]['fecha'] = $this->fecha($r->fecha);
            }
        }

        // Meses del rango (para promedio en modo rango)
        $mesesRango = 1;
        if ((int) $request->input('periodo', 0) === 1 && ($f1 = $request->input('fecha1')) && ($f2 = $request->input('fecha2'))) {
            $dias = \Carbon\Carbon::parse($f1)->diffInDays(\Carbon\Carbon::parse($f2)) + 1;
            $mesesRango = max(1, (int) floor($dias / 30));
        }
        $rango = (int) $request->input('periodo', 0) === 1;

        $rows = [];
        foreach ($byEmp as $e) {
            if ($modo === 'mejor') {
                $rows[] = ['cuil' => $e['cuil'], 'nombre' => $e['nombre'], 'mes' => $e['mes'], 'anio' => $e['anio'], 'fecha' => $e['fecha'], 'monto' => $e['mejor']];
            } else {
                $div = $rango ? $mesesRango : max(1, $e['cant']);
                $rows[] = ['cuil' => $e['cuil'], 'nombre' => $e['nombre'], 'mes' => $e['mes'], 'anio' => $e['anio'], 'fecha' => $e['fecha'], 'promedio' => round($e['suma'] / $div, 2)];
            }
        }
        usort($rows, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return $rows;
    }

    /** Query base con joins y filtros de empleado + período. */
    private function base(Request $request)
    {
        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD');
        foreach ([
            'empresa' => 'p.PER_EMP', 'contratista' => 'p.PER_CONTRA', 'lugar' => 'p.PER_LUGAR', 'convenio' => 'p.PER_CON',
            'categoria' => 'p.PER_CAT', 'empleado' => 'p.PER_COD', 'banco' => 'p.PER_BAN',
        ] as $param => $col) {
            if ($v = (int) $request->input($param, 0)) $q->where($col, $v);
        }
        if ((int) $request->input('periodo', 0) === 1) {
            if ($f1 = $request->input('fecha1')) $q->whereDate('l.LIQ_FEC', '>=', $f1);
            if ($f2 = $request->input('fecha2')) $q->whereDate('l.LIQ_FEC', '<=', $f2);
        }
        return $q;
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y');
    }
}
