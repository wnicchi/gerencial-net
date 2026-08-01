<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ViajeController — Carga de viajes del personal (tabla viajes, campos PVI_*).
 *
 * Réplica del formulario FoxPro "Viajes": alta de un viaje imputado a un empleado
 * (fecha de imputación, salida/llegada, días, destino, kilómetros, dominio y obs.).
 */
class ViajeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'PVI_PER' => 'required|integer|min:1',
            'PVI_NOM' => 'nullable|string',
            'PVI_FEC' => 'nullable|date',
            'PVI_FSA' => 'nullable|date',
            'PVI_FEN' => 'nullable|date',
            'PVI_DIA' => 'nullable|integer|min:0',
            'PVI_DES' => 'nullable|string',
            'PVI_KMS' => 'nullable|integer|min:0',
            'PVI_VEH' => 'nullable|string',
            'PVI_OBS' => 'nullable|string',
        ], [], [
            'PVI_PER' => 'empleado',
        ]);

        // Regla FoxPro: la SALIDA no puede ser posterior a la LLEGADA.
        if (!empty($d['PVI_FSA']) && !empty($d['PVI_FEN']) && strtotime($d['PVI_FSA']) > strtotime($d['PVI_FEN'])) {
            return response()->json(['message' => 'La fecha/hora de SALIDA no puede ser mayor a la de LLEGADA.'], 422);
        }

        if (!\App\Support\Empleado::activo((int) $d['PVI_PER'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }

        $fila = Registro::completar('viajes', $d);
        DB::table('viajes')->insert($fila);

        return response()->json(['message' => 'Viaje guardado correctamente.'], 201);
    }

    /** Lista de viajes por período (salida o llegada dentro del rango), con legajo. */
    public function index(Request $request): JsonResponse
    {
        $desde = trim((string) $request->query('desde', ''));
        $hasta = trim((string) $request->query('hasta', ''));

        $q = DB::table('viajes as v')
            ->leftJoin('personal as p', 'v.PVI_PER', '=', 'p.PER_COD')
            ->select(
                'v.UNICO', 'v.PVI_PER', 'v.PVI_NOM', 'v.PVI_FEC', 'v.PVI_FSA', 'v.PVI_FEN',
                'v.PVI_DIA', 'v.PVI_DES', 'v.PVI_KMS', 'v.PVI_VEH', 'v.PVI_OBS',
                DB::raw('p.PER_LEG as legajo')
            );

        if ($desde !== '' && $hasta !== '') {
            $q->where(function ($w) use ($desde, $hasta) {
                $w->whereBetween(DB::raw('CAST(v.PVI_FSA AS DATE)'), [$desde, $hasta])
                  ->orWhereBetween(DB::raw('CAST(v.PVI_FEN AS DATE)'), [$desde, $hasta]);
            });
        }

        $rows = $q->orderByDesc('v.PVI_FSA')->limit(1000)->get();
        return response()->json($rows);
    }

    /** Modifica un viaje existente (no cambia el empleado). */
    public function update(Request $request, int $unico): JsonResponse
    {
        $d = $request->validate([
            'PVI_FEC' => 'nullable|date',
            'PVI_FSA' => 'nullable|date',
            'PVI_FEN' => 'nullable|date',
            'PVI_DIA' => 'nullable|integer|min:0',
            'PVI_DES' => 'nullable|string',
            'PVI_KMS' => 'nullable|integer|min:0',
            'PVI_VEH' => 'nullable|string',
            'PVI_OBS' => 'nullable|string',
        ]);

        if (!empty($d['PVI_FSA']) && !empty($d['PVI_FEN']) && strtotime($d['PVI_FSA']) > strtotime($d['PVI_FEN'])) {
            return response()->json(['message' => 'La fecha/hora de SALIDA no puede ser mayor a la de LLEGADA.'], 422);
        }

        if (!DB::table('viajes')->where('UNICO', $unico)->exists()) {
            return response()->json(['message' => 'El viaje no existe.'], 404);
        }

        $upd = [
            'PVI_FEC' => $d['PVI_FEC'] ?? '1900-01-01',
            'PVI_FSA' => $d['PVI_FSA'] ?? '1900-01-01',
            'PVI_FEN' => $d['PVI_FEN'] ?? '1900-01-01',
            'PVI_DIA' => (int) ($d['PVI_DIA'] ?? 0),
            'PVI_DES' => mb_substr((string) ($d['PVI_DES'] ?? ''), 0, 100),
            'PVI_KMS' => (int) ($d['PVI_KMS'] ?? 0),
            'PVI_VEH' => mb_substr((string) ($d['PVI_VEH'] ?? ''), 0, 7),
            'PVI_OBS' => mb_substr((string) ($d['PVI_OBS'] ?? ''), 0, 100),
        ];
        DB::table('viajes')->where('UNICO', $unico)->update($upd);

        return response()->json(['message' => 'Viaje modificado correctamente.']);
    }

    /** Elimina uno o varios viajes por UNICO. */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'unicos'   => 'required|array|min:1',
            'unicos.*' => 'integer',
        ]);
        $n = DB::table('viajes')->whereIn('UNICO', $d['unicos'])->delete();
        return response()->json(['eliminados' => $n]);
    }

    /** Últimos viajes cargados (para mostrar debajo del formulario). */
    public function recientes(): JsonResponse
    {
        $rows = DB::table('viajes')->orderByDesc('UNICO')->limit(15)->get([
            'UNICO', 'PVI_PER', 'PVI_NOM', 'PVI_FEC', 'PVI_FSA', 'PVI_FEN',
            'PVI_DIA', 'PVI_DES', 'PVI_KMS', 'PVI_VEH',
        ]);
        return response()->json($rows);
    }
}
