<?php

namespace App\Http\Controllers;

use App\Services\CostoLaboralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CostoGrupalController — Costos Laborales: Costos Grupales (empleados_costos_grupales.scx).
 *
 * Arma un grupo de empleados (agregando por código o por sector/subsector) y
 * calcula el costo laboral de cada uno (misma lógica que el Individual) con su total.
 */
class CostoGrupalController extends Controller
{
    /** @route GET /api/costos-grupales/sectores — combos de sector y subsector. */
    public function sectores(): JsonResponse
    {
        // sector/subsector tienen columnas en minúscula; se usan alias para leerlas sin depender del casing.
        return response()->json([
            'sectores' => DB::table('sector')->selectRaw('sec_cod AS cod, sec_des AS des')->orderBy('sec_des')->get()
                ->map(fn ($s) => ['cod' => (int) $s->cod, 'des' => trim((string) $s->des)])->values(),
            'subsectores' => DB::table('subsector')->selectRaw('sub_cod AS cod, sub_des AS des')->orderBy('sub_des')->get()
                ->map(fn ($s) => ['cod' => (int) $s->cod, 'des' => trim((string) $s->des)])->values(),
        ]);
    }

    /** @route GET /api/costos-grupales/empleado/{cod} — datos básicos para agregar por código. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_COD', 'PER_LEG', 'PER_NOM']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }
        return response()->json($this->fila($p));
    }

    /** @route GET /api/costos-grupales/buscar?sector=&subsector= — empleados activos del sector/subsector. */
    public function buscar(Request $request): JsonResponse
    {
        $d = $request->validate(['sector' => 'required|integer', 'subsector' => 'required|integer']);
        $rows = DB::table('personal')
            ->where('PER_SEC', $d['sector'])->where('PER_SUC', $d['subsector'])->where('PER_AOP', 'A')
            ->orderBy('PER_NOM')->get(['PER_COD', 'PER_LEG', 'PER_NOM'])
            ->map(fn ($p) => $this->fila($p))->values();
        return response()->json($rows);
    }

    /** @route POST /api/costos-grupales/calcular — costo de cada empleado del grupo. */
    public function calcular(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes'       => 'required|integer|min:1|max:12',
            'anio'      => 'required|integer|min:2000|max:2200',
            'codigos'   => 'required|array|min:1',
            'codigos.*' => 'integer',
        ]);

        $svc = new CostoLaboralService();
        $out = [];
        foreach (DB::table('personal')->whereIn('PER_COD', $d['codigos'])->orderBy('PER_NOM')->get() as $per) {
            $r = $svc->calcular($per, (int) $d['mes'], (int) $d['anio']);
            $prev = $svc->previsionIndividual($r['branch'], $r['bruto']);
            $costo = array_sum(array_column($r['rows'], 'importe'))
                   + $prev['importe']
                   + array_sum(array_column($r['gastos'], 'importe'));
            $out[] = [
                'codigo' => (int) $per->PER_COD,
                'legajo' => trim((string) $per->PER_LEG),
                'nombre' => trim((string) $per->PER_NOM),
                'costo'  => round($costo, 2),
            ];
        }

        return response()->json([
            'empleados' => $out,
            'total'     => round(array_sum(array_column($out, 'costo')), 2),
        ]);
    }

    private function fila($p): array
    {
        return [
            'codigo' => (int) $p->PER_COD,
            'legajo' => trim((string) $p->PER_LEG),
            'nombre' => trim((string) $p->PER_NOM),
        ];
    }
}
