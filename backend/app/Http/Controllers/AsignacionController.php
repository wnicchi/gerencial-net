<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AsignacionController — ABM de Asignaciones Familiares (tabla asignaci).
 * Campos: ASI_COD (código incremental), ASI_DES (descripción), ASI_IMP (importe).
 */
class AsignacionController extends Controller
{
    /** @route GET /api/asignaciones */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('asignaci')->orderBy('ASI_DES')->get([
                'ASI_COD as cod',
                DB::raw('LTRIM(RTRIM(ASI_DES)) as descripcion'),
                'ASI_IMP as importe',
            ])
        );
    }

    /** @route POST /api/asignaciones */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('asignaci')->max('ASI_COD') + 1;
        DB::table('asignaci')->insert([
            'ASI_COD' => $cod,
            'ASI_DES' => trim($datos['descripcion']),
            'ASI_IMP' => $datos['importe'] ?? 0,
        ]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/asignaciones/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);
        $afectados = DB::table('asignaci')->where('ASI_COD', $cod)->update([
            'ASI_DES' => trim($datos['descripcion']),
            'ASI_IMP' => $datos['importe'] ?? 0,
        ]);
        if ($afectados === 0 && !DB::table('asignaci')->where('ASI_COD', $cod)->exists()) {
            return response()->json(['error' => 'Asignación no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/asignaciones/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $borrados = DB::table('asignaci')->where('ASI_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Asignación no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion' => 'required|string|max:50',
            'importe'     => 'nullable|numeric|min:0',
        ]);
    }
}
