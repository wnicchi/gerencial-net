<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AsignarCapacitacionController — Asignar empleados a una capacitación
 * (capacitacion_asignar.scx).
 *
 * Asigna cursantes (cap_emp) a una jornada (capacitacion). Permite agregar por
 * grupo (filtros sobre personal activo) o individual, agregar a todos los
 * "necesitados" del tema (cap_nece) y eliminar cursantes seleccionados.
 *
 * cap_emp: CAP_COD, PER_COD, EFI_COD, NO_PARTICIPO, DISER_*, DURACION, MODALIDAD.
 * cap_nece: CNE_EMP, CNE_TEM, CNE_FEC (empleados que necesitan un tema).
 */
class AsignarCapacitacionController extends Controller
{
    /** @route GET /api/asignacion-cap/capacitacion/{cod} — cabecera + cursantes. */
    public function capacitacion(int $cod): JsonResponse
    {
        $c = DB::table('capacitacion')->where('CAP_COD', $cod)->first();
        if (!$c) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        return response()->json([
            'cabecera' => [
                'cod'          => (int) $c->CAP_COD,
                'capacitacion' => trim((string) $c->CAP_CAPA),
                'fecha'        => $fecha($c->CAP_FEC),
                'desde'        => $fecha($c->CAP_PER_INI),
                'hasta'        => $fecha($c->CAP_PER_FIN),
                'disertante'   => trim((string) $c->CAP_DISER_NOM),
                'duracion'     => trim((string) $c->CAP_DURA),
                'modalidad'    => trim((string) $c->CAP_MODALIDAD),
                'objetivo'     => trim((string) $c->CAP_OBJE),
                'tematica'     => trim((string) $c->CAP_TEM_DET),
                'tema_cod'     => (int) $c->CAP_TEM_COD,
            ],
            'cursantes' => $this->cursantes($cod),
        ]);
    }

    /** @route POST /api/asignacion-cap/{cod}/agregar — agrega por grupo o individual. */
    public function agregar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'lote'        => 'required|in:grupo,individuo',
            'empleado'    => 'nullable|integer',
            'empresa'     => 'nullable|integer',
            'contratista' => 'nullable|integer',
            'lugar'       => 'nullable|integer',
            'convenio'    => 'nullable|integer',
            'categoria'   => 'nullable|integer',
            'sector'      => 'nullable|integer',
            'subsector'   => 'nullable|integer',
        ]);
        if (!DB::table('capacitacion')->where('CAP_COD', $cod)->exists()) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }

        // Empleados candidatos (activos) según el lote.
        $q = DB::table('personal')->where('PER_AOP', 'A');
        if ($d['lote'] === 'individuo') {
            $q->where('PER_COD', (int) ($d['empleado'] ?? 0));
        } else {
            foreach ([
                'empresa' => 'PER_EMP', 'contratista' => 'PER_CONTRA', 'lugar' => 'PER_LUGAR',
                'convenio' => 'PER_CON', 'categoria' => 'PER_CAT', 'sector' => 'PER_SEC', 'subsector' => 'PER_SUC',
            ] as $key => $col) {
                if ((int) ($d[$key] ?? 0) > 0) {
                    $q->where($col, (int) $d[$key]);
                }
            }
        }
        $candidatos = $q->pluck('PER_COD')->map(fn ($x) => (int) $x)->all();

        // Ya asignados a esta capacitación.
        $yaAsignados = DB::table('cap_emp')->where('CAP_COD', $cod)->pluck('PER_COD')->map(fn ($x) => (int) $x)->all();
        $nuevos = array_values(array_diff($candidatos, $yaAsignados));

        foreach ($nuevos as $per) {
            DB::table('cap_emp')->insert(Registro::completar('cap_emp', [
                'CAP_COD' => $cod, 'PER_COD' => $per, 'EFI_COD' => 0,
            ]));
        }

        return response()->json(['agregados' => count($nuevos), 'cursantes' => $this->cursantes($cod)]);
    }

    /** @route POST /api/asignacion-cap/{cod}/necesitados — agrega los necesitados del tema. */
    public function necesitados(int $cod): JsonResponse
    {
        $c = DB::table('capacitacion')->where('CAP_COD', $cod)->first(['CAP_TEM_COD']);
        if (!$c) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }
        $tema = (int) $c->CAP_TEM_COD;
        $agregados = 0;

        DB::transaction(function () use ($cod, $tema, &$agregados) {
            $necesitados = DB::table('cap_nece')->where('CNE_TEM', $tema)->pluck('CNE_EMP')->map(fn ($x) => (int) $x)->all();
            $yaAsignados = DB::table('cap_emp')->where('CAP_COD', $cod)->pluck('PER_COD')->map(fn ($x) => (int) $x)->all();
            foreach ($necesitados as $per) {
                if (!in_array($per, $yaAsignados, true)) {
                    DB::table('cap_emp')->insert(Registro::completar('cap_emp', [
                        'CAP_COD' => $cod, 'PER_COD' => $per, 'EFI_COD' => 0,
                    ]));
                    $agregados++;
                }
                // Sale de la lista de necesitados del tema.
                DB::table('cap_nece')->where('CNE_TEM', $tema)->where('CNE_EMP', $per)->delete();
            }
        });

        return response()->json(['agregados' => $agregados, 'cursantes' => $this->cursantes($cod)]);
    }

    /** @route POST /api/asignacion-cap/{cod}/eliminar — quita cursantes seleccionados. */
    public function eliminar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['empleados' => 'required|array|min:1', 'empleados.*' => 'integer']);
        $tema = (int) DB::table('capacitacion')->where('CAP_COD', $cod)->value('CAP_TEM_COD');
        $emps = array_map('intval', $d['empleados']);

        DB::transaction(function () use ($cod, $tema, $emps) {
            DB::table('cap_emp')->where('CAP_COD', $cod)->whereIn('PER_COD', $emps)->delete();
            DB::table('cap_nece')->where('CNE_TEM', $tema)->whereIn('CNE_EMP', $emps)->delete();
        });

        return response()->json(['ok' => true, 'cursantes' => $this->cursantes($cod)]);
    }

    /** Cursantes asignados a la capacitación (cap_emp join personal). */
    private function cursantes(int $cod): array
    {
        return DB::table('cap_emp as ce')->join('personal as p', 'ce.PER_COD', '=', 'p.PER_COD')
            ->where('ce.CAP_COD', $cod)
            ->orderBy('p.PER_NOM')->orderBy('p.PER_COD')
            ->get(['p.PER_COD', 'p.PER_NOM', 'p.PER_CUI', 'p.PER_TEL', 'p.PER_CEL'])
            ->map(fn ($r) => [
                'codigo'   => (int) $r->PER_COD,
                'nombre'   => trim((string) $r->PER_NOM),
                'cuil'     => trim((string) $r->PER_CUI),
                'telefono' => trim(trim((string) $r->PER_TEL) . ' ' . trim((string) $r->PER_CEL)),
            ])->values()->all();
    }
}
