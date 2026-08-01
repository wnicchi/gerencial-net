<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SueldoTipoController — Liquidaciones / Tipos de Sueldos (sueldos_tipos.scx).
 *
 * ABM simple de los tipos de sueldo (tabla sueldo_tip: STI_COD, STI_DES). STI_COD no es identity.
 */
class SueldoTipoController extends Controller
{
    /** @route GET /api/sueldos-tipos */
    public function index(): JsonResponse
    {
        $filas = DB::table('sueldo_tip')->orderBy('STI_COD')->get(['STI_COD', 'STI_DES'])
            ->map(fn ($t) => ['cod' => (int) $t->STI_COD, 'descripcion' => trim((string) $t->STI_DES)])->values();
        return response()->json(['tipos' => $filas]);
    }

    /** @route POST /api/sueldos-tipos */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        $cod = (int) DB::table('sueldo_tip')->max('STI_COD') + 1;
        DB::table('sueldo_tip')->insert(Registro::completar('sueldo_tip', [
            'STI_COD' => $cod, 'STI_DES' => mb_strtoupper(trim($d['descripcion'])),
        ]));
        return response()->json(['message' => 'Tipo de sueldo creado correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/sueldos-tipos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('sueldo_tip')->where('STI_COD', $cod)->exists()) return response()->json(['message' => 'Tipo inexistente.'], 404);
        $d = $request->validate(['descripcion' => 'required|string|max:50']);
        DB::table('sueldo_tip')->where('STI_COD', $cod)->update(['STI_DES' => mb_strtoupper(trim($d['descripcion']))]);
        return response()->json(['message' => 'Tipo de sueldo actualizado correctamente.']);
    }

    /** @route DELETE /api/sueldos-tipos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $n = DB::table('sueldo_tip')->where('STI_COD', $cod)->delete();
        if (!$n) return response()->json(['message' => 'Tipo inexistente.'], 404);
        return response()->json(['message' => 'Tipo de sueldo eliminado correctamente.']);
    }
}
