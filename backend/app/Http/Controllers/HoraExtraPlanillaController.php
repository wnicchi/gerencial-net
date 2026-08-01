<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HoraExtraPlanillaController — Planillas mensuales de Horas Extras (tabla horas_extras).
 *
 * CREAR: elige empleados (con filtros) + período, totaliza las horas extras diarias
 * confirmadas, calcula valores/brutos/total y crea una planilla numerada.
 * EDITAR: carga una planilla por número, permite ajustar horas/valores (recalcula
 * brutos y total) y guarda los cambios.
 *
 * Las horas extra diarias (HRE_EST50/100/ESTNOC50) están en formato HHMM (230 = 2:30).
 */
class HoraExtraPlanillaController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────────────────

    /** Empleados filtrados para armar la planilla. @route GET /api/planillas-hs-extras/empleados */
    public function empleados(Request $request): JsonResponse
    {
        $q = DB::table('personal')->where('PER_AOP', '!=', 'P')->where('PER_CHE', 'S');
        $mapa = ['empresa' => 'PER_EMP', 'contratista' => 'PER_CONTRA', 'lugar' => 'PER_LUGAR',
            'convenio' => 'PER_CON', 'categoria' => 'PER_CAT', 'sector' => 'PER_SEC', 'subsector' => 'PER_SUC'];
        foreach ($mapa as $param => $col) {
            $v = (int) $request->input($param, 0);
            if ($v > 0) $q->where($col, $v);
        }
        switch ((int) $request->input('orden', 1)) {
            case 2: $q->orderBy('PER_LEG'); break;
            case 3: $q->orderBy('PER_ING'); break;
            case 4: $q->orderByRaw('MONTH(PER_FNA), DAY(PER_FNA)'); break;
            default: $q->orderBy('PER_NOM');
        }
        $rows = $q->get(['PER_LEG', 'PER_NOM', 'PER_TDO', 'PER_NDO', 'PER_CUI', 'PER_DOM', 'PER_COD'])
            ->map(fn ($p) => [
                'es_ok'  => true,
                'cod'    => (int) $p->PER_COD,
                'legajo' => trim((string) $p->PER_LEG),
                'nombre' => trim((string) $p->PER_NOM),
                'tipo_doc' => trim((string) $p->PER_TDO),
                'nro_doc'  => trim((string) $p->PER_NDO),
                'cuil'   => trim((string) $p->PER_CUI),
                'domicilio' => trim((string) $p->PER_DOM),
            ]);
        return response()->json($rows);
    }

    /** Calcula la planilla (sin grabar). @route POST /api/planillas-hs-extras/generar */
    public function generar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigos'   => 'required|array|min:1',
            'codigos.*' => 'integer',
            'fecha1'    => 'required|date',
            'fecha2'    => 'required|date',
        ]);
        return response()->json(['rows' => $this->computar($datos['codigos'], $datos['fecha1'], $datos['fecha2'])]);
    }

    /** Crea la planilla numerada en horas_extras. @route POST /api/planillas-hs-extras/crear */
    public function crear(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigos'   => 'required|array|min:1',
            'codigos.*' => 'integer',
            'fecha1'    => 'required|date',
            'fecha2'    => 'required|date',
            'nombre'    => 'required|string|max:60',
        ]);
        $rows = $this->computar($datos['codigos'], $datos['fecha1'], $datos['fecha2']);
        if (empty($rows)) return response()->json(['message' => 'No hay datos para crear la planilla.'], 422);

        $nombre = mb_strtoupper(trim($datos['nombre']));
        $f1 = Carbon::parse($datos['fecha1'])->startOfDay();
        $f2 = Carbon::parse($datos['fecha2'])->startOfDay();
        $usuario = mb_strtoupper((string) ($request->user()->NOMBRE ?? $request->user()->name ?? ''));
        $nro = ((int) DB::table('horas_extras')->max('HRE_NUMERO')) + 1;
        $hoy = Carbon::today();

        DB::transaction(function () use ($rows, $nro, $nombre, $usuario, $hoy, $f1, $f2) {
            foreach ($rows as $r) {
                DB::table('horas_extras')->insert(Registro::completar('horas_extras', [
                    'HRE_NUMERO' => $nro, 'HRE_PLANILLA' => $nombre, 'HRE_FECHA' => $hoy,
                    'HRE_CREADA' => $usuario, 'HRE_SECTOR' => $r['sector'], 'HRE_NOMBRE' => $r['nombre'],
                    'HRE_HS_50' => $r['hs_50'], 'HRE_HS_100' => $r['hs_100'], 'HRE_HS_NOC' => $r['hs_noc'],
                    'HRE_HS_NOC50' => $r['hs_noc50'], 'HRE_HS_DIA30' => $r['hs_dia30'],
                    'HRE_VALOR_NOR' => $r['valor_nor'], 'HRE_VALOR_NOC' => $r['valor_noc'],
                    'HRE_VALOR_NOC50' => $r['valor_noc50'], 'HRE_VALOR_DIA30' => $r['valor_dia30'],
                    'HRE_VALOR_EXTRA50' => $r['valor_extra50'], 'HRE_VALOR_EXTRA100' => $r['valor_extra100'],
                    'HRE_BRUTO_EXTRA50' => $r['bruto_extra50'], 'HRE_BRUTO_EXTRA100' => $r['bruto_extra100'],
                    'HRE_BRUTO_NOC' => $r['bruto_noc'], 'HRE_BRUTO_NOC50' => $r['bruto_noc50'],
                    'HRE_BRUTO_DIA30' => $r['bruto_dia30'], 'HRE_TOTAL' => $r['total'], 'HRE_PER_COD' => $r['cod'],
                    'HRE_MODUSU' => '', 'HRE_FECHA1' => $f1, 'HRE_FECHA2' => $f2,
                ]));
            }
        });

        return response()->json(['ok' => true, 'numero' => $nro, 'creados' => count($rows)]);
    }

    /** Núcleo de cálculo de la planilla de trabajo. */
    private function computar(array $codigos, string $fecha1, string $fecha2): array
    {
        $codigos = array_map('intval', $codigos);
        $f1 = Carbon::parse($fecha1)->startOfDay();
        $f2 = Carbon::parse($fecha2)->startOfDay();

        // Totales de horas extras diarias por empleado (HHMM → minutos)
        $min50 = []; $min100 = []; $minNoc = [];
        foreach (DB::table('horas_extras_diaria')->whereIn('HRE_PER_COD', $codigos)
            ->whereBetween(DB::raw('CAST(HRE_FECHA AS DATE)'), [$f1->toDateString(), $f2->toDateString()])
            ->get(['HRE_PER_COD', 'HRE_EST50', 'HRE_EST100', 'HRE_ESTNOC50']) as $d) {
            $c = (int) $d->HRE_PER_COD;
            $min50[$c]  = ($min50[$c]  ?? 0) + $this->hhmmAMin((int) $d->HRE_EST50);
            $min100[$c] = ($min100[$c] ?? 0) + $this->hhmmAMin((int) $d->HRE_EST100);
            $minNoc[$c] = ($minNoc[$c] ?? 0) + $this->hhmmAMin((int) $d->HRE_ESTNOC50);
        }

        // Días viajados
        $diasViaje = [];
        foreach (DB::table('viajes')->whereIn('PVI_PER', $codigos)
            ->whereBetween(DB::raw('CAST(PVI_FEC AS DATE)'), [$f1->toDateString(), $f2->toDateString()])
            ->get(['PVI_PER', 'PVI_DIA']) as $v) {
            $diasViaje[(int) $v->PVI_PER] = ($diasViaje[(int) $v->PVI_PER] ?? 0) + (float) $v->PVI_DIA;
        }

        $valorFijo = (float) DB::table('parametro')->value('PAR_D30');

        $personas = DB::table('personal')->whereIn('PER_COD', $codigos)
            ->get(['PER_COD', 'PER_NOM', 'PER_SUE', 'PER_LUGAR', 'PER_CON'])->keyBy('PER_COD');
        $lugares = DB::table('lugar')->pluck('LUG_NOM', 'LUG_COD');
        $pne = DB::table('convenio')->pluck('CON_PNE', 'CON_COD');

        // Mantener el orden de selección (por nombre)
        $orden = $personas->sortBy(fn ($p) => trim((string) $p->PER_NOM))->keys()->all();

        $rows = [];
        foreach ($orden as $cod) {
            $cod = (int) $cod;
            $p = $personas[$cod] ?? null;
            if (!$p) continue;

            $hs50  = $this->minAHoras($min50[$cod] ?? 0);
            $hs100 = $this->minAHoras($min100[$cod] ?? 0);
            $hsNoc = $this->minAHoras($minNoc[$cod] ?? 0);
            $hsNoc50 = 0.0;                                   // (no se acumula en el original)
            $hsDia30 = (float) ($diasViaje[$cod] ?? 0);

            $valorNor   = (float) $p->PER_SUE / 200;
            $valorNoc   = $valorNor * 1.1333;
            $valorNoc50 = $valorNoc * 1.5;
            $valorExtra50  = $valorNor * 1.5;
            $valorExtra100 = $valorNor * 2;

            $brutoExtra50  = $hs50 * $valorExtra50;
            $brutoExtra100 = $hs100 * $valorExtra100;
            $brutoNoc      = $hsNoc * $valorNoc;
            $brutoNoc50    = $hsNoc50 * $valorNoc50;
            $brutoDia30    = $valorFijo * $hsDia30;

            $porcNeto = (float) ($pne[(int) $p->PER_CON] ?? 0);
            $total = ($brutoExtra50 + $brutoExtra100 + $brutoNoc + $brutoNoc50 + $brutoDia30) / (1 + $porcNeto / 100);

            $rows[] = [
                'cod' => $cod, 'nombre' => trim((string) $p->PER_NOM),
                'sector' => mb_strtoupper(trim((string) ($lugares[(int) $p->PER_LUGAR] ?? ''))),
                'hs_50' => round($hs50, 2), 'hs_100' => round($hs100, 2), 'hs_noc' => round($hsNoc, 2),
                'hs_noc50' => round($hsNoc50, 2), 'hs_dia30' => round($hsDia30, 2),
                'valor_nor' => round($valorNor, 2), 'valor_extra50' => round($valorExtra50, 2),
                'valor_extra100' => round($valorExtra100, 2), 'valor_dia30' => round($valorFijo, 2),
                'valor_noc' => round($valorNoc, 2), 'valor_noc50' => round($valorNoc50, 2),
                'bruto_extra50' => round($brutoExtra50, 2), 'bruto_extra100' => round($brutoExtra100, 2),
                'bruto_noc' => round($brutoNoc, 2), 'bruto_noc50' => round($brutoNoc50, 2),
                'bruto_dia30' => round($brutoDia30, 2), 'total' => round($total, 2),
            ];
        }
        return $rows;
    }

    // ─────────────────────────────────────────────────────────
    // EDITAR
    // ─────────────────────────────────────────────────────────

    /** Carga una planilla por número. @route GET /api/planillas-hs-extras/{nro} */
    public function cargarPlanilla(int $nro): JsonResponse
    {
        $filas = DB::table('horas_extras')->where('HRE_NUMERO', $nro)->orderBy('HRE_NOMBRE')->get();
        if ($filas->isEmpty()) return response()->json(['message' => 'No existe la planilla N° ' . $nro], 404);

        $cab = $filas->first();
        $rows = $filas->map(fn ($r) => [
            'unico'          => (int) $r->UNICO,
            'legajo'         => (int) $r->HRE_PER_COD,
            'sector'         => trim((string) $r->HRE_SECTOR),
            'nombre'         => trim((string) $r->HRE_NOMBRE),
            'hs_50'          => (float) $r->HRE_HS_50,
            'hs_100'         => (float) $r->HRE_HS_100,
            'hs_noc'         => (float) $r->HRE_HS_NOC,
            'hs_noc50'       => (float) $r->HRE_HS_NOC50,
            'hs_dia30'       => (float) $r->HRE_HS_DIA30,
            'valor_nor'      => (float) $r->HRE_VALOR_NOR,
            'valor_extra50'  => (float) $r->HRE_VALOR_EXTRA50,
            'valor_extra100' => (float) $r->HRE_VALOR_EXTRA100,
            'valor_dia30'    => (float) $r->HRE_VALOR_DIA30,
            'valor_noc'      => (float) $r->HRE_VALOR_NOC,
            'valor_noc50'    => (float) $r->HRE_VALOR_NOC50,
            'bruto_extra50'  => (float) $r->HRE_BRUTO_EXTRA50,
            'bruto_extra100' => (float) $r->HRE_BRUTO_EXTRA100,
            'bruto_noc'      => (float) $r->HRE_BRUTO_NOC,
            'bruto_noc50'    => (float) $r->HRE_BRUTO_NOC50,
            'bruto_dia30'    => (float) $r->HRE_BRUTO_DIA30,
            'total'          => (float) $r->HRE_TOTAL,
        ]);

        return response()->json([
            'nombre' => trim((string) $cab->HRE_PLANILLA),
            'creada' => 'Creada el ' . Carbon::parse($cab->HRE_FECHA)->format('d/m/Y') . ' por ' . trim((string) $cab->HRE_CREADA),
            'fecha1' => $cab->HRE_FECHA1 ? Carbon::parse($cab->HRE_FECHA1)->format('d/m/Y') : '',
            'fecha2' => $cab->HRE_FECHA2 ? Carbon::parse($cab->HRE_FECHA2)->format('d/m/Y') : '',
            'rows'   => $rows,
        ]);
    }

    /** Guarda los cambios de una planilla. @route POST /api/planillas-hs-extras/guardar */
    public function guardarPlanilla(Request $request): JsonResponse
    {
        $request->validate([
            'rows'         => 'required|array|min:1',
            'rows.*.unico' => 'required|integer',
        ]);
        $rows = $request->input('rows');   // filas completas (validate() devuelve solo lo validado)
        $num = fn ($r, $k) => (float) ($r[$k] ?? 0);
        DB::transaction(function () use ($rows, $num) {
            foreach ($rows as $r) {
                DB::table('horas_extras')->where('UNICO', (int) $r['unico'])->update([
                    'HRE_HS_50' => $num($r, 'hs_50'), 'HRE_HS_100' => $num($r, 'hs_100'), 'HRE_HS_NOC' => $num($r, 'hs_noc'),
                    'HRE_HS_NOC50' => $num($r, 'hs_noc50'), 'HRE_HS_DIA30' => $num($r, 'hs_dia30'),
                    'HRE_VALOR_NOR' => $num($r, 'valor_nor'), 'HRE_VALOR_EXTRA50' => $num($r, 'valor_extra50'),
                    'HRE_VALOR_EXTRA100' => $num($r, 'valor_extra100'), 'HRE_VALOR_DIA30' => $num($r, 'valor_dia30'),
                    'HRE_VALOR_NOC' => $num($r, 'valor_noc'), 'HRE_VALOR_NOC50' => $num($r, 'valor_noc50'),
                    'HRE_BRUTO_EXTRA50' => $num($r, 'bruto_extra50'), 'HRE_BRUTO_EXTRA100' => $num($r, 'bruto_extra100'),
                    'HRE_BRUTO_NOC' => $num($r, 'bruto_noc'), 'HRE_BRUTO_NOC50' => $num($r, 'bruto_noc50'),
                    'HRE_BRUTO_DIA30' => $num($r, 'bruto_dia30'), 'HRE_TOTAL' => $num($r, 'total'),
                ]);
            }
        });
        return response()->json(['ok' => true, 'actualizados' => count($rows)]);
    }

    private function hhmmAMin(int $hhmm): int
    {
        return intdiv($hhmm, 100) * 60 + $hhmm % 100;
    }

    private function minAHoras(int $min): float
    {
        return intdiv($min, 60) + ($min % 60) / 60;
    }
}
