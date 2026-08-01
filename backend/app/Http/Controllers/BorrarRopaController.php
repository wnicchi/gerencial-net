<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BorrarRopaController — Borrar Ropa entregada (ropa_borrar.scx).
 *
 * Lista entregas (entregaropa join ropa) de todos los empleados o de uno, y
 * permite "devolver" elementos: al borrar una entrega devuelve la cantidad al
 * stock (ropa_stock += cantidad) y elimina el registro de entregaropa.
 */
class BorrarRopaController extends Controller
{
    /** @route GET /api/borrar-ropa?empleado= — entregas (todas o de un empleado). */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('entregaropa as e')
            ->join('ropa as r', 'e.ENR_ROP', '=', 'r.ROP_COD');
        if ($cod = (int) $request->query('empleado', 0)) {
            $cuil = trim((string) DB::table('personal')->where('PER_COD', $cod)->value('PER_CUI'));
            if ($cuil === '') {
                return response()->json(['message' => 'El código del empleado no es válido.'], 404);
            }
            $q->where(DB::raw('LTRIM(RTRIM(e.ENR_CUIL))'), $cuil);
        }
        $rows = $q->orderByDesc('e.ENR_FEC')->get([
            'e.ENR_ROP', 'r.ROP_DES', 'e.ENR_CUIL', 'e.ENR_FEC', 'e.ENR_CAN', 'e.ENR_MOT',
            'e.ENR_MAC', 'e.ENR_TAL', 'e.ENR_DEP', 'e.ENR_MAD', 'e.ENR_TAD',
        ])->map(fn ($r) => [
            'codigo'   => (int) $r->ENR_ROP,
            'objeto'   => trim((string) $r->ROP_DES),
            'cuil'     => trim((string) $r->ENR_CUIL),
            'fecha'    => $r->ENR_FEC ? substr((string) $r->ENR_FEC, 0, 10) : '',
            'cantidad' => (int) $r->ENR_CAN,
            'motivo'   => trim((string) $r->ENR_MOT),
            'marca'    => trim((string) $r->ENR_MAD),
            'talle'    => trim((string) $r->ENR_TAD),
            'mac'      => (int) $r->ENR_MAC,
            'tal'      => (int) $r->ENR_TAL,
            'dep'      => (int) $r->ENR_DEP,
        ])->values();
        return response()->json($rows);
    }

    /** @route POST /api/borrar-ropa/devolver — devuelve al stock y borra las entregas seleccionadas. */
    public function devolver(Request $request): JsonResponse
    {
        $d = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.codigo'   => 'required|integer',
            'items.*.cuil'     => 'required|string',
            'items.*.fecha'    => 'required|string',
            'items.*.cantidad' => 'required|integer',
            'items.*.mac'      => 'nullable|integer',
            'items.*.tal'      => 'nullable|integer',
            'items.*.dep'      => 'nullable|integer',
        ], ['items.min' => 'Debe seleccionar el registro que desea eliminar.']);

        $borradas = 0;
        DB::transaction(function () use ($d, &$borradas) {
            foreach ($d['items'] as $it) {
                $cod = (int) $it['codigo'];
                $cuil = trim((string) $it['cuil']);
                $fec = substr((string) $it['fecha'], 0, 10);
                $can = (int) $it['cantidad'];

                // Localiza el registro entregado (mismo criterio que FoxPro).
                $reg = DB::table('entregaropa')
                    ->where('ENR_ROP', $cod)
                    ->where(DB::raw('LTRIM(RTRIM(ENR_CUIL))'), $cuil)
                    ->whereDate('ENR_FEC', $fec)
                    ->where('ENR_CAN', $can)
                    ->first();
                if (!$reg) {
                    continue;
                }

                // Devuelve al stock (sólo si la entrega había descontado stock).
                if ((int) $reg->ENR_STK === 1) {
                    DB::table('ropa_stock')
                        ->where('SEPP_COD', (int) $reg->ENR_ROP)->where('SEPP_MAR', (int) $reg->ENR_MAC)
                        ->where('SEPP_TAL', (int) $reg->ENR_TAL)->where('SEPP_DEP', (int) $reg->ENR_DEP)
                        ->increment('SEPP_STOCK', (int) $reg->ENR_CAN);
                }

                DB::table('entregaropa')
                    ->where('ENR_ROP', $cod)
                    ->where(DB::raw('LTRIM(RTRIM(ENR_CUIL))'), $cuil)
                    ->whereDate('ENR_FEC', $fec)
                    ->where('ENR_CAN', $can)
                    ->where('ENR_MAC', (int) $reg->ENR_MAC)
                    ->where('ENR_TAL', (int) $reg->ENR_TAL)
                    ->delete();
                $borradas++;
            }
        });

        return response()->json(['ok' => true, 'borradas' => $borradas]);
    }
}
