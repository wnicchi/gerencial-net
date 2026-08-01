<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TareaController — Tareas (catálogo global) + asignación a puestos.
 *
 * Réplica de tareas_detalle.scx (FoxPro RRHHWINSQL). ABM simple del catálogo
 * `tareas` (tarea_cod, tarea_des). Cada tarea se puede asignar a varios puestos:
 * el vínculo se guarda en `tareapue` y se relaciona por la DESCRIPCIÓN
 * (tareapue.TAR_DES == tareas.tarea_des), tal como en FoxPro.
 */
class TareaController extends Controller
{
    /** @route GET /api/tareas?buscar= — catálogo de tareas. */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('tareas');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where('tarea_des', 'like', "%{$b}%");
        }
        $rows = $q->orderBy('tarea_des')->get(['tarea_cod', 'tarea_des'])
            ->map(fn ($t) => ['codigo' => (int) $t->tarea_cod, 'descripcion' => trim((string) $t->tarea_des)])->values();
        return response()->json($rows);
    }

    /** @route GET /api/tareas/puestos — todos los puestos (para el desplegable ASIGNAR). */
    public function puestos(): JsonResponse
    {
        $rows = DB::table('puestos')->orderBy('PUE_DES')->get(['PUE_COD', 'PUE_DES'])
            ->map(fn ($p) => ['codigo' => trim((string) $p->PUE_COD), 'descripcion' => trim((string) $p->PUE_DES)])->values();
        return response()->json($rows);
    }

    /** @route GET /api/tareas/{cod} — tarea + puestos en los que aplica. */
    public function show(int $cod): JsonResponse
    {
        $t = DB::table('tareas')->where('tarea_cod', $cod)->first(['tarea_cod', 'tarea_des']);
        if (!$t) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
        return response()->json([
            'codigo'      => (int) $t->tarea_cod,
            'descripcion' => trim((string) $t->tarea_des),
            'puestos'     => $this->puestosDeTarea(trim((string) $t->tarea_des)),
        ]);
    }

    /** @route POST /api/tareas — alta de tarea. */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['descripcion' => 'required|string|max:100']);
        $cod = ((int) DB::table('tareas')->max('tarea_cod')) + 1;
        DB::table('tareas')->insert(Registro::completar('tareas', [
            'tarea_cod' => $cod, 'tarea_des' => mb_strtoupper(trim($d['descripcion'])),
        ]));
        return response()->json(['ok' => true, 'codigo' => $cod], 201);
    }

    /** @route PUT /api/tareas/{cod} — modifica la descripción (y sincroniza tareapue). */
    public function update(Request $request, int $cod): JsonResponse
    {
        $t = DB::table('tareas')->where('tarea_cod', $cod)->first(['tarea_des']);
        if (!$t) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
        $d = $request->validate(['descripcion' => 'required|string|max:100']);
        $nueva = mb_strtoupper(trim($d['descripcion']));
        $vieja = trim((string) $t->tarea_des);
        DB::transaction(function () use ($cod, $nueva, $vieja) {
            DB::table('tareas')->where('tarea_cod', $cod)->update(['tarea_des' => $nueva]);
            // Mantener consistente el vínculo por descripción en tareapue.
            if ($vieja !== '' && mb_strtoupper($vieja) !== $nueva) {
                DB::table('tareapue')->whereRaw('UPPER(RTRIM(TAR_DES)) = ?', [mb_strtoupper($vieja)])
                    ->update(['TAR_DES' => $nueva]);
            }
        });
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/tareas/{cod} — baja de la tarea del catálogo. */
    public function destroy(int $cod): JsonResponse
    {
        DB::table('tareas')->where('tarea_cod', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    /** @route POST /api/tareas/{cod}/asignar  Body: { puesto } — vincula un puesto (evita duplicado). */
    public function asignar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['puesto' => 'required|string|max:15']);
        $t = DB::table('tareas')->where('tarea_cod', $cod)->first(['tarea_des']);
        if (!$t) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
        $des = mb_strtoupper(trim((string) $t->tarea_des));
        $pue = trim($d['puesto']);

        $existe = DB::table('tareapue')
            ->whereRaw('UPPER(RTRIM(TAR_PUE)) = ?', [mb_strtoupper($pue)])
            ->whereRaw('UPPER(RTRIM(TAR_DES)) = ?', [$des])->exists();
        if ($existe) {
            return response()->json(['message' => 'Ese puesto ya está asignado a esta tarea.'], 422);
        }
        $tarCod = ((int) DB::table('tareapue')->where('TAR_PUE', $pue)->max('TAR_COD')) + 1;
        DB::table('tareapue')->insert(Registro::completar('tareapue', [
            'TAR_COD' => $tarCod, 'TAR_DES' => $des, 'TAR_PUE' => $pue, 'TAR_FRE' => 0, 'TAR_MIN' => 0,
        ]));
        return response()->json(['ok' => true]);
    }

    /** @route POST /api/tareas/{cod}/baja  Body: { puestos:[...] } — desvincula puestos. */
    public function darBaja(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['puestos' => 'required|array|min:1', 'puestos.*' => 'string|max:15']);
        $t = DB::table('tareas')->where('tarea_cod', $cod)->first(['tarea_des']);
        if (!$t) {
            return response()->json(['message' => 'Tarea no encontrada.'], 404);
        }
        $des = mb_strtoupper(trim((string) $t->tarea_des));
        DB::transaction(function () use ($d, $des) {
            foreach ($d['puestos'] as $pue) {
                DB::table('tareapue')
                    ->whereRaw('UPPER(RTRIM(TAR_PUE)) = ?', [mb_strtoupper(trim((string) $pue))])
                    ->whereRaw('UPPER(RTRIM(TAR_DES)) = ?', [$des])->delete();
            }
        });
        return response()->json(['ok' => true]);
    }

    /** Puestos vinculados a una tarea (por descripción), con su jefe. */
    private function puestosDeTarea(string $des): array
    {
        $desU = mb_strtoupper($des);
        return DB::table('tareapue as tp')
            ->join('puestos as p', DB::raw('UPPER(RTRIM(tp.TAR_PUE))'), '=', DB::raw('UPPER(RTRIM(p.PUE_COD))'))
            ->whereRaw('UPPER(RTRIM(tp.TAR_DES)) = ?', [$desU])
            ->orderBy('p.PUE_DES')
            ->get(['p.PUE_COD', 'p.PUE_DES', 'p.PUE_REP'])
            ->map(fn ($r) => [
                'codigo'  => trim((string) $r->PUE_COD),
                'puesto'  => trim((string) $r->PUE_DES),
                'reporta' => trim((string) $r->PUE_REP),
            ])->values()->all();
    }
}
