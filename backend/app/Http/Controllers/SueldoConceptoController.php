<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SueldoConceptoController — Liquidaciones / Conceptos de Sueldos (sueldos_conceptos.scx).
 *
 * ABM simple de los conceptos de liquidación (tabla sueldo_con: SCO_COD, SCO_DES). SCO_COD no es identity.
 */
class SueldoConceptoController extends Controller
{
    /** @route GET /api/sueldos-conceptos */
    public function index(): JsonResponse
    {
        $filas = DB::table('sueldo_con')->orderBy('SCO_COD')->get(['SCO_COD', 'SCO_DES'])
            ->map(fn ($c) => ['cod' => (int) $c->SCO_COD, 'descripcion' => trim((string) $c->SCO_DES)])->values();
        return response()->json(['conceptos' => $filas]);
    }

    /** @route POST /api/sueldos-conceptos */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:100']);
        $cod = (int) DB::table('sueldo_con')->max('SCO_COD') + 1;
        DB::table('sueldo_con')->insert(Registro::completar('sueldo_con', [
            'SCO_COD' => $cod, 'SCO_DES' => mb_strtoupper(trim($d['descripcion'])),
        ]));
        return response()->json(['message' => 'Concepto creado correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/sueldos-conceptos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('sueldo_con')->where('SCO_COD', $cod)->exists()) return response()->json(['message' => 'Concepto inexistente.'], 404);
        $d = $request->validate(['descripcion' => 'required|string|max:100']);
        DB::table('sueldo_con')->where('SCO_COD', $cod)->update(['SCO_DES' => mb_strtoupper(trim($d['descripcion']))]);
        return response()->json(['message' => 'Concepto actualizado correctamente.']);
    }

    /** @route DELETE /api/sueldos-conceptos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $n = DB::table('sueldo_con')->where('SCO_COD', $cod)->delete();
        if (!$n) return response()->json(['message' => 'Concepto inexistente.'], 404);
        return response()->json(['message' => 'Concepto eliminado correctamente.']);
    }
}
