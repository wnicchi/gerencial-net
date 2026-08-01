<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojTurnosController — Reloj / Asignar Turnos Laborales (empleado_asignar_turno_laboral.scx).
 *
 * Lista el personal activo de una empresa con su convenio y el turno (grupo de reloj) asignado, y permite
 * cambiarles el turno en bloque. Al confirmar actualiza PER_GRU en personal.
 */
class RelojTurnosController extends Controller
{
    /** @route GET /api/reloj/turnos/grupos — catálogo de turnos (reloj_grupos). */
    public function grupos(): JsonResponse
    {
        $filas = DB::table('reloj_grupos')->orderBy('rgr_des')->get(['rgr_cod', 'rgr_des'])
            ->map(fn ($g) => ['cod' => (int) $g->rgr_cod, 'des' => trim((string) $g->rgr_des)])->values();
        return response()->json(['grupos' => $filas]);
    }

    /** @route GET /api/reloj/turnos/personal?empresa= — personal activo con su turno actual. */
    public function personal(Request $request): JsonResponse
    {
        $emp = (int) $request->validate(['empresa' => 'required|integer'])['empresa'];

        $filas = DB::table('personal as p')->join('convenio as c', 'c.con_cod', '=', 'p.PER_CON')
            ->where('p.PER_EMP', $emp)->where('p.PER_AOP', 'A')
            ->orderBy('p.PER_NOM')
            ->get(['p.PER_COD', 'p.PER_NOM', 'p.PER_LEG', 'c.con_des', 'p.PER_GRU'])
            ->map(fn ($p) => [
                'cod' => (int) $p->PER_COD, 'nombre' => trim((string) $p->PER_NOM), 'legajo' => (int) $p->PER_LEG,
                'convenio' => trim((string) $p->con_des), 'turno' => (int) $p->PER_GRU,
            ])->values();

        return response()->json(['total' => $filas->count(), 'personal' => $filas]);
    }

    /** @route POST /api/reloj/turnos/confirmar — actualiza el turno (PER_GRU) de los empleados cambiados. */
    public function confirmar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cambios' => 'required|array|min:1',
            'cambios.*.cod' => 'required|integer', 'cambios.*.turno' => 'required|integer',
        ]);
        $n = 0;
        DB::transaction(function () use ($d, &$n) {
            foreach ($d['cambios'] as $c) {
                $n += DB::table('personal')->where('PER_COD', (int) $c['cod'])->update(['PER_GRU' => (int) $c['turno']]);
            }
        });
        return response()->json(['message' => "Turnos laborales actualizados ($n).", 'actualizados' => $n]);
    }
}
