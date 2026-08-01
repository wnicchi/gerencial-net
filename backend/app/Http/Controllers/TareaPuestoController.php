<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TareaPuestoController — "Tareas por Puesto" (ABM plano sobre tareapue).
 *
 * Réplica de tareas.scx (FoxPro RRHHWINSQL). Cada registro de `tareapue` es la
 * asignación de una tarea a un puesto, con su frecuencia (TAR_FRE → frecuencia)
 * y el % de la jornada que insume (TAR_MIN). Se identifica cada fila por la
 * clave natural (TAR_PUE + TAR_DES): una tarea no se repite dentro de un puesto.
 */
class TareaPuestoController extends Controller
{
    /** @route GET /api/tareas-puesto?buscar= — lista de asignaciones tarea↔puesto. */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('tareapue as t')
            ->leftJoin('puestos as p', DB::raw('UPPER(RTRIM(p.PUE_COD))'), '=', DB::raw('UPPER(RTRIM(t.TAR_PUE))'))
            ->leftJoin('frecuencia as f', 'f.FRE_COD', '=', 't.TAR_FRE');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where(function ($w) use ($b) {
                $w->where('t.TAR_DES', 'like', "%{$b}%")->orWhere('p.PUE_DES', 'like', "%{$b}%");
            });
        }
        $rows = $q->orderBy('p.PUE_DES')->orderBy('t.TAR_DES')
            ->get(['t.TAR_COD', 't.TAR_DES', 't.TAR_PUE', 't.TAR_FRE', 't.TAR_MIN', 'p.PUE_DES', 'f.FRE_DES'])
            ->map(fn ($r) => [
                'cod'        => (int) $r->TAR_COD,
                'des'        => trim((string) $r->TAR_DES),
                'puesto'     => trim((string) $r->TAR_PUE),
                'puesto_des' => trim((string) ($r->PUE_DES ?? '')),
                'fre'        => (int) $r->TAR_FRE,
                'fre_des'    => trim((string) ($r->FRE_DES ?? '')),
                'min'        => (int) $r->TAR_MIN,
            ])->values();
        return response()->json($rows);
    }

    /** @route POST /api/tareas-puesto — crea una asignación. */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $pue = trim($d['puesto']); $des = mb_strtoupper(trim($d['des']));
        if ($this->existe($pue, $des)) {
            return response()->json(['message' => 'Esa tarea ya está asignada a ese puesto.'], 422);
        }
        $cod = ((int) DB::table('tareapue')->where('TAR_PUE', $pue)->max('TAR_COD')) + 1;
        DB::table('tareapue')->insert(Registro::completar('tareapue', [
            'TAR_COD' => $cod, 'TAR_DES' => $des, 'TAR_PUE' => $pue,
            'TAR_FRE' => (int) ($d['fre'] ?? 0), 'TAR_MIN' => (int) ($d['min'] ?? 0),
        ]));
        return response()->json(['ok' => true]);
    }

    /** @route PUT /api/tareas-puesto — modifica una asignación (body: puesto, des_orig, des, fre, min). */
    public function update(Request $request): JsonResponse
    {
        $d = $this->validar($request, true);
        $pue = trim($d['puesto']); $des = mb_strtoupper(trim($d['des'])); $orig = mb_strtoupper(trim($d['des_orig']));

        if ($des !== $orig && $this->existe($pue, $des)) {
            return response()->json(['message' => 'Ya existe esa tarea en el puesto.'], 422);
        }
        $afect = DB::table('tareapue')
            ->whereRaw('UPPER(RTRIM(TAR_PUE)) = ?', [mb_strtoupper($pue)])
            ->whereRaw('UPPER(RTRIM(TAR_DES)) = ?', [$orig])
            ->update(['TAR_DES' => $des, 'TAR_FRE' => (int) ($d['fre'] ?? 0), 'TAR_MIN' => (int) ($d['min'] ?? 0)]);
        if (!$afect) {
            return response()->json(['message' => 'Asignación no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route POST /api/tareas-puesto/eliminar — borra una asignación (body: puesto, des). */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['puesto' => 'required|string|max:15', 'des' => 'required|string|max:100']);
        DB::table('tareapue')
            ->whereRaw('UPPER(RTRIM(TAR_PUE)) = ?', [mb_strtoupper(trim($d['puesto']))])
            ->whereRaw('UPPER(RTRIM(TAR_DES)) = ?', [mb_strtoupper(trim($d['des']))])->delete();
        return response()->json(['ok' => true]);
    }

    private function existe(string $pue, string $des): bool
    {
        return DB::table('tareapue')
            ->whereRaw('UPPER(RTRIM(TAR_PUE)) = ?', [mb_strtoupper($pue)])
            ->whereRaw('UPPER(RTRIM(TAR_DES)) = ?', [mb_strtoupper($des)])->exists();
    }

    private function validar(Request $request, bool $edit = false): array
    {
        $reglas = [
            'des'    => 'required|string|max:100',
            'puesto' => 'required|string|max:15',
            'fre'    => 'nullable|integer',
            'min'    => 'nullable|integer|min:0|max:100',
        ];
        if ($edit) $reglas['des_orig'] = 'required|string|max:100';
        return $request->validate($reglas);
    }
}
