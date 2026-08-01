<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ResumenLiquidacionController — Exportar Resumen Liquidaciones (sueldos_informes).
 *
 * Arma una planilla "pivote": una fila por empleado y una columna por cada tipo de liquidación
 * presente en el período, con el total (SUM(LIT_HAB - LIT_DED)) de cada tipo. El tipo "ADELANTOS"
 * se resta. La última columna es el total de la fila.
 *
 * NOTA: el FoxPro además arma una sección de SAC leyendo la base de GESTIÓN (PAGOS_AUTORIZAR +
 * tablas de ítems por banco). Esa parte está marcada como experimental en el FoxPro
 * ("TESTEO CON LA 2 ... luego cambiar a la 11") y NO se replica acá; si se necesita, se agrega aparte.
 */
class ResumenLiquidacionController extends Controller
{
    /** @route GET /api/resumen-liquidaciones?fecha1=&fecha2=&empresa=&contratista=&lugar=&convenio=&categoria=&banco= */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha1' => 'required|date', 'fecha2' => 'required|date']);

        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->whereDate('l.LIQ_FEC', '>=', $d['fecha1'])->whereDate('l.LIQ_FEC', '<=', $d['fecha2']);
        foreach ([
            'empresa' => 'p.PER_EMP', 'contratista' => 'p.PER_CONTRA', 'lugar' => 'p.PER_LUGAR', 'convenio' => 'p.PER_CON',
            'categoria' => 'p.PER_CAT', 'banco' => 'p.PER_BAN',
        ] as $param => $col) {
            if ($v = (int) $request->input($param, 0)) $q->where($col, $v);
        }

        // Suma por empleado + tipo de liquidación
        $sumas = $q->groupBy('p.PER_LEG', 'p.PER_NOM', 'l.LIQ_TID')
            ->selectRaw('p.PER_LEG, p.PER_NOM, LTRIM(RTRIM(l.LIQ_TID)) as tipo, SUM(li.LIT_HAB - li.LIT_DED) as monto')
            ->get();

        // Columnas (tipos distintos, ordenados)
        $columnas = $sumas->pluck('tipo')->map(fn ($t) => trim((string) $t))->unique()->filter()->sort()->values()->all();
        $idx = array_flip($columnas);

        // Filas por empleado (legajo)
        $filas = [];
        foreach ($sumas as $s) {
            $leg = (int) $s->PER_LEG; $tipo = trim((string) $s->tipo);
            $monto = round((float) $s->monto, 2) * (mb_strtoupper($tipo) === 'ADELANTOS' ? -1 : 1);
            if (!isset($filas[$leg])) {
                $filas[$leg] = ['legajo' => $leg, 'nombre' => trim((string) $s->PER_NOM), 'valores' => array_fill(0, count($columnas), 0.0)];
            }
            if (isset($idx[$tipo])) $filas[$leg]['valores'][$idx[$tipo]] += $monto;
        }

        $out = [];
        foreach ($filas as $f) {
            $f['valores'] = array_map(fn ($v) => round($v, 2), $f['valores']);
            $f['total'] = round(array_sum($f['valores']), 2);
            $out[] = $f;
        }
        usort($out, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return response()->json(['columnas' => $columnas, 'filas' => $out]);
    }
}
