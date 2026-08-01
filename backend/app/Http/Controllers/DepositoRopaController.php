<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DepositoRopaController — ABM de Depósitos de Ropa/EPP (tabla ropadepo).
 * Campos: RDE_COD (código incremental), RDE_DES (descripción).
 */
class DepositoRopaController extends Controller
{
    /** @route GET /api/depositos-ropa */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('ropadepo')->orderBy('RDE_DES')->get([
                'RDE_COD as cod',
                DB::raw('LTRIM(RTRIM(RDE_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/depositos-ropa */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $cod = (int) DB::table('ropadepo')->max('RDE_COD') + 1;
        DB::table('ropadepo')->insert(['RDE_COD' => $cod, 'RDE_DES' => mb_strtoupper(trim($d['nombre']))]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/depositos-ropa/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $af = DB::table('ropadepo')->where('RDE_COD', $cod)->update(['RDE_DES' => mb_strtoupper(trim($d['nombre']))]);
        if ($af === 0 && !DB::table('ropadepo')->where('RDE_COD', $cod)->exists()) {
            return response()->json(['message' => 'Depósito no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/depositos-ropa/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $b = DB::table('ropadepo')->where('RDE_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Depósito no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
