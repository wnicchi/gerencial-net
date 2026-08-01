<?php

namespace App\Http\Controllers;

use App\Support\StockRopa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * InventarioRopaController — Carga de inventario de ropa/EPP (ropa_inventario.scx).
 *
 * "TRAER TODO": lista el stock actual de un depósito. "CONFIRMAR": fija el stock
 * de cada línea al valor de "cantidad real" cargado (no suma/resta, asigna).
 */
class InventarioRopaController extends Controller
{
    /** @route GET /api/inventario-ropa/depositos — combo de depósitos. */
    public function depositos(): JsonResponse
    {
        return response()->json(DB::table('ropadepo')->orderBy('RDE_DES')->get(['RDE_COD', 'RDE_DES'])
            ->map(fn ($d) => ['cod' => (int) $d->RDE_COD, 'nombre' => trim((string) $d->RDE_DES)])->values());
    }

    /** @route GET /api/inventario-ropa/stock/{dep} — stock actual del depósito. */
    public function stock(int $dep): JsonResponse
    {
        $rows = DB::table('ropa_stock')->where('SEPP_DEP', $dep)
            ->orderBy('SEPP_DES')->orderBy('SEPP_MAD')->orderBy('SEPP_TAD')
            ->get(['SEPP_COD', 'SEPP_DES', 'SEPP_MAR', 'SEPP_MAD', 'SEPP_TAL', 'SEPP_TAD', 'SEPP_STOCK'])
            ->map(fn ($r) => [
                'codigo'   => (int) $r->SEPP_COD,
                'detalle'  => trim((string) $r->SEPP_DES),
                'mcod'     => (int) $r->SEPP_MAR,
                'marca'    => trim((string) $r->SEPP_MAD),
                'tcod'     => (int) $r->SEPP_TAL,
                'talle'    => trim((string) $r->SEPP_TAD),
                'actual'   => (int) $r->SEPP_STOCK,
                'real'     => (int) $r->SEPP_STOCK,
            ])->values();
        return response()->json($rows);
    }

    /** @route POST /api/inventario-ropa/{dep} — fija el stock real de cada línea. */
    public function confirmar(Request $request, int $dep): JsonResponse
    {
        $d = $request->validate([
            'items'            => 'present|array',
            'items.*.codigo'   => 'required|integer',
            'items.*.detalle'  => 'nullable|string|max:50',
            'items.*.mcod'     => 'nullable|integer',
            'items.*.marca'    => 'nullable|string|max:30',
            'items.*.tcod'     => 'nullable|integer',
            'items.*.talle'    => 'nullable|string|max:20',
            'items.*.real'     => 'required|integer',
        ]);
        if ($dep <= 0 || !($depo = DB::table('ropadepo')->where('RDE_COD', $dep)->first(['RDE_DES']))) {
            return response()->json(['message' => 'No ha seleccionado el depósito donde actualizar inventario.'], 422);
        }
        $depDes = trim((string) $depo->RDE_DES);

        $n = 0;
        DB::transaction(function () use ($d, $dep, $depDes, &$n) {
            foreach ($d['items'] as $it) {
                $cod = (int) $it['codigo']; $mcod = (int) ($it['mcod'] ?? 0); $tcod = (int) ($it['tcod'] ?? 0);
                if ($cod <= 0 || $mcod <= 0 || $tcod <= 0) {
                    continue;
                }
                StockRopa::fijar($cod, (string) ($it['detalle'] ?? ''), $mcod, (string) ($it['marca'] ?? ''),
                    $tcod, (string) ($it['talle'] ?? ''), $dep, $depDes, (int) $it['real']);
                $n++;
            }
        });
        return response()->json(['ok' => true, 'actualizados' => $n]);
    }
}
