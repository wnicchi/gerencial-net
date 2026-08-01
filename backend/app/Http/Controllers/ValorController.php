<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ValorController — pantalla de Valores (parámetros varios). NO es un ABM:
 * la tabla `valores` tiene un único registro que se consulta y se actualiza.
 *
 * Campos usados por la pantalla:
 *  VAL_DBA depositado banco · VAL_AFE fecha depósito · LUG_PAG observación
 *  VAL_HA1..4 / VAL_HI1..4  asignaciones familiares por hijo (hasta / pesos)
 *  VAL_PPR porcentaje de reducción · VAL_AOS importe adicional O.S.
 *  VAL_CIE decreto 1273/02 · VAL_PC1 obra social decreto · VAL_PC2 INSSJP decreto
 */
class ValorController extends Controller
{
    /** @route GET /api/valores */
    public function show(): JsonResponse
    {
        $v = DB::table('valores')->first();
        if (!$v) return response()->json([]);

        $fecha = $v->VAL_AFE ? substr((string) $v->VAL_AFE, 0, 10) : '';
        if ($fecha === '1900-01-01') $fecha = '';

        return response()->json([
            'deposito_banco' => trim((string) $v->VAL_DBA),
            'fecha_deposito' => $fecha,
            'observacion'    => trim((string) $v->LUG_PAG),
            'ha1' => (int) $v->VAL_HA1, 'ha2' => (int) $v->VAL_HA2, 'ha3' => (int) $v->VAL_HA3, 'ha4' => (int) $v->VAL_HA4,
            'hi1' => (float) $v->VAL_HI1, 'hi2' => (float) $v->VAL_HI2, 'hi3' => (float) $v->VAL_HI3, 'hi4' => (float) $v->VAL_HI4,
            'porc_reduccion' => (float) $v->VAL_PPR,
            'adicional_os'   => (float) $v->VAL_AOS,
            'decreto'        => (float) $v->VAL_CIE,
            'os_decreto'     => (float) $v->VAL_PC1,
            'inssjp_decreto' => (float) $v->VAL_PC2,
        ]);
    }

    /** @route PUT /api/valores */
    public function update(Request $request): JsonResponse
    {
        $d = $request->validate([
            'deposito_banco' => 'nullable|string|max:50',
            'fecha_deposito' => 'nullable|date',
            'observacion'    => 'nullable|string|max:50',
            'ha1' => 'nullable|integer|min:0', 'ha2' => 'nullable|integer|min:0',
            'ha3' => 'nullable|integer|min:0', 'ha4' => 'nullable|integer|min:0',
            'hi1' => 'nullable|numeric|min:0', 'hi2' => 'nullable|numeric|min:0',
            'hi3' => 'nullable|numeric|min:0', 'hi4' => 'nullable|numeric|min:0',
            'porc_reduccion' => 'nullable|numeric',
            'adicional_os'   => 'nullable|numeric',
            'decreto'        => 'nullable|numeric',
            'os_decreto'     => 'nullable|numeric',
            'inssjp_decreto' => 'nullable|numeric',
        ]);

        $datos = [
            'VAL_DBA' => trim((string) ($d['deposito_banco'] ?? '')),
            'VAL_AFE' => $d['fecha_deposito'] ?? '1900-01-01',
            'LUG_PAG' => trim((string) ($d['observacion'] ?? '')),
            'VAL_HA1' => (int) ($d['ha1'] ?? 0), 'VAL_HA2' => (int) ($d['ha2'] ?? 0),
            'VAL_HA3' => (int) ($d['ha3'] ?? 0), 'VAL_HA4' => (int) ($d['ha4'] ?? 0),
            'VAL_HI1' => $d['hi1'] ?? 0, 'VAL_HI2' => $d['hi2'] ?? 0,
            'VAL_HI3' => $d['hi3'] ?? 0, 'VAL_HI4' => $d['hi4'] ?? 0,
            'VAL_PPR' => $d['porc_reduccion'] ?? 0,
            'VAL_AOS' => $d['adicional_os'] ?? 0,
            'VAL_CIE' => $d['decreto'] ?? 0,
            'VAL_PC1' => $d['os_decreto'] ?? 0,
            'VAL_PC2' => $d['inssjp_decreto'] ?? 0,
        ];

        if (DB::table('valores')->exists()) {
            DB::table('valores')->update($datos);
        } else {
            DB::table('valores')->insert($datos);
        }

        return response()->json(['ok' => true]);
    }
}
