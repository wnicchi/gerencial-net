<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MarcaRopaController — ABM de Marcas de Ropa/EPP (tabla ropamarca).
 * Campos: RMA_COD (código incremental), RMA_DES (descripción).
 */
class MarcaRopaController extends Controller
{
    /** @route GET /api/marcas-ropa */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('ropamarca')->orderBy('RMA_DES')->get([
                'RMA_COD as cod',
                DB::raw('LTRIM(RTRIM(RMA_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/marcas-ropa */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:30']);
        $cod = (int) DB::table('ropamarca')->max('RMA_COD') + 1;
        DB::table('ropamarca')->insert(['RMA_COD' => $cod, 'RMA_DES' => mb_strtoupper(trim($d['nombre']))]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/marcas-ropa/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:30']);
        $af = DB::table('ropamarca')->where('RMA_COD', $cod)->update(['RMA_DES' => mb_strtoupper(trim($d['nombre']))]);
        if ($af === 0 && !DB::table('ropamarca')->where('RMA_COD', $cod)->exists()) {
            return response()->json(['message' => 'Marca no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/marcas-ropa/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $b = DB::table('ropamarca')->where('RMA_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Marca no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
