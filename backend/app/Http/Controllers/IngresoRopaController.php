<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Support\StockRopa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * IngresoRopaController — Ingreso de Ropa/EPP a stock (ropa_ingreso.scx).
 *
 * Suma stock a un depósito: por cada ítem inserta movimiento de ingreso
 * (ropa_movi "I") y aumenta ropa_stock (+cantidad). Requiere código, detalle,
 * marca, talle y cantidad > 0.
 */
class IngresoRopaController extends Controller
{
    /** @route GET /api/ingreso-ropa/init — combos (depósitos, marcas, talles). */
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

    /** @route GET /api/ingreso-ropa/ropa/{cod} — descripción de una prenda. */
    public function ropa(int $cod): JsonResponse
    {
        $r = DB::table('ropa')->where('ROP_COD', $cod)->first(['ROP_COD', 'ROP_DES']);
        if (!$r) {
            return response()->json(['message' => 'Código de prenda/EPP inexistente.'], 404);
        }
        return response()->json(['cod' => (int) $r->ROP_COD, 'des' => trim((string) $r->ROP_DES)]);
    }

    /** @route POST /api/ingreso-ropa — registra el ingreso a stock. */
    public function ingresar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'deposito'         => 'required|integer|min:1',
            'motivo'           => 'nullable|string|max:100',
            'items'            => 'present|array',
            'items.*.rcod'     => 'required|integer',
            'items.*.rdes'     => 'nullable|string|max:50',
            'items.*.mcod'     => 'nullable|integer',
            'items.*.mdes'     => 'nullable|string|max:30',
            'items.*.tcod'     => 'nullable|integer',
            'items.*.tdes'     => 'nullable|string|max:20',
            'items.*.cantidad' => 'nullable|integer',
            'items.*.fecha'    => 'nullable|date',
        ], ['deposito.min' => 'No ha seleccionado el depósito donde ingresarán los elementos.']);

        $depo = DB::table('ropadepo')->where('RDE_COD', $d['deposito'])->first(['RDE_COD', 'RDE_DES']);
        if (!$depo) {
            return response()->json(['message' => 'Depósito no válido.'], 422);
        }

        // Válidos: código, detalle, marca, talle y cantidad > 0.
        $validos = array_values(array_filter($d['items'], fn ($it) => (int) ($it['rcod'] ?? 0) > 0
            && trim((string) ($it['rdes'] ?? '')) !== '' && (int) ($it['mcod'] ?? 0) > 0
            && (int) ($it['tcod'] ?? 0) > 0 && (int) ($it['cantidad'] ?? 0) > 0));
        if (count($validos) === 0) {
            return response()->json(['message' => 'No ha ingresado ningún ítem válido, verifique códigos y cantidades por favor.'], 422);
        }

        $motivo = mb_strtoupper(trim((string) ($d['motivo'] ?? '')));
        $depCod = (int) $depo->RDE_COD;
        $depDes = trim((string) $depo->RDE_DES);

        DB::transaction(function () use ($validos, $depCod, $depDes, $motivo) {
            foreach ($validos as $it) {
                $rcod = (int) $it['rcod']; $rdes = mb_substr(trim((string) $it['rdes']), 0, 50);
                $mcod = (int) $it['mcod']; $mdes = mb_substr(trim((string) ($it['mdes'] ?? '')), 0, 30);
                $tcod = (int) $it['tcod']; $tdes = mb_substr(trim((string) ($it['tdes'] ?? '')), 0, 20);
                $can = (int) $it['cantidad'];
                $fec = !empty($it['fecha']) ? substr((string) $it['fecha'], 0, 10) : now()->format('Y-m-d');

                DB::table('ropa_movi')->insert(Registro::completar('ropa_movi', [
                    'RST_IOE' => 'I', 'RST_ROC' => $rcod, 'RST_ROD' => $rdes,
                    'RST_MAC' => $mcod, 'RST_MAD' => $mdes, 'RST_TAL' => $tcod, 'RST_TAD' => $tdes,
                    'RST_CAN' => $can, 'RST_FEC' => $fec . ' 00:00:00', 'RST_DET' => $motivo,
                    'RST_DEP' => $depCod, 'RST_DES' => $depDes,
                ]));

                StockRopa::sumar($rcod, $rdes, $mcod, $mdes, $tcod, $tdes, $depCod, $depDes, $can);
            }
        });

        return response()->json(['ok' => true, 'ingresados' => count($validos)]);
    }
}
