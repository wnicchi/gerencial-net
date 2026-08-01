<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * InformeCapacitacionController — Informes de capacitación (capacitacion_informes.scx).
 *
 * Consulta jornadas (capacitacion) con filtros por tema, disertante, eficacia,
 * modalidad y período, y por tipo de informe: historial, vencidas sin cerrar o
 * próximas a realizar. Opcionalmente detalla los empleados de cada jornada.
 */
class InformeCapacitacionController extends Controller
{
    /** @route GET /api/informe-cap/init — combos. */
    public function init(): JsonResponse
    {
        return response()->json([
            'temas' => DB::table('tema')->orderBy('TEM_DES')->get(['TEM_COD', 'TEM_DES'])
                ->map(fn ($t) => ['cod' => (int) $t->TEM_COD, 'nombre' => trim((string) $t->TEM_DES)])->values(),
            'disertantes' => DB::table('disertantes')->orderBy('dis_nom')->get(['dis_cod', 'dis_nom'])
                ->map(fn ($d) => ['cod' => (int) $d->dis_cod, 'nombre' => trim((string) $d->dis_nom)])->values(),
            'eficacias' => DB::table('eficacia')->orderBy('EFI_DES')->get(['EFI_COD', 'EFI_DES'])
                ->map(fn ($e) => ['cod' => (int) $e->EFI_COD, 'nombre' => trim((string) $e->EFI_DES)])->values(),
            'modalidades' => DB::table('capacitacion')->whereRaw("LTRIM(RTRIM(CAP_MODALIDAD)) <> ''")
                ->distinct()->orderBy('CAP_MODALIDAD')->pluck('CAP_MODALIDAD')->map(fn ($x) => trim((string) $x))->filter()->unique()->values(),
        ]);
    }

    /** @route GET /api/informe-cap?tema=&disertante=&eficacia=&modalidad=&periodo=&desde=&hasta=&tipo=&detallada= */
    public function consultar(Request $request): JsonResponse
    {
        $tema      = (int) $request->query('tema', 0);
        $disertante = (int) $request->query('disertante', 0);
        $eficacia  = (int) $request->query('eficacia', 0);
        $modalidad = trim((string) $request->query('modalidad', ''));
        $rango     = $request->query('periodo', 'hist') === 'rango';
        $tipo      = $request->query('tipo', 'historial'); // historial | vencidas | proximas
        $detallada = filter_var($request->query('detallada', 'false'), FILTER_VALIDATE_BOOL);
        $hoy       = now()->format('Y-m-d');

        $q = DB::table('capacitacion');
        if ($tema > 0)       $q->where('CAP_TEM_COD', $tema);
        if ($disertante > 0) $q->where('CAP_DISER_COD', $disertante);
        if ($modalidad !== '') $q->where(DB::raw('LTRIM(RTRIM(CAP_MODALIDAD))'), $modalidad);
        if ($rango) {
            $desde = substr((string) $request->query('desde', '1900-01-01'), 0, 10);
            $hasta = substr((string) $request->query('hasta', '2999-12-31'), 0, 10);
            $q->whereBetween('CAP_FEC', [$desde . ' 00:00:00', $hasta . ' 23:59:59']);
        }
        if ($tipo === 'vencidas') {
            // No cerradas cuyo fin de período ya pasó.
            $q->where('CAP_CERRADA', 0)->whereDate('CAP_PER_FIN', '<', $hoy)->whereYear('CAP_PER_FIN', '>', 1900);
        } elseif ($tipo === 'proximas') {
            // No cerradas que aún no empezaron.
            $q->where('CAP_CERRADA', 0)->whereDate('CAP_PER_INI', '>=', $hoy);
        }
        if ($eficacia > 0) {
            // Sólo jornadas con al menos un cursante con esa eficacia.
            $q->whereExists(function ($s) use ($eficacia) {
                $s->select(DB::raw(1))->from('cap_emp')->whereColumn('cap_emp.CAP_COD', 'capacitacion.CAP_COD')->where('cap_emp.EFI_COD', $eficacia);
            });
        }

        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $jornadas = $q->orderByDesc('CAP_FEC')->get()->map(fn ($c) => [
            'cod'          => (int) $c->CAP_COD,
            'capacitacion' => trim((string) $c->CAP_CAPA),
            'fecha'        => $fecha($c->CAP_FEC),
            'desde'        => $fecha($c->CAP_PER_INI),
            'hasta'        => $fecha($c->CAP_PER_FIN),
            'disertante'   => trim((string) $c->CAP_DISER_NOM),
            'duracion'     => trim((string) $c->CAP_DURA),
            'modalidad'    => trim((string) $c->CAP_MODALIDAD),
            'tematica'     => trim((string) $c->CAP_TEM_DET),
            'cerrada'      => (bool) $c->CAP_CERRADA,
        ])->values();

        // Detalle de empleados por jornada (opcional).
        if ($detallada && $jornadas->isNotEmpty()) {
            $cods = $jornadas->pluck('cod')->all();
            $emp = DB::table('cap_emp as ce')->join('personal as p', 'ce.PER_COD', '=', 'p.PER_COD')
                ->leftJoin('eficacia as ef', 'ce.EFI_COD', '=', 'ef.EFI_COD')
                ->whereIn('ce.CAP_COD', $cods)
                ->when($eficacia > 0, fn ($qq) => $qq->where('ce.EFI_COD', $eficacia))
                ->orderBy('p.PER_NOM')
                ->get(['ce.CAP_COD', 'p.PER_COD', 'p.PER_NOM', 'p.PER_CUI', 'ef.EFI_DES', 'ce.NO_PARTICIPO'])
                ->groupBy('CAP_COD');
            $jornadas = $jornadas->map(function ($j) use ($emp) {
                $j['empleados'] = collect($emp->get($j['cod'], []))->map(fn ($r) => [
                    'codigo'      => (int) $r->PER_COD,
                    'nombre'      => trim((string) $r->PER_NOM),
                    'cuil'        => trim((string) $r->PER_CUI),
                    'eficacia'    => trim((string) ($r->EFI_DES ?? '')),
                    'no_participo' => (bool) $r->NO_PARTICIPO,
                ])->values()->all();
                return $j;
            });
        }

        return response()->json(['jornadas' => $jornadas->values(), 'total' => $jornadas->count()]);
    }
}
