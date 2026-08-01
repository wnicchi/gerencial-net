<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojGruposController — Reloj / Grupos de Turnos Laborales (reloj_grupos).
 *
 * ABM de los grupos/turnos de horario que se asignan a los empleados (PER_GRU). Cada grupo tiene una
 * descripción y hasta 3 turnos; cada turno define, para los 7 días (**d1=Domingo, d2=Lunes … d7=Sábado**,
 * igual que VFP Dow()), la hora de entrada (rgr_t{t}d{d}d) y de salida (rgr_t{t}d{d}h) en formato HHMM,
 * más la rotación del turno: rgr_t{t}cam (rotar cada N días) y rgr_t{t}pas (al turno N). rgr_cod no es identity.
 *
 * La rotación entre turnos según la semana la usa el cálculo de Horas Trabajadas.
 */
class RelojGruposController extends Controller
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /** @route GET /api/reloj/grupos */
    public function index(): JsonResponse
    {
        $filas = DB::table('reloj_grupos')->orderBy('rgr_des')->get()
            ->map(fn ($g) => ['cod' => (int) $g->rgr_cod, 'des' => trim((string) $g->rgr_des), 'turnos' => $this->cuantosTurnos($g)])->values();
        return response()->json(['grupos' => $filas]);
    }

    /** @route GET /api/reloj/grupos/{cod} */
    public function show(int $cod): JsonResponse
    {
        $g = DB::table('reloj_grupos')->where('rgr_cod', $cod)->first();
        if (!$g) return response()->json(['message' => 'Grupo inexistente.'], 404);
        return response()->json($this->fila($g));
    }

    /** @route POST /api/reloj/grupos */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('reloj_grupos')->max('rgr_cod') + 1;
        DB::table('reloj_grupos')->insert(Registro::completar('reloj_grupos', $this->columnas($d) + ['rgr_cod' => $cod]));
        return response()->json(['message' => 'Grupo creado correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/reloj/grupos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('reloj_grupos')->where('rgr_cod', $cod)->exists()) return response()->json(['message' => 'Grupo inexistente.'], 404);
        $d = $this->validar($request);
        DB::table('reloj_grupos')->where('rgr_cod', $cod)->update($this->columnas($d));
        return response()->json(['message' => 'Grupo actualizado correctamente.']);
    }

    /** @route DELETE /api/reloj/grupos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_GRU', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a este grupo.'], 422);
        }
        $n = DB::table('reloj_grupos')->where('rgr_cod', $cod)->delete();
        if (!$n) return response()->json(['message' => 'Grupo inexistente.'], 404);
        return response()->json(['message' => 'Grupo eliminado correctamente.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function cuantosTurnos($g): int
    {
        $n = 1;
        for ($t = 2; $t <= 3; $t++) {
            for ($d = 1; $d <= 7; $d++) {
                if ((float) $g->{"rgr_t{$t}d{$d}d"} > 0 || (float) $g->{"rgr_t{$t}d{$d}h"} > 0) { $n = $t; break; }
            }
        }
        return $n;
    }

    private function fila($g): array
    {
        $turnos = [];
        for ($t = 1; $t <= 3; $t++) {
            $dias = [];
            for ($d = 1; $d <= 7; $d++) {
                $dias[] = [
                    'nombre' => self::DIAS[$d - 1],
                    'entrada' => (int) round((float) $g->{"rgr_t{$t}d{$d}d"}),
                    'salida' => (int) round((float) $g->{"rgr_t{$t}d{$d}h"}),
                ];
            }
            $turnos[] = ['cambio' => (int) round((float) $g->{"rgr_t{$t}cam"}), 'pase' => (int) round((float) $g->{"rgr_t{$t}pas"}), 'dias' => $dias];
        }
        return ['cod' => (int) $g->rgr_cod, 'des' => trim((string) $g->rgr_des), 'turnos' => $turnos];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'des'    => 'required|string|max:100',
            'turnos' => 'required|array|size:3',
            'turnos.*.cambio' => 'nullable|integer', 'turnos.*.pase' => 'nullable|integer',
            'turnos.*.dias' => 'required|array|size:7',
            'turnos.*.dias.*.entrada' => 'nullable|integer', 'turnos.*.dias.*.salida' => 'nullable|integer',
        ]);
    }

    private function columnas(array $d): array
    {
        $cols = ['rgr_des' => mb_strtoupper(trim((string) $d['des']))];
        for ($t = 1; $t <= 3; $t++) {
            $turno = $d['turnos'][$t - 1] ?? [];
            $cols["rgr_t{$t}cam"] = (int) ($turno['cambio'] ?? 0);
            $cols["rgr_t{$t}pas"] = (int) ($turno['pase'] ?? 0);
            for ($dia = 1; $dia <= 7; $dia++) {
                $val = $turno['dias'][$dia - 1] ?? [];
                $cols["rgr_t{$t}d{$dia}d"] = (int) ($val['entrada'] ?? 0);
                $cols["rgr_t{$t}d{$dia}h"] = (int) ($val['salida'] ?? 0);
            }
        }
        return $cols;
    }
}
