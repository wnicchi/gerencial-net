<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EstadisticaRopaController — Estadística de entregas de ropa/EPP (ropa_estadistica.scx).
 *
 * Suma las entregas (entregaropa) agrupadas por prenda, marca, talle y depósito,
 * con filtros por período (histórico o rango de fechas), depósito, prenda, marca
 * y talle. Devuelve las líneas con totales para el informe "ESTADÍSTICA ENTREGAS".
 */
class EstadisticaRopaController extends Controller
{
    /** @route GET /api/estadistica-ropa/init — combos. */
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

    /** @route GET /api/estadistica-ropa?periodo=&desde=&hasta=&deposito=&ropa=&marca=&talle= */
    public function consultar(Request $request): JsonResponse
    {
        $rango = $request->query('periodo', 'hist') === 'rango';
        $depo  = (int) $request->query('deposito', 0);
        $ropa  = (int) $request->query('ropa', 0);
        $marca = (int) $request->query('marca', 0);
        $talle = (int) $request->query('talle', 0);

        $q = DB::table('entregaropa as a')->selectRaw(
            'a.ENR_ROP, a.ENR_MAC, a.ENR_TAL, a.ENR_DEP, '
            . 'MAX(a.ENR_MAD) as marca, MAX(a.ENR_TAD) as talle, MAX(a.ENR_DED) as deposito, '
            . 'SUM(a.ENR_CAN) as total'
        )->groupBy('a.ENR_ROP', 'a.ENR_MAC', 'a.ENR_TAL', 'a.ENR_DEP')->havingRaw('SUM(a.ENR_CAN) > 0');

        if ($depo > 0)  $q->where('a.ENR_DEP', $depo);
        if ($ropa > 0)  $q->where('a.ENR_ROP', $ropa);
        if ($marca > 0) $q->where('a.ENR_MAC', $marca);
        if ($talle > 0) $q->where('a.ENR_TAL', $talle);
        if ($rango) {
            $desde = substr((string) $request->query('desde', '1900-01-01'), 0, 10);
            $hasta = substr((string) $request->query('hasta', '2999-12-31'), 0, 10);
            $q->whereBetween('a.ENR_FEC', [$desde . ' 00:00:00', $hasta . ' 23:59:59']);
        }

        // Mapa de prenda → descripción y rubro (agrupado para evitar duplicados de código).
        $ropaMap = DB::table('ropa')->selectRaw('ROP_COD, MAX(ROP_DES) as des, MAX(ROP_RUD) as rud')
            ->groupBy('ROP_COD')->get()->keyBy('ROP_COD');

        $rows = collect($q->get())->map(function ($r) use ($ropaMap) {
            $rm = $ropaMap->get($r->ENR_ROP);
            return [
                'codigo'       => (int) $r->ENR_ROP,
                'nombre'       => $rm ? trim((string) $rm->des) : '',
                'marca'        => trim((string) $r->marca),
                'talle'        => trim((string) $r->talle),
                'deposito'     => trim((string) $r->deposito),
                'indumentaria' => $rm ? (trim((string) $rm->rud) ?: 'SIN RUBRO') : 'SIN RUBRO',
                'total'        => (int) $r->total,
            ];
        })->filter(fn ($x) => $x['total'] > 0 && $x['marca'] !== '')
          ->sort(fn ($a, $b) => [$a['deposito'], $a['indumentaria'], $a['nombre'], $a['talle'], $a['marca']]
                            <=> [$b['deposito'], $b['indumentaria'], $b['nombre'], $b['talle'], $b['marca']])
          ->values();

        return response()->json(['items' => $rows, 'total' => $rows->sum('total')]);
    }
}
