<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TemaController — ABM de Áreas Temáticas (tabla tema).
 * Campos: TEM_COD (incremental), TEM_DES (descripción).
 */
class TemaController extends Controller
{
    /** @route GET /api/areas-tematicas?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('tema');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where('TEM_DES', 'like', "%{$b}%");
        }
        return response()->json($q->orderBy('TEM_DES')->get([
            'TEM_COD as cod', DB::raw('LTRIM(RTRIM(TEM_DES)) as nombre'),
        ]));
    }

    /** @route POST /api/areas-tematicas */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $cod = (int) DB::table('tema')->max('TEM_COD') + 1;
        DB::table('tema')->insert(['TEM_COD' => $cod, 'TEM_DES' => mb_strtoupper(trim($d['nombre']))]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/areas-tematicas/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['nombre' => 'required|string|max:50']);
        $af = DB::table('tema')->where('TEM_COD', $cod)->update(['TEM_DES' => mb_strtoupper(trim($d['nombre']))]);
        if ($af === 0 && !DB::table('tema')->where('TEM_COD', $cod)->exists()) {
            return response()->json(['message' => 'Área temática no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/areas-tematicas/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('capacitacion')->where('CAP_TEM_COD', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el área está usada en jornadas de capacitación.'], 422);
        }
        $b = DB::table('tema')->where('TEM_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Área temática no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }
}
