<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DepartamController — ABM de Departamentos (tabla departam).
 * Campos: DEP_COD (código incremental), DEP_DES (descripción).
 */
class DepartamController extends Controller
{
    /** @route GET /api/departamentos */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('departam')->orderBy('DEP_DES')->get([
                'DEP_COD as cod',
                DB::raw('LTRIM(RTRIM(DEP_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/departamentos */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $cod = (int) DB::table('departam')->max('DEP_COD') + 1;
        DB::table('departam')->insert(['DEP_COD' => $cod, 'DEP_DES' => trim($d['nombre'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/departamentos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $af = DB::table('departam')->where('DEP_COD', $cod)->update(['DEP_DES' => trim($d['nombre'])]);
        if ($af === 0 && !DB::table('departam')->where('DEP_COD', $cod)->exists()) {
            return response()->json(['message' => 'Departamento no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/departamentos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('puestos')->where('PUE_DEP', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay puestos asignados a este departamento.'], 422);
        }
        $b = DB::table('departam')->where('DEP_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Departamento no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
