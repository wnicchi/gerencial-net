<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LugarController — ABM de Lugares (tabla lugar).
 * Campos: LUG_COD (código incremental), LUG_NOM (nombre).
 */
class LugarController extends Controller
{
    /** @route GET /api/lugares */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('lugar')->orderBy('LUG_NOM')->get([
                'LUG_COD as cod',
                DB::raw('LTRIM(RTRIM(LUG_NOM)) as nombre'),
            ])
        );
    }

    /** @route POST /api/lugares */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => 'required|string|max:100']);
        $cod = (int) DB::table('lugar')->max('LUG_COD') + 1;
        DB::table('lugar')->insert(['LUG_COD' => $cod, 'LUG_NOM' => trim($datos['nombre'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/lugares/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $request->validate(['nombre' => 'required|string|max:100']);
        $afectados = DB::table('lugar')->where('LUG_COD', $cod)->update(['LUG_NOM' => trim($datos['nombre'])]);
        if ($afectados === 0 && !DB::table('lugar')->where('LUG_COD', $cod)->exists()) {
            return response()->json(['error' => 'Lugar no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/lugares/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_LUGAR', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a este lugar.'], 422);
        }
        $borrados = DB::table('lugar')->where('LUG_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Lugar no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
