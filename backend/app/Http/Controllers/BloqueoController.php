<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BloqueoController — Bloqueos Varios (tabla bloqueos). NO es un ABM: es un
 * toggle por (tipo, mes, año). Permite consultar y grabar si un período está
 * bloqueado para Novedades ('N') u Horas Extras ('H').
 *
 * Campos: BLO_TIP (N/H), BLO_MES, BLO_ANO, BLO_EST (S/N).
 */
class BloqueoController extends Controller
{
    /** @route GET /api/bloqueos/estado?tipo=&mes=&anio= */
    public function estado(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tipo' => 'required|in:N,H',
            'mes'  => 'required|integer|min:1|max:12',
            'anio' => 'required|integer|min:2000|max:2999',
        ]);

        $row = DB::table('bloqueos')
            ->whereRaw('UPPER(LTRIM(RTRIM(BLO_TIP))) = ?', [$d['tipo']])
            ->where('BLO_MES', $d['mes'])->where('BLO_ANO', $d['anio'])
            ->first(['BLO_EST']);

        return response()->json([
            'bloqueado' => $row ? strtoupper(trim((string) $row->BLO_EST)) === 'S' : false,
        ]);
    }

    /** @route POST /api/bloqueos  Body: { tipo, mes, anio, bloqueado } */
    public function grabar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tipo'      => 'required|in:N,H',
            'mes'       => 'required|integer|min:1|max:12',
            'anio'      => 'required|integer|min:2000|max:2999',
            'bloqueado' => 'required|boolean',
        ]);

        $est = $d['bloqueado'] ? 'S' : 'N';

        $existe = DB::table('bloqueos')
            ->whereRaw('UPPER(LTRIM(RTRIM(BLO_TIP))) = ?', [$d['tipo']])
            ->where('BLO_MES', $d['mes'])->where('BLO_ANO', $d['anio']);

        if ($existe->exists()) {
            $existe->update(['BLO_EST' => $est]);
        } else {
            DB::table('bloqueos')->insert([
                'BLO_TIP' => $d['tipo'], 'BLO_MES' => $d['mes'], 'BLO_ANO' => $d['anio'], 'BLO_EST' => $est,
            ]);
        }

        return response()->json(['ok' => true, 'bloqueado' => $d['bloqueado']]);
    }
}
