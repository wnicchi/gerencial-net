<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojUbicacionController — Reloj / Reloj Ubicación (reloj_ubicaciones.scx).
 *
 * ABM simple de las ubicaciones físicas de los relojes de personal (tabla reloj_ubicacion: ure_cod, ure_des).
 * ure_cod no es identity. La descripción se guarda en mayúsculas.
 */
class RelojUbicacionController extends Controller
{
    /** @route GET /api/reloj/ubicaciones */
    public function index(): JsonResponse
    {
        $filas = DB::table('reloj_ubicacion')->orderBy('ure_cod')->get(['ure_cod', 'ure_des'])
            ->map(fn ($u) => ['cod' => (int) $u->ure_cod, 'descripcion' => trim((string) $u->ure_des)])->values();
        return response()->json(['ubicaciones' => $filas]);
    }

    /** @route POST /api/reloj/ubicaciones */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        $cod = (int) DB::table('reloj_ubicacion')->max('ure_cod') + 1;
        DB::table('reloj_ubicacion')->insert(Registro::completar('reloj_ubicacion', [
            'ure_cod' => $cod, 'ure_des' => mb_strtoupper(trim($d['descripcion'])),
        ]));
        return response()->json(['message' => 'Ubicación creada correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/reloj/ubicaciones/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('reloj_ubicacion')->where('ure_cod', $cod)->exists()) return response()->json(['message' => 'Ubicación inexistente.'], 404);
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        DB::table('reloj_ubicacion')->where('ure_cod', $cod)->update(['ure_des' => mb_strtoupper(trim($d['descripcion']))]);
        return response()->json(['message' => 'Ubicación actualizada correctamente.']);
    }

    /** @route DELETE /api/reloj/ubicaciones/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $n = DB::table('reloj_ubicacion')->where('ure_cod', $cod)->delete();
        if (!$n) return response()->json(['message' => 'Ubicación inexistente.'], 404);
        return response()->json(['message' => 'Ubicación eliminada correctamente.']);
    }
}
