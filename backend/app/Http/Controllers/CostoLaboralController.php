<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CostoLaboralController — Costos Laborales: Editar Costos Fijos (empleados_costos_fijos.scx).
 *
 * Mantiene los "costos fijos para el cálculo de costos laborales" por período (mes/año),
 * en la tabla costo_laboral_fijo (COS_COD, COS_DET, COS_IMP, COS_MES, COS_ANIO).
 */
class CostoLaboralController extends Controller
{
    /** @route GET /api/costos-laborales?mes=&anio= — costos del período + subtotal. */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000|max:2200',
        ]);

        $rows = DB::table('costo_laboral_fijo')
            ->where('COS_MES', $d['mes'])->where('COS_ANIO', $d['anio'])
            ->orderBy('COS_DET')->get()
            ->map(fn ($c) => [
                'cod'     => (int) $c->COS_COD,
                'detalle' => trim((string) $c->COS_DET),
                'importe' => (float) $c->COS_IMP,
            ])->values();

        return response()->json([
            'costos'   => $rows,
            'subtotal' => (float) $rows->sum('importe'),
        ]);
    }

    /** @route POST /api/costos-laborales — agrega un costo al período (AGREGAR). */
    public function agregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes'         => 'required|integer|min:1|max:12',
            'anio'        => 'required|integer|min:2000|max:2200',
            'descripcion' => 'required|string|max:100',
        ], [
            'descripcion.required' => 'Debe ingresar la descripción del nuevo costo.',
        ]);

        $det = mb_strtoupper(trim($d['descripcion']));

        // No permitir descripción duplicada en el mismo período.
        $existe = DB::table('costo_laboral_fijo')
            ->where('COS_MES', $d['mes'])->where('COS_ANIO', $d['anio'])
            ->whereRaw('UPPER(LTRIM(RTRIM(COS_DET))) = ?', [$det])
            ->exists();
        if ($existe) {
            return response()->json(['message' => 'Ya existe un costo con la misma descripción.'], 422);
        }

        $cod = (int) DB::table('costo_laboral_fijo')->max('COS_COD') + 1;
        DB::table('costo_laboral_fijo')->insert(Registro::completar('costo_laboral_fijo', [
            'COS_COD'  => $cod,
            'COS_DET'  => mb_substr($det, 0, 100),
            'COS_IMP'  => 0,
            'COS_MES'  => (int) $d['mes'],
            'COS_ANIO' => (int) $d['anio'],
        ]));

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/costos-laborales/{cod} — actualiza el importe de un costo. */
    public function actualizar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['importe' => 'required|numeric']);
        if (!DB::table('costo_laboral_fijo')->where('COS_COD', $cod)->exists()) {
            return response()->json(['message' => 'Costo no encontrado.'], 404);
        }
        DB::table('costo_laboral_fijo')->where('COS_COD', $cod)->update(['COS_IMP' => (float) $d['importe']]);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/costos-laborales/{cod} — elimina un costo. */
    public function eliminar(int $cod): JsonResponse
    {
        if (!DB::table('costo_laboral_fijo')->where('COS_COD', $cod)->exists()) {
            return response()->json(['message' => 'Costo no encontrado.'], 404);
        }
        DB::table('costo_laboral_fijo')->where('COS_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }
}
