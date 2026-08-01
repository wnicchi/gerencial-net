<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ContratistaController — ABM de Contratistas (tabla contratista).
 *
 * Campos: CONT_COD (código incremental), CONT_DET (nombre),
 * CONT_INI (código base de empleados nuevos).
 */
class ContratistaController extends Controller
{
    /** @route GET /api/contratistas */
    public function index(): JsonResponse
    {
        $items = DB::table('contratista')->orderBy('CONT_DET')->get([
            'CONT_COD as cod',
            DB::raw('LTRIM(RTRIM(CONT_DET)) as nombre'),
            'CONT_INI as base_empleados',
        ]);

        return response()->json($items);
    }

    /** @route POST /api/contratistas */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('contratista')->max('CONT_COD') + 1;

        DB::table('contratista')->insert([
            'CONT_COD' => $cod,
            'CONT_DET' => trim($datos['nombre']),
            'CONT_INI' => (int) ($datos['base_empleados'] ?? 0),
        ]);

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/contratistas/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);

        $afectados = DB::table('contratista')->where('CONT_COD', $cod)->update([
            'CONT_DET' => trim($datos['nombre']),
            'CONT_INI' => (int) ($datos['base_empleados'] ?? 0),
        ]);

        if ($afectados === 0 && !DB::table('contratista')->where('CONT_COD', $cod)->exists()) {
            return response()->json(['error' => 'Contratista no encontrado.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/contratistas/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        // No permitir borrar un contratista con empleados asignados
        if (DB::table('personal')->where('PER_CONTRA', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a este contratista.'], 422);
        }

        $borrados = DB::table('contratista')->where('CONT_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Contratista no encontrado.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'         => 'required|string|max:100',
            'base_empleados' => 'nullable|integer|min:0',
        ]);
    }
}
