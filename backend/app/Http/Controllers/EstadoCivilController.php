<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EstadoCivilController — ABM de estados civiles (tabla estadocivil).
 * Campos: ECI_COD (código incremental) y ECI_DES (descripción).
 */
class EstadoCivilController extends Controller
{
    /** GET /api/estado-civil — listado */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('estadocivil')
                ->orderBy('ECI_COD')
                ->get([
                    'ECI_COD as cod',
                    DB::raw('LTRIM(RTRIM(ECI_DES)) as descripcion'),
                ])
        );
    }

    /** POST /api/estado-civil — alta (código = MAX+1) */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['descripcion' => 'required|string|max:50']);

        $cod = (int) DB::table('estadocivil')->max('ECI_COD') + 1;
        DB::table('estadocivil')->insert([
            'ECI_COD' => $cod,
            'ECI_DES' => trim($request->descripcion),
        ]);

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** PUT /api/estado-civil/{cod} — edición */
    public function update(Request $request, int $cod): JsonResponse
    {
        $request->validate(['descripcion' => 'required|string|max:50']);

        $afectados = DB::table('estadocivil')
            ->where('ECI_COD', $cod)
            ->update(['ECI_DES' => trim($request->descripcion)]);

        if ($afectados === 0) {
            return response()->json(['message' => 'Estado civil no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** DELETE /api/estado-civil/{cod} — baja (bloquea si está en uso) */
    public function destroy(int $cod): JsonResponse
    {
        $enUso = DB::table('personal')->where('PER_ECI', $cod)->exists();
        if ($enUso) {
            return response()->json([
                'message' => 'No se puede eliminar: hay empleados con este estado civil asignado.',
            ], 422);
        }

        DB::table('estadocivil')->where('ECI_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }
}
