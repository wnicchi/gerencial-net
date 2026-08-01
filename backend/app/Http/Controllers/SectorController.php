<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SectorController — ABM de Sectores Laborales (tabla sector).
 * Campos: SEC_COD (código incremental), SEC_DES (nombre).
 */
class SectorController extends Controller
{
    /** @route GET /api/sectores */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('sector')->orderBy('SEC_DES')->get([
                'SEC_COD as cod',
                DB::raw('LTRIM(RTRIM(SEC_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/sectores */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(['nombre' => 'required|string|max:50']);
        $cod = (int) DB::table('sector')->max('SEC_COD') + 1;
        DB::table('sector')->insert(['SEC_COD' => $cod, 'SEC_DES' => trim($datos['nombre'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/sectores/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $request->validate(['nombre' => 'required|string|max:50']);
        $afectados = DB::table('sector')->where('SEC_COD', $cod)->update(['SEC_DES' => trim($datos['nombre'])]);
        if ($afectados === 0 && !DB::table('sector')->where('SEC_COD', $cod)->exists()) {
            return response()->json(['error' => 'Sector no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/sectores/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_SEC', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a este sector.'], 422);
        }
        $borrados = DB::table('sector')->where('SEC_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Sector no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
