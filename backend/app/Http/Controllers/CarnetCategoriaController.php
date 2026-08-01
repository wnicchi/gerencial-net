<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CarnetCategoriaController — ABM de Categorías de Carnet (tabla carn_cat).
 * Campos: CRT_COD (código alfanumérico ingresado por el usuario, clave), CRT_DES (nombre).
 */
class CarnetCategoriaController extends Controller
{
    /** @route GET /api/carnet-categorias */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('carn_cat')->orderBy('CRT_COD')->get([
                DB::raw('LTRIM(RTRIM(CRT_COD)) as cod'),
                DB::raw('LTRIM(RTRIM(CRT_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/carnet-categorias */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['cod' => 'required|string|max:4', 'nombre' => 'required|string|max:100']);
        $cod = strtoupper(trim($d['cod']));

        if (DB::table('carn_cat')->whereRaw('UPPER(LTRIM(RTRIM(CRT_COD))) = ?', [$cod])->exists()) {
            return response()->json(['message' => "Ya existe una categoría de carnet con el código \"{$cod}\"."], 422);
        }

        DB::table('carn_cat')->insert(['CRT_COD' => $cod, 'CRT_DES' => trim($d['nombre'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/carnet-categorias/{cod} */
    public function update(Request $request, string $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:100']);
        $afectados = DB::table('carn_cat')
            ->whereRaw('UPPER(LTRIM(RTRIM(CRT_COD))) = ?', [strtoupper(trim($cod))])
            ->update(['CRT_DES' => trim($d['nombre'])]);

        if ($afectados === 0 &&
            !DB::table('carn_cat')->whereRaw('UPPER(LTRIM(RTRIM(CRT_COD))) = ?', [strtoupper(trim($cod))])->exists()) {
            return response()->json(['error' => 'Categoría de carnet no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/carnet-categorias/{cod} */
    public function destroy(string $cod): JsonResponse
    {
        $borrados = DB::table('carn_cat')
            ->whereRaw('UPPER(LTRIM(RTRIM(CRT_COD))) = ?', [strtoupper(trim($cod))])->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Categoría de carnet no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
