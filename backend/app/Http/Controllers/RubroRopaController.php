<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RubroRopaController — ABM de Rubros de Ropa/EPP (tabla roparubro).
 * Campos: RRU_COD (código incremental), RRU_DES (descripción).
 * Se usan para clasificar las prendas/EPP del catálogo (ropa.ROP_RUC).
 */
class RubroRopaController extends Controller
{
    /** @route GET /api/rubros-ropa */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('roparubro')->orderBy('RRU_DES')->get([
                'RRU_COD as cod',
                DB::raw('LTRIM(RTRIM(RRU_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/rubros-ropa */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $cod = (int) DB::table('roparubro')->max('RRU_COD') + 1;
        DB::table('roparubro')->insert(['RRU_COD' => $cod, 'RRU_DES' => mb_strtoupper(trim($d['nombre']))]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/rubros-ropa/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $af = DB::table('roparubro')->where('RRU_COD', $cod)->update(['RRU_DES' => mb_strtoupper(trim($d['nombre']))]);
        if ($af === 0 && !DB::table('roparubro')->where('RRU_COD', $cod)->exists()) {
            return response()->json(['message' => 'Rubro no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/rubros-ropa/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('ropa')->where('ROP_RUC', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay prendas/EPP que usan este rubro.'], 422);
        }
        $b = DB::table('roparubro')->where('RRU_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Rubro no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
