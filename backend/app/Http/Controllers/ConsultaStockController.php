<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ConsultaStockController — Consulta de stock de ropa/EPP (ropa_stock.scx).
 *
 * Reporte de existencias (ropa_stock) con filtros opcionales por depósito, prenda,
 * marca y talle, y opción de mostrar sólo EPP activo. Devuelve las líneas
 * ordenadas por rubro/descripción/marca/talle para armar el informe Detallado
 * o Resumido. El rubro y el flag de activo se traen por subconsulta para no
 * duplicar filas (la tabla de prendas admite códigos repetidos).
 */
class ConsultaStockController extends Controller
{
    /** @route GET /api/consulta-stock/init — combos (depósitos, marcas, talles). */
    public function init(): JsonResponse
    {
        return response()->json([
            'depositos' => DB::table('ropadepo')->orderBy('RDE_DES')->get(['RDE_COD', 'RDE_DES'])
                ->map(fn ($d) => ['cod' => (int) $d->RDE_COD, 'nombre' => trim((string) $d->RDE_DES)])->values(),
            'marcas'    => DB::table('ropamarca')->orderBy('RMA_DES')->get(['RMA_COD', 'RMA_DES'])
                ->map(fn ($m) => ['cod' => (int) $m->RMA_COD, 'nombre' => trim((string) $m->RMA_DES)])->values(),
            'talles'    => DB::table('talles')->orderBy('TAL_DES')->get(['TAL_COD', 'TAL_DES'])
                ->map(fn ($t) => ['cod' => (int) $t->TAL_COD, 'nombre' => trim((string) $t->TAL_DES)])->values(),
        ]);
    }

    /** @route GET /api/consulta-stock?deposito=&ropa=&marca=&talle=&solo_activo= */
    public function consultar(Request $request): JsonResponse
    {
        $depo  = (int) $request->query('deposito', 0);
        $ropa  = (int) $request->query('ropa', 0);
        $marca = (int) $request->query('marca', 0);
        $talle = (int) $request->query('talle', 0);
        $soloActivo = filter_var($request->query('solo_activo', 'true'), FILTER_VALIDATE_BOOL);

        $q = DB::table('ropa_stock as a')->select(
            'a.SEPP_COD', 'a.SEPP_DES', 'a.SEPP_MAR', 'a.SEPP_MAD', 'a.SEPP_TAL', 'a.SEPP_TAD',
            'a.SEPP_DEP', 'a.SEPP_DED', 'a.SEPP_STOCK',
            DB::raw('(SELECT TOP 1 LTRIM(RTRIM(rop_rud)) FROM ropa WHERE ROP_COD = a.SEPP_COD) as rubro'),
        );
        if ($depo > 0)  $q->where('a.SEPP_DEP', $depo);
        if ($ropa > 0)  $q->where('a.SEPP_COD', $ropa);
        if ($marca > 0) $q->where('a.SEPP_MAR', $marca);
        if ($talle > 0) $q->where('a.SEPP_TAL', $talle);
        if ($soloActivo) {
            $q->whereRaw('ISNULL((SELECT TOP 1 ROP_INA FROM ropa WHERE ROP_COD = a.SEPP_COD), 0) = 0');
        }

        $rows = $q->get()->map(fn ($r) => [
            'codigo'    => (int) $r->SEPP_COD,
            'detalle'   => trim((string) $r->SEPP_DES),
            'mcod'      => (int) $r->SEPP_MAR,
            'marca'     => trim((string) $r->SEPP_MAD),
            'tcod'      => (int) $r->SEPP_TAL,
            'talle'     => trim((string) $r->SEPP_TAD),
            'deposito'  => trim((string) $r->SEPP_DED),
            'stock'     => (int) $r->SEPP_STOCK,
            'rubro'     => trim((string) ($r->rubro ?? '')) ?: 'SIN RUBRO',
        ])->sort(function ($x, $y) {
            return [$x['rubro'], $x['detalle'], $x['marca'], $x['talle']]
                <=> [$y['rubro'], $y['detalle'], $y['marca'], $y['talle']];
        })->values();

        return response()->json([
            'items' => $rows,
            'total' => $rows->sum('stock'),
        ]);
    }
}
