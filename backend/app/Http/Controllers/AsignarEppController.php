<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AsignarEppController — Asignar EPP utilizada por un empleado (per_ropa).
 *
 * Réplica de empleado_asignar_epp_utilizado.scx. Lista las prendas/EPP que tiene
 * asignadas un empleado (per_ropa) con su talle, y permite reemplazar toda la
 * lista (al confirmar: borra las del empleado y reinserta las enviadas).
 *
 * per_ropa: PERO_COD (empleado), PERO_RCOD (ropa), PERO_RDES (descripción ropa),
 *           PERO_RTAL (talle cod), PERO_RTAD (talle descripción).
 */
class AsignarEppController extends Controller
{
    /** @route GET /api/asignar-epp/empleado/{cod} — empleado + EPP asignada. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }
        $items = DB::table('per_ropa')->where('PERO_COD', $cod)->orderBy('PERO_RDES')->get()
            ->map(fn ($r) => [
                'rcod' => (int) $r->PERO_RCOD,
                'rdes' => trim((string) $r->PERO_RDES),
                'rtal' => (int) $r->PERO_RTAL,
                'rtad' => trim((string) $r->PERO_RTAD),
            ])->values();
        return response()->json(['nombre' => trim((string) $p->PER_NOM), 'items' => $items]);
    }

    /** @route GET /api/asignar-epp/ropa/{cod} — descripción de una prenda/EPP por código. */
    public function ropa(int $cod): JsonResponse
    {
        $r = DB::table('ropa')->where('ROP_COD', $cod)->first(['ROP_COD', 'ROP_DES']);
        if (!$r) {
            return response()->json(['message' => 'Código de prenda/EPP inexistente.'], 404);
        }
        return response()->json(['cod' => (int) $r->ROP_COD, 'des' => trim((string) $r->ROP_DES)]);
    }

    /** @route POST /api/asignar-epp/{cod} — reemplaza la EPP asignada del empleado. */
    public function guardar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'items'        => 'present|array',
            'items.*.rcod' => 'required|integer',
            'items.*.rdes' => 'nullable|string|max:100',
            'items.*.rtal' => 'nullable|integer',
            'items.*.rtad' => 'nullable|string|max:20',
        ]);
        if (!DB::table('personal')->where('PER_COD', $cod)->exists()) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }
        if (!\App\Support\Empleado::activo($cod)) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }

        DB::transaction(function () use ($cod, $d) {
            DB::table('per_ropa')->where('PERO_COD', $cod)->delete();
            foreach ($d['items'] as $it) {
                if ((int) ($it['rcod'] ?? 0) <= 0) continue;
                DB::table('per_ropa')->insert(Registro::completar('per_ropa', [
                    'PERO_COD'  => $cod,
                    'PERO_RCOD' => (int) $it['rcod'],
                    'PERO_RDES' => mb_substr(trim((string) ($it['rdes'] ?? '')), 0, 100),
                    'PERO_RTAL' => (int) ($it['rtal'] ?? 0),
                    'PERO_RTAD' => mb_substr(trim((string) ($it['rtad'] ?? '')), 0, 20),
                ]));
            }
        });
        return response()->json(['ok' => true]);
    }
}
