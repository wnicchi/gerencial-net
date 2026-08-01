<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ComedorController — ABM de Comedores (tabla comedor).
 * Campos: COME_COD (código incremental), COME_DES (descripción).
 */
class ComedorController extends Controller
{
    /** @route GET /api/comedores */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('comedor')->orderBy('COME_DES')->get([
                'COME_COD as cod',
                DB::raw('LTRIM(RTRIM(COME_DES)) as descripcion'),
            ])
        );
    }

    /** @route POST /api/comedores */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        $cod = (int) DB::table('comedor')->max('COME_COD') + 1;
        DB::table('comedor')->insert(['COME_COD' => $cod, 'COME_DES' => trim($d['descripcion'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/comedores/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        $afectados = DB::table('comedor')->where('COME_COD', $cod)->update(['COME_DES' => trim($d['descripcion'])]);
        if ($afectados === 0 && !DB::table('comedor')->where('COME_COD', $cod)->exists()) {
            return response()->json(['error' => 'Comedor no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/comedores/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_COMN', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a este comedor.'], 422);
        }
        $borrados = DB::table('comedor')->where('COME_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Comedor no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
