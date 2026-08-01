<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SubsectorController — ABM de Sub-Sectores Laborales (tabla subsector).
 * Campos: SUB_COD (código incremental), SUB_DES (nombre).
 */
class SubsectorController extends Controller
{
    /** @route GET /api/subsectores */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('subsector')->orderBy('SUB_DES')->get([
                'SUB_COD as cod',
                DB::raw('LTRIM(RTRIM(SUB_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/subsectores */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => 'required|string|max:30']);
        $cod = (int) DB::table('subsector')->max('SUB_COD') + 1;
        DB::table('subsector')->insert(['SUB_COD' => $cod, 'SUB_DES' => trim($datos['nombre'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/subsectores/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $request->validate(['nombre' => 'required|string|max:30']);
        $afectados = DB::table('subsector')->where('SUB_COD', $cod)->update(['SUB_DES' => trim($datos['nombre'])]);
        if ($afectados === 0 && !DB::table('subsector')->where('SUB_COD', $cod)->exists()) {
            return response()->json(['error' => 'Sub-sector no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/subsectores/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_SUC', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a este sub-sector.'], 422);
        }
        $borrados = DB::table('subsector')->where('SUB_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Sub-sector no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
