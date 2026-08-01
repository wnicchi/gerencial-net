<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FrecuenciaController — ABM de Frecuencias (tabla frecuencia).
 * Campos: FRE_COD (código incremental), FRE_DES (descripción).
 * Se usan en las tareas de los puestos (frecuencia de uso).
 */
class FrecuenciaController extends Controller
{
    /** @route GET /api/frecuencias */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('frecuencia')->orderBy('FRE_DES')->get([
                'FRE_COD as cod',
                DB::raw('LTRIM(RTRIM(FRE_DES)) as nombre'),
            ])
        );
    }

    /** @route POST /api/frecuencias */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $cod = (int) DB::table('frecuencia')->max('FRE_COD') + 1;
        DB::table('frecuencia')->insert(['FRE_COD' => $cod, 'FRE_DES' => trim($d['nombre'])]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/frecuencias/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $af = DB::table('frecuencia')->where('FRE_COD', $cod)->update(['FRE_DES' => trim($d['nombre'])]);
        if ($af === 0 && !DB::table('frecuencia')->where('FRE_COD', $cod)->exists()) {
            return response()->json(['message' => 'Frecuencia no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/frecuencias/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('tareapue')->where('TAR_FRE', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay tareas que usan esta frecuencia.'], 422);
        }
        $b = DB::table('frecuencia')->where('FRE_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Frecuencia no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
