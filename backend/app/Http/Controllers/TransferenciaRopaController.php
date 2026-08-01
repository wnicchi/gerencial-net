<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use App\Support\StockRopa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TransferenciaRopaController — Transferencia de ropa entre depósitos
 * (ropa_transferencias.scx).
 *
 * Por cada ítem: registra egreso (ropa_movi "E") del depósito origen y resta
 * su stock; registra ingreso (ropa_movi "I") al depósito destino y suma su
 * stock. Devuelve el payload para el remito de transferencia.
 */
class TransferenciaRopaController extends Controller
{
    /** @route GET /api/transferencia-ropa/init — combos (depósitos, marcas, talles). */
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

    /** @route GET /api/transferencia-ropa/ropa/{cod} — descripción de una prenda. */
    public function ropa(int $cod): JsonResponse
    {
        $r = DB::table('ropa')->where('ROP_COD', $cod)->first(['ROP_COD', 'ROP_DES']);
        if (!$r) {
            return response()->json(['message' => 'Código de prenda/EPP inexistente.'], 404);
        }
        return response()->json(['cod' => (int) $r->ROP_COD, 'des' => trim((string) $r->ROP_DES)]);
    }

    /** @route POST /api/transferencia-ropa — registra la transferencia. */
    public function transferir(Request $request): JsonResponse
    {
        $d = $request->validate([
            'origen'           => 'required|integer|min:1',
            'destino'          => 'required|integer|min:1',
            'items'            => 'present|array',
            'items.*.rcod'     => 'required|integer',
            'items.*.rdes'     => 'nullable|string|max:50',
            'items.*.mcod'     => 'nullable|integer',
            'items.*.mdes'     => 'nullable|string|max:30',
            'items.*.tcod'     => 'nullable|integer',
            'items.*.tdes'     => 'nullable|string|max:20',
            'items.*.cantidad' => 'nullable|integer',
            'items.*.fecha'    => 'nullable|date',
        ], [
            'origen.min'  => 'No ha seleccionado el depósito origen.',
            'destino.min' => 'No ha seleccionado el depósito destino.',
        ]);

        if ((int) $d['origen'] === (int) $d['destino']) {
            return response()->json(['message' => 'Los depósitos de origen y destino no pueden ser iguales.'], 422);
        }
        $ori = DB::table('ropadepo')->where('RDE_COD', $d['origen'])->first(['RDE_COD', 'RDE_DES']);
        $des = DB::table('ropadepo')->where('RDE_COD', $d['destino'])->first(['RDE_COD', 'RDE_DES']);
        if (!$ori || !$des) {
            return response()->json(['message' => 'Depósito no válido.'], 422);
        }

        $validos = array_values(array_filter($d['items'], fn ($it) => (int) ($it['rcod'] ?? 0) > 0
            && trim((string) ($it['rdes'] ?? '')) !== '' && (int) ($it['cantidad'] ?? 0) > 0));
        if (count($validos) === 0) {
            return response()->json(['message' => 'No ha ingresado ningún ítem válido, verifique códigos y cantidades por favor.'], 422);
        }

        $codOri = (int) $ori->RDE_COD; $desOri = trim((string) $ori->RDE_DES);
        $codDes = (int) $des->RDE_COD; $desDes = trim((string) $des->RDE_DES);
        $ahora = now()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($validos, $codOri, $desOri, $codDes, $desDes, $ahora) {
            foreach ($validos as $it) {
                $rcod = (int) $it['rcod']; $rdes = mb_substr(trim((string) $it['rdes']), 0, 50);
                $mcod = (int) ($it['mcod'] ?? 0); $mdes = mb_substr(trim((string) ($it['mdes'] ?? '')), 0, 30);
                $tcod = (int) ($it['tcod'] ?? 0); $tdes = mb_substr(trim((string) ($it['tdes'] ?? '')), 0, 20);
                $can = (int) $it['cantidad'];

                // Egreso del origen
                DB::table('ropa_movi')->insert(Registro::completar('ropa_movi', [
                    'RST_IOE' => 'E', 'RST_ROC' => $rcod, 'RST_ROD' => $rdes, 'RST_MAC' => $mcod, 'RST_MAD' => $mdes,
                    'RST_TAL' => $tcod, 'RST_TAD' => $tdes, 'RST_CAN' => $can, 'RST_FEC' => $ahora,
                    'RST_DET' => 'Transferencia a ' . $desDes, 'RST_DEP' => $codOri, 'RST_DES' => $desOri,
                ]));
                StockRopa::sumar($rcod, $rdes, $mcod, $mdes, $tcod, $tdes, $codOri, $desOri, -$can);

                // Ingreso al destino
                DB::table('ropa_movi')->insert(Registro::completar('ropa_movi', [
                    'RST_IOE' => 'I', 'RST_ROC' => $rcod, 'RST_ROD' => $rdes, 'RST_MAC' => $mcod, 'RST_MAD' => $mdes,
                    'RST_TAL' => $tcod, 'RST_TAD' => $tdes, 'RST_CAN' => $can, 'RST_FEC' => $ahora,
                    'RST_DET' => 'Recibo del deposito ' . $desOri, 'RST_DEP' => $codDes, 'RST_DES' => $desDes,
                ]));
                StockRopa::sumar($rcod, $rdes, $mcod, $mdes, $tcod, $tdes, $codDes, $desDes, $can);
            }
        });

        return response()->json([
            'ok'      => true,
            'origen'  => $desOri,
            'destino' => $desDes,
            'items'   => array_map(fn ($it) => [
                'codigo'   => (int) $it['rcod'],
                'detalle'  => trim((string) ($it['rdes'] ?? '')),
                'marca'    => trim((string) ($it['mdes'] ?? '')),
                'talle'    => trim((string) ($it['tdes'] ?? '')),
                'cantidad' => (int) ($it['cantidad'] ?? 0),
                'fecha'    => !empty($it['fecha']) ? substr((string) $it['fecha'], 0, 10) : now()->format('Y-m-d'),
            ], $validos),
        ]);
    }
}
