<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EstadisticaController — Tablero Gerencial / Estadísticas de RRHH.
 *
 * Un único endpoint (GET /api/estadisticas) que, para un período y un conjunto de filtros
 * (empresa, contratista, convenio, sector, categoría, lugar), devuelve los datasets de todos
 * los gráficos del tablero:
 *   • kpis            — totales del período (neto, haberes, deducciones, dotación, promedio)
 *   • sueldosPorMes   — neto/haberes/deducciones pagados por mes (LIQUIDAC + LIQ_ITE)
 *   • composicion     — masa salarial repartida por una dimensión (convenio por defecto)
 *   • dotacion        — activos / altas / bajas por mes (personal PER_ING/PER_BAJ)
 *   • horasExtras     — cantidad y costo de HE 50/100/nocturnas por mes (horas_extras)
 *   • ausentismo      — días de licencia/falta por tipo (reloj_faltas_diarias)
 *
 * El importe pagado se calcula como SUM(LIT_HAB - LIT_DED) sobre TODOS los tipos de liquidación
 * (incluidos ADELANTOS): refleja el dinero real que se pagó en el período.
 */
class EstadisticaController extends Controller
{
    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /** Rango máximo permitido (meses). El frontend además auto-ajusta las fechas para no superarlo. */
    private const MAX_MESES = 12;

    /** Columna de personal por la que se puede agrupar la composición. */
    private const DIMENSIONES = [
        'convenio'    => ['col' => 'p.PER_CON',    'desc' => 'p.PER_CDE'],
        'sector'      => ['col' => 'p.PER_SEC',    'desc' => 'p.PER_SED'],
        'categoria'   => ['col' => 'p.PER_CAT',    'desc' => 'p.PER_CAD'],
        'empresa'     => ['col' => 'p.PER_EMP',    'desc' => 'p.PER_EMD'],
        'contratista' => ['col' => 'p.PER_CONTRA', 'desc' => 'ct.CONT_DET', 'join' => ['contratista as ct', 'p.PER_CONTRA', 'ct.CONT_COD']],
        'lugar'       => ['col' => 'p.PER_LUGAR',  'desc' => 'lg.LUG_NOM',  'join' => ['lugar as lg', 'p.PER_LUGAR', 'lg.LUG_COD']],
    ];

    /** @route GET /api/estadisticas?fecha1=&fecha2=&empresa=&contratista=&convenio=&sector=&categoria=&lugar=&agrupar= */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'fecha1'      => 'required|date',
            'fecha2'      => 'required|date',
            'agrupar'     => 'nullable|in:convenio,sector,categoria,empresa,contratista,lugar',
            'tipoSueldo'  => 'nullable|integer',   // LIQ_TIP; 0/ausente = todos los tipos
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->endOfDay();
        if ($f1->gt($f2)) return response()->json(['message' => 'La fecha inicial no puede ser mayor que la final.'], 422);

        // Tope de rango (mismo criterio que el auto-ajuste del frontend: máximo 1 año, por día).
        if ($f2->copy()->startOfDay()->gt($f1->copy()->addMonths(self::MAX_MESES))) {
            return response()->json(['message' => 'El período es demasiado amplio (máximo ' . self::MAX_MESES . ' meses). Elegí un rango menor.'], 422);
        }

        $meses = $this->rangoMeses($f1, $f2);   // ['2026-06' => 'Jun 2026', ...]

        try {
            return response()->json([
                'periodo'       => $meses,
                'sueldosPorMes' => $this->sueldosPorMes($request, $f1, $f2, $meses),
                'kpis'          => $this->kpis($request, $f1, $f2),
                'composicion'   => $this->composicion($request, $f1, $f2, $d['agrupar'] ?? 'convenio'),
                'dotacion'      => $this->dotacion($request, $meses),
                'horasExtras'   => $this->horasExtras($request, $f1, $f2, $meses),
                'ausentismo'    => $this->ausentismo($request, $f1, $f2),
                'faltasEmpleado' => $this->faltasEmpleado($request, $f1, $f2),
                'liqFinales'    => $this->liqFinalesPorMes($request, $f1, $f2, $meses),
            ]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudieron calcular las estadísticas para ese período. Probá con un rango más chico; el detalle quedó registrado en el Log de Errores.'], 500);
        }
    }

    /**
     * @route GET /api/estadisticas/composicion — solo el gráfico de composición.
     * Se usa cuando el usuario cambia el "agrupar por" para no recalcular todo el tablero.
     */
    public function composicionEndpoint(Request $request): JsonResponse
    {
        $d = $request->validate([
            'fecha1'  => 'required|date',
            'fecha2'  => 'required|date',
            'agrupar' => 'nullable|in:convenio,sector,categoria,empresa,contratista,lugar',
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->endOfDay();
        try {
            return response()->json($this->composicion($request, $f1, $f2, $d['agrupar'] ?? 'convenio'));
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudo recalcular la composición.'], 500);
        }
    }

    /** Filtro por tipo de liquidación (LIQ_TIP). Solo para las consultas sobre LIQUIDAC (alias l). */
    private function filtroTipo($q, Request $request): void
    {
        if ($t = (int) $request->input('tipoSueldo', 0)) $q->where('l.LIQ_TIP', $t);
    }

    // ── Filtros comunes por dimensiones de personal (alias p) ────────
    private function aplicarFiltros($q, Request $request): void
    {
        foreach ([
            'empresa' => 'p.PER_EMP', 'contratista' => 'p.PER_CONTRA', 'convenio' => 'p.PER_CON',
            'sector' => 'p.PER_SEC', 'categoria' => 'p.PER_CAT', 'lugar' => 'p.PER_LUGAR',
        ] as $param => $col) {
            if ($v = (int) $request->input($param, 0)) $q->where($col, $v);
        }
    }

    /** Devuelve ['YYYY-MM' => 'Mmm YYYY'] para cada mes del rango. */
    private function rangoMeses(\Carbon\Carbon $f1, \Carbon\Carbon $f2): array
    {
        $out = [];
        for ($m = $f1->copy()->startOfMonth(); $m->lte($f2); $m->addMonth()) {
            $out[$m->format('Y-m')] = self::MESES[(int) $m->month] . ' ' . $m->year;
        }
        return $out;
    }

    // ── 1) Sueldos pagados por mes ───────────────────────────────────
    private function sueldosPorMes(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2, array $meses): array
    {
        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->whereBetween('l.LIQ_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request); $this->filtroTipo($q, $request);

        // Dinero real pagado: SUM(haberes - deducciones) sobre todos los tipos (adelantos incluidos).
        $rows = $q->selectRaw("
                YEAR(l.LIQ_FEC) as anio, MONTH(l.LIQ_FEC) as mes,
                SUM(li.LIT_HAB - li.LIT_DED) as neto,
                SUM(li.LIT_HAB) as haberes, SUM(li.LIT_DED) as deducciones,
                COUNT(DISTINCT l.LIQ_COD) as empleados
            ")
            ->groupByRaw('YEAR(l.LIQ_FEC), MONTH(l.LIQ_FEC)')->get();

        $porClave = [];
        foreach ($rows as $r) $porClave[sprintf('%04d-%02d', $r->anio, $r->mes)] = $r;

        $out = [];
        foreach ($meses as $clave => $label) {
            $r = $porClave[$clave] ?? null;
            $out[] = [
                'mes'         => $clave, 'label' => $label,
                'neto'        => $r ? round((float) $r->neto, 2) : 0,
                'haberes'     => $r ? round((float) $r->haberes, 2) : 0,
                'deducciones' => $r ? round((float) $r->deducciones, 2) : 0,
                'empleados'   => $r ? (int) $r->empleados : 0,
            ];
        }
        return $out;
    }

    /**
     * Empleados incluidos en un grupo de la composición (drill-down del gráfico).
     * Devuelve, ordenados por nombre, los empleados de la dimensión elegida cuyo
     * nombre de grupo coincide con {clave}, con su neto pagado en el período.
     *
     * @route GET /api/estadisticas/composicion-empleados?fecha1=&fecha2=&agrupar=&clave=&…filtros
     */
    public function composicionEmpleados(Request $request): JsonResponse
    {
        $d = $request->validate([
            'fecha1'  => 'required|date',
            'fecha2'  => 'required|date',
            'agrupar' => 'required|in:convenio,sector,categoria,empresa,contratista,lugar',
            'clave'   => 'nullable|string',
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->endOfDay();
        try {
            $dim = self::DIMENSIONES[$d['agrupar']];
            $descExpr = $dim['desc'] ? "LTRIM(RTRIM({$dim['desc']}))" : "CAST({$dim['col']} AS VARCHAR(20))";

            $q = DB::table('LIQUIDAC as l')
                ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
                ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
                ->whereBetween('l.LIQ_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
            if (!empty($dim['join'])) { [$tabla, $left, $right] = $dim['join']; $q->leftJoin($tabla, $left, '=', $right); }
            $this->aplicarFiltros($q, $request); $this->filtroTipo($q, $request);

            $clave = trim((string) ($d['clave'] ?? ''));
            if ($clave === '' || $clave === '(sin dato)') {
                $q->whereRaw("ISNULL(NULLIF({$descExpr}, ''), '') = ''");
            } else {
                $q->whereRaw("{$descExpr} = ?", [$clave]);
            }

            $rows = $q->selectRaw("
                    p.PER_LEG as legajo, LTRIM(RTRIM(p.PER_NOM)) as nombre,
                    SUM(li.LIT_HAB - li.LIT_DED) as neto
                ")
                ->groupByRaw('p.PER_LEG, LTRIM(RTRIM(p.PER_NOM))')
                ->orderByRaw('LTRIM(RTRIM(p.PER_NOM))')->get();

            $out = $rows->map(fn ($r) => [
                'legajo' => (int) $r->legajo,
                'nombre' => trim((string) $r->nombre),
                'neto'   => round((float) $r->neto, 2),
            ])->all();

            return response()->json(['clave' => $clave !== '' ? $clave : '(sin dato)', 'rows' => $out]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudo obtener el detalle del grupo.'], 500);
        }
    }

    /**
     * Movimientos de dotación de un mes (drill del gráfico): quiénes INGRESARON
     * (alta en el mes) y quiénes SE FUERON (baja en el mes), ordenados por nombre.
     *
     * @route GET /api/estadisticas/dotacion-movimientos?mes=YYYY-MM&…filtros
     */
    public function dotacionMovimientos(Request $request): JsonResponse
    {
        $d = $request->validate(['mes' => 'required|regex:/^\d{4}-\d{2}$/']);
        try {
            [$y, $mm] = array_map('intval', explode('-', $d['mes']));
            $iniMes = \Carbon\Carbon::create($y, $mm, 1)->startOfDay();
            $finMes = $iniMes->copy()->endOfMonth();

            $q = DB::table('personal as p')->select(['p.PER_LEG', 'p.PER_NOM', 'p.PER_ING', 'p.PER_BAJ']);
            $this->aplicarFiltros($q, $request);
            $empleados = $q->get();

            $altas = []; $bajas = [];
            foreach ($empleados as $e) {
                $ing = $this->fecha($e->PER_ING);
                $baj = $this->fecha($e->PER_BAJ);
                $bajaEfectiva = $baj && $baj->year > 1900 ? $baj : null;
                $fila = ['legajo' => (int) $e->PER_LEG, 'nombre' => trim((string) $e->PER_NOM)];
                if ($ing && $ing->between($iniMes, $finMes)) $altas[] = $fila + ['fecha' => $ing->format('Y-m-d')];
                if ($bajaEfectiva && $bajaEfectiva->between($iniMes, $finMes)) $bajas[] = $fila + ['fecha' => $bajaEfectiva->format('Y-m-d')];
            }
            $ordNom = fn ($a, $b) => strcmp($a['nombre'], $b['nombre']);
            usort($altas, $ordNom); usort($bajas, $ordNom);

            return response()->json(['mes' => $d['mes'], 'altas' => $altas, 'bajas' => $bajas]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudieron obtener los movimientos del mes.'], 500);
        }
    }

    /**
     * Horas extras de un mes por EMPLEADO (drill del gráfico): cuántas horas de cada
     * tipo (50 % / 100 % / nocturnas) hizo cada empleado y su costo, ordenado por nombre.
     *
     * @route GET /api/estadisticas/horas-extras-empleados?mes=YYYY-MM&…filtros
     */
    public function horasExtrasEmpleados(Request $request): JsonResponse
    {
        $d = $request->validate(['mes' => 'required|regex:/^\d{4}-\d{2}$/']);
        try {
            [$y, $mm] = array_map('intval', explode('-', $d['mes']));
            $iniMes = \Carbon\Carbon::create($y, $mm, 1)->startOfDay();
            $finMes = $iniMes->copy()->endOfMonth();

            $q = DB::table('horas_extras as h')->join('personal as p', 'h.HRE_PER_COD', '=', 'p.PER_COD')
                ->whereBetween('h.HRE_FECHA', [$iniMes->format('Y-m-d'), $finMes->format('Y-m-d')]);
            $this->aplicarFiltros($q, $request);
            $rows = $q->selectRaw("
                    p.PER_LEG as legajo, LTRIM(RTRIM(p.PER_NOM)) as nombre,
                    SUM(h.HRE_HS_50) as hs50, SUM(h.HRE_HS_100) as hs100,
                    SUM(h.HRE_HS_NOC + h.HRE_HS_NOC50) as hsnoc, SUM(h.HRE_TOTAL) as costo
                ")
                ->groupByRaw('p.PER_LEG, LTRIM(RTRIM(p.PER_NOM))')
                ->orderByRaw('LTRIM(RTRIM(p.PER_NOM))')->get();

            $out = $rows->map(fn ($r) => [
                'legajo' => (int) $r->legajo, 'nombre' => trim((string) $r->nombre),
                'hs50'   => round((float) $r->hs50, 1), 'hs100' => round((float) $r->hs100, 1),
                'hsnoc'  => round((float) $r->hsnoc, 1), 'total' => round((float) $r->hs50 + (float) $r->hs100 + (float) $r->hsnoc, 1),
                'costo'  => round((float) $r->costo, 2),
            ])->all();

            return response()->json(['mes' => $d['mes'], 'rows' => $out]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudieron obtener las horas extras del mes.'], 500);
        }
    }

    /**
     * Empleados que faltaron por un tipo de licencia (drill del gráfico de ausentismo):
     * cada empleado con la cantidad de días de ese tipo en el período, ordenado por nombre.
     *
     * @route GET /api/estadisticas/ausentismo-empleados?fecha1=&fecha2=&tipo=&…filtros
     */
    public function ausentismoEmpleados(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha1' => 'required|date', 'fecha2' => 'required|date', 'tipo' => 'nullable|string']);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->endOfDay();
        try {
            $q = DB::table('reloj_faltas_diarias as a')->join('personal as p', 'a.AFD_PER', '=', 'p.PER_COD')
                ->whereRaw('CAST(a.AFD_FE1 AS DATE) <= ?', [$f2->format('Y-m-d')])
                ->whereRaw('CAST(a.AFD_FE2 AS DATE) >= ?', [$f1->format('Y-m-d')]);
            $tipo = trim((string) ($d['tipo'] ?? ''));
            if ($tipo === '' || $tipo === '(sin tipo)') $q->whereRaw("ISNULL(NULLIF(LTRIM(RTRIM(a.AFD_LID)), ''), '') = ''");
            else $q->whereRaw('LTRIM(RTRIM(a.AFD_LID)) = ?', [$tipo]);
            $this->aplicarFiltros($q, $request);
            $rows = $q->get(['p.PER_LEG', 'p.PER_NOM', 'a.AFD_FE1', 'a.AFD_FE2']);

            $porEmp = [];
            foreach ($rows as $r) {
                $d1 = $this->fecha($r->AFD_FE1); $d2 = $this->fecha($r->AFD_FE2);
                if (!$d1 || !$d2) continue;
                if ($d1->lt($f1)) $d1 = $f1->copy();
                if ($d2->gt($f2)) $d2 = $f2->copy();
                $dias = (int) round($d1->diffInDays($d2)) + 1;
                if ($dias <= 0) continue;
                $leg = (int) $r->PER_LEG;
                if (!isset($porEmp[$leg])) $porEmp[$leg] = ['legajo' => $leg, 'nombre' => trim((string) $r->PER_NOM), 'dias' => 0];
                $porEmp[$leg]['dias'] += $dias;
            }
            $out = array_values($porEmp);
            usort($out, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

            return response()->json(['tipo' => $tipo !== '' ? $tipo : '(sin tipo)', 'rows' => $out]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudo obtener el detalle del ausentismo.'], 500);
        }
    }

    /**
     * Empleados con liquidación final en un mes (drill del gráfico): cada empleado
     * con su monto liquidado, ordenado por nombre.
     *
     * @route GET /api/estadisticas/liq-finales-empleados?mes=YYYY-MM&…filtros
     */
    public function liqFinalesEmpleados(Request $request): JsonResponse
    {
        $d = $request->validate(['mes' => 'required|regex:/^\d{4}-\d{2}$/']);
        try {
            [$y, $mm] = array_map('intval', explode('-', $d['mes']));
            $iniMes = \Carbon\Carbon::create($y, $mm, 1)->startOfDay();
            $finMes = $iniMes->copy()->endOfMonth();

            $q = DB::table('LIQUIDAC as l')
                ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
                ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
                ->where('l.LIQ_TIP', 5)
                ->whereBetween('l.LIQ_FEC', [$iniMes->format('Y-m-d'), $finMes->format('Y-m-d')]);
            $this->aplicarFiltros($q, $request);
            $rows = $q->selectRaw("
                    p.PER_LEG as legajo, LTRIM(RTRIM(p.PER_NOM)) as nombre,
                    SUM(li.LIT_HAB - li.LIT_DED) as monto
                ")
                ->groupByRaw('p.PER_LEG, LTRIM(RTRIM(p.PER_NOM))')
                ->orderByRaw('LTRIM(RTRIM(p.PER_NOM))')->get();

            $out = $rows->map(fn ($r) => ['legajo' => (int) $r->legajo, 'nombre' => trim((string) $r->nombre), 'monto' => round((float) $r->monto, 2)])->all();

            return response()->json(['mes' => $d['mes'], 'rows' => $out]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudieron obtener las liquidaciones finales del mes.'], 500);
        }
    }

    /**
     * Detalle de faltas de UN empleado (drill del ranking): qué tipos de licencia y
     * cuántos días de cada uno componen su total, ordenado por días desc.
     *
     * @route GET /api/estadisticas/faltas-empleado-detalle?fecha1=&fecha2=&legajo=&…filtros
     */
    public function faltasEmpleadoDetalle(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha1' => 'required|date', 'fecha2' => 'required|date', 'legajo' => 'required|integer']);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->endOfDay();
        try {
            $q = DB::table('reloj_faltas_diarias as a')->join('personal as p', 'a.AFD_PER', '=', 'p.PER_COD')
                ->where('p.PER_LEG', (int) $d['legajo'])
                ->whereRaw('CAST(a.AFD_FE1 AS DATE) <= ?', [$f2->format('Y-m-d')])
                ->whereRaw('CAST(a.AFD_FE2 AS DATE) >= ?', [$f1->format('Y-m-d')]);
            $this->aplicarFiltros($q, $request);
            $rows = $q->get(['a.AFD_LID', 'a.AFD_FE1', 'a.AFD_FE2']);

            $porTipo = [];
            foreach ($rows as $r) {
                $d1 = $this->fecha($r->AFD_FE1); $d2 = $this->fecha($r->AFD_FE2);
                if (!$d1 || !$d2) continue;
                if ($d1->lt($f1)) $d1 = $f1->copy();
                if ($d2->gt($f2)) $d2 = $f2->copy();
                $dias = (int) round($d1->diffInDays($d2)) + 1;
                if ($dias <= 0) continue;
                $tipo = trim((string) $r->AFD_LID) ?: '(sin tipo)';
                $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $dias;
            }
            arsort($porTipo);
            $out = [];
            foreach ($porTipo as $tipo => $dias) $out[] = ['tipo' => $tipo, 'dias' => (int) $dias];

            return response()->json(['legajo' => (int) $d['legajo'], 'rows' => $out]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudo obtener el detalle de faltas.'], 500);
        }
    }

    /**
     * Puntualidad: ranking de empleados con más llegadas tarde (Top N) + resumen de
     * cumplidores. Cálculo pesado (marcaciones vs turno), por eso es un endpoint aparte
     * que el tablero pide de forma diferida. Respeta los filtros del tablero.
     *
     * @route GET /api/estadisticas/puntualidad?fecha1=&fecha2=&…filtros
     */
    public function puntualidad(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha1' => 'required|date', 'fecha2' => 'required|date']);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();
        // El cálculo es pesado (marcaciones vs turno por empleado/día): si el rango supera
        // 3 meses se acota a los últimos 92 días (y se avisa en la respuesta), en vez de fallar.
        $acotado = false;
        if ($f1->diffInDays($f2) > 92) { $f1 = $f2->copy()->subDays(92); $acotado = true; }
        try {
            // Empleados a evaluar: activos que cumplen los filtros del tablero.
            $q = DB::table('personal as p')->where('p.PER_AOP', 'A');
            $this->aplicarFiltros($q, $request);
            $codigos = $q->pluck('p.PER_COD')->map(fn ($c) => (int) $c)->all();
            if (!$codigos) return response()->json(['top' => [], 'conTarde' => 0, 'sinTarde' => 0, 'evaluados' => 0]);

            // Cache 1 h por combinación de filtros + período (evita recalcular los ~25 s en cada apertura).
            $clave = 'punt_' . md5(config('database.connections.sqlsrv_rrhh.database') . '|' . implode(',', $codigos) . '|' . $f1->toDateString() . '|' . $f2->toDateString());
            $out = \Illuminate\Support\Facades\Cache::remember($clave, now()->addHour(), function () use ($codigos, $f1, $f2) {
                $agg = app(\App\Http\Controllers\HorasTrabajadasController::class)->rankingLlegadasTarde($codigos, $f1, $f2, 30, false);
                $evaluados = count($agg);
                $conTarde = array_values(array_filter($agg, fn ($e) => $e['minutos'] > 0));
                usort($conTarde, fn ($a, $b) => $b['minutos'] <=> $a['minutos']);
                $top = array_map(fn ($e) => ['legajo' => $e['legajo'], 'nombre' => $e['nombre'], 'minutos' => $e['minutos'], 'dias' => $e['dias']], array_slice($conTarde, 0, 15));
                return ['top' => $top, 'conTarde' => count($conTarde), 'sinTarde' => $evaluados - count($conTarde), 'evaluados' => $evaluados];
            });
            $out['acotado'] = $acotado;
            $out['desde'] = $f1->toDateString();
            $out['hasta'] = $f2->toDateString();
            return response()->json($out);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudo calcular la puntualidad.'], 500);
        }
    }

    /**
     * Drill de puntualidad: días con llegada tarde de UN empleado (fecha + minutos).
     *
     * @route GET /api/estadisticas/puntualidad-empleado?fecha1=&fecha2=&legajo=&…filtros
     */
    public function puntualidadEmpleado(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha1' => 'required|date', 'fecha2' => 'required|date', 'legajo' => 'required|integer']);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();
        if ($f1->diffInDays($f2) > 92) $f1 = $f2->copy()->subDays(92);   // mismo recorte que el ranking
        try {
            $cod = (int) DB::table('personal')->where('PER_LEG', (int) $d['legajo'])->value('PER_COD');
            if (!$cod) return response()->json(['rows' => []]);
            $agg = app(\App\Http\Controllers\HorasTrabajadasController::class)->rankingLlegadasTarde([$cod], $f1, $f2, 30, true);
            $rows = $agg[$cod]['detalle'] ?? [];
            $rows = array_map(fn ($x) => ['fecha' => $x['fecha'], 'minutos' => $x['minutos']], $rows);
            return response()->json(['rows' => $rows]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'ESTADISTICAS');
            return response()->json(['message' => 'No se pudo obtener el detalle de puntualidad.'], 500);
        }
    }

    // ── KPIs del período ─────────────────────────────────────────────
    private function kpis(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2): array
    {
        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->whereBetween('l.LIQ_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request); $this->filtroTipo($q, $request);
        $r = $q->selectRaw("
            SUM(li.LIT_HAB - li.LIT_DED) as neto,
            SUM(li.LIT_HAB) as haberes, SUM(li.LIT_DED) as deducciones,
            COUNT(DISTINCT l.LIQ_COD) as empleados
        ")->first();

        $emp = (int) ($r->empleados ?? 0);
        $neto = round((float) ($r->neto ?? 0), 2);
        return [
            'neto'        => $neto,
            'haberes'     => round((float) ($r->haberes ?? 0), 2),
            'deducciones' => round((float) ($r->deducciones ?? 0), 2),
            'empleados'   => $emp,
            'promedio'    => $emp > 0 ? round($neto / $emp, 2) : 0,
        ];
    }

    // ── 2) Composición de la masa salarial por dimensión ─────────────
    private function composicion(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2, string $agrupar): array
    {
        $dim = self::DIMENSIONES[$agrupar] ?? self::DIMENSIONES['convenio'];
        $descExpr = $dim['desc']
            ? "LTRIM(RTRIM({$dim['desc']}))"
            : "CAST({$dim['col']} AS VARCHAR(20))";

        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->whereBetween('l.LIQ_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
        // Algunas dimensiones (lugar, contratista) tienen el nombre en una tabla aparte.
        if (!empty($dim['join'])) { [$tabla, $left, $right] = $dim['join']; $q->leftJoin($tabla, $left, '=', $right); }
        $this->aplicarFiltros($q, $request); $this->filtroTipo($q, $request);

        $rows = $q->selectRaw("
                {$descExpr} as clave,
                SUM(li.LIT_HAB - li.LIT_DED) as monto,
                COUNT(DISTINCT l.LIQ_COD) as empleados
            ")
            ->groupByRaw($descExpr)->get();

        $out = [];
        foreach ($rows as $r) {
            $clave = trim((string) $r->clave);
            $out[] = ['clave' => $clave !== '' ? $clave : '(sin dato)', 'monto' => round((float) $r->monto, 2), 'empleados' => (int) $r->empleados];
        }
        usort($out, fn ($a, $b) => $b['monto'] <=> $a['monto']);
        return ['agrupar' => $agrupar, 'items' => $out];
    }

    // ── 3) Evolución de la dotación (activos / altas / bajas por mes) ─
    private function dotacion(Request $request, array $meses): array
    {
        // Se trae ingreso y baja de cada empleado que cumple los filtros y se cuenta por mes.
        $q = DB::table('personal as p')->select(['p.PER_ING', 'p.PER_BAJ', 'p.PER_AOP']);
        $this->aplicarFiltros($q, $request);
        $empleados = $q->get();

        $out = [];
        foreach ($meses as $clave => $label) {
            [$y, $mm] = array_map('intval', explode('-', $clave));
            $iniMes = \Carbon\Carbon::create($y, $mm, 1)->startOfDay();
            $finMes = $iniMes->copy()->endOfMonth();
            $activos = $altas = $bajas = 0;
            foreach ($empleados as $e) {
                $ing = $this->fecha($e->PER_ING);
                $baj = $this->fecha($e->PER_BAJ);
                if (!$ing) continue;
                $bajaEfectiva = $baj && $baj->year > 1900 ? $baj : null;
                // Activo en el mes: ingresó antes del fin de mes y no se fue antes del inicio.
                if ($ing->lte($finMes) && (!$bajaEfectiva || $bajaEfectiva->gte($iniMes))) $activos++;
                if ($ing->between($iniMes, $finMes)) $altas++;
                if ($bajaEfectiva && $bajaEfectiva->between($iniMes, $finMes)) $bajas++;
            }
            $out[] = ['mes' => $clave, 'label' => $label, 'activos' => $activos, 'altas' => $altas, 'bajas' => $bajas];
        }
        return $out;
    }

    // ── 4) Horas extras por mes (cantidad y costo) ───────────────────
    private function horasExtras(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2, array $meses): array
    {
        $q = DB::table('horas_extras as h')->join('personal as p', 'h.HRE_PER_COD', '=', 'p.PER_COD')
            ->whereBetween('h.HRE_FECHA', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request);
        $rows = $q->selectRaw("
                YEAR(h.HRE_FECHA) as anio, MONTH(h.HRE_FECHA) as mes,
                SUM(h.HRE_HS_50) as hs50, SUM(h.HRE_HS_100) as hs100, SUM(h.HRE_HS_NOC + h.HRE_HS_NOC50) as hsnoc,
                SUM(h.HRE_TOTAL) as costo
            ")
            ->groupByRaw('YEAR(h.HRE_FECHA), MONTH(h.HRE_FECHA)')->get();

        $porClave = [];
        foreach ($rows as $r) $porClave[sprintf('%04d-%02d', $r->anio, $r->mes)] = $r;

        $out = [];
        foreach ($meses as $clave => $label) {
            $r = $porClave[$clave] ?? null;
            $out[] = [
                'mes'   => $clave, 'label' => $label,
                'hs50'  => $r ? round((float) $r->hs50, 1) : 0,
                'hs100' => $r ? round((float) $r->hs100, 1) : 0,
                'hsnoc' => $r ? round((float) $r->hsnoc, 1) : 0,
                'costo' => $r ? round((float) $r->costo, 2) : 0,
            ];
        }
        return $out;
    }

    // ── 5) Ausentismo por tipo de licencia (días) ────────────────────
    private function ausentismo(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2): array
    {
        $q = DB::table('reloj_faltas_diarias as a')->join('personal as p', 'a.AFD_PER', '=', 'p.PER_COD')
            ->whereRaw('CAST(a.AFD_FE1 AS DATE) <= ?', [$f2->format('Y-m-d')])
            ->whereRaw('CAST(a.AFD_FE2 AS DATE) >= ?', [$f1->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request);
        $rows = $q->get(['a.AFD_LID', 'a.AFD_FE1', 'a.AFD_FE2']);

        $porTipo = [];
        foreach ($rows as $r) {
            $d1 = $this->fecha($r->AFD_FE1); $d2 = $this->fecha($r->AFD_FE2);
            if (!$d1 || !$d2) continue;
            if ($d1->lt($f1)) $d1 = $f1->copy();
            if ($d2->gt($f2)) $d2 = $f2->copy();
            $dias = (int) round($d1->diffInDays($d2)) + 1;   // diffInDays puede dar 8.9999…: redondear a días enteros
            if ($dias <= 0) continue;
            $tipo = trim((string) $r->AFD_LID) ?: '(sin tipo)';
            $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $dias;
        }
        arsort($porTipo);
        $items = [];
        foreach ($porTipo as $tipo => $dias) $items[] = ['tipo' => $tipo, 'dias' => (int) $dias];
        return ['items' => $items, 'totalDias' => (int) array_sum($porTipo)];
    }

    // ── 7) Liquidaciones finales por mes (LIQ_TIP = 5) ───────────────
    private function liqFinalesPorMes(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2, array $meses): array
    {
        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->where('l.LIQ_TIP', 5)   // 5 = LIQUIDACIÓN FINAL
            ->whereBetween('l.LIQ_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request);
        $rows = $q->selectRaw("
                YEAR(l.LIQ_FEC) as anio, MONTH(l.LIQ_FEC) as mes,
                SUM(li.LIT_HAB - li.LIT_DED) as monto, COUNT(DISTINCT l.LIQ_COD) as empleados
            ")
            ->groupByRaw('YEAR(l.LIQ_FEC), MONTH(l.LIQ_FEC)')->get();

        $porClave = [];
        foreach ($rows as $r) $porClave[sprintf('%04d-%02d', $r->anio, $r->mes)] = $r;

        $out = []; $total = 0.0; $totalEmp = 0;
        foreach ($meses as $clave => $label) {
            $r = $porClave[$clave] ?? null;
            $m = $r ? round((float) $r->monto, 2) : 0; $e = $r ? (int) $r->empleados : 0;
            $out[] = ['mes' => $clave, 'label' => $label, 'monto' => $m, 'empleados' => $e];
            $total += $m; $totalEmp += $e;
        }
        return ['items' => $out, 'total' => round($total, 2), 'empleados' => $totalEmp];
    }

    /**
     * @route GET /api/estadisticas/detalle-sueldos — desglose (una fila por liquidación) que forma
     * el gráfico de sueldos, para los filtros activos. Sirve para el "ojito" de trazabilidad.
     */
    public function detalleSueldos(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha1' => 'required|date', 'fecha2' => 'required|date', 'tipoSueldo' => 'nullable|integer']);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->endOfDay();

        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->whereBetween('l.LIQ_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request); $this->filtroTipo($q, $request);

        $total = (clone $q)->distinct()->count('l.LIQ_NRO');
        $tope = 4000;
        $rows = $q->selectRaw("
                l.LIQ_NRO, CAST(l.LIQ_FEC AS DATE) as fecha, p.PER_LEG as legajo, LTRIM(RTRIM(p.PER_NOM)) as nombre,
                LTRIM(RTRIM(l.LIQ_TID)) as tipo, SUM(li.LIT_HAB) as haberes, SUM(li.LIT_DED) as deducciones,
                SUM(li.LIT_HAB - li.LIT_DED) as neto
            ")
            ->groupByRaw('l.LIQ_NRO, CAST(l.LIQ_FEC AS DATE), p.PER_LEG, LTRIM(RTRIM(p.PER_NOM)), LTRIM(RTRIM(l.LIQ_TID))')
            ->orderByRaw('CAST(l.LIQ_FEC AS DATE)')->orderByRaw('LTRIM(RTRIM(p.PER_NOM))')
            ->limit($tope)->get();

        $out = $rows->map(fn ($r) => [
            'fecha' => substr((string) $r->fecha, 0, 10),
            'mes' => self::MESES[(int) \Carbon\Carbon::parse($r->fecha)->month] . ' ' . \Carbon\Carbon::parse($r->fecha)->year,
            'legajo' => (int) $r->legajo, 'nombre' => trim((string) $r->nombre), 'tipo' => trim((string) $r->tipo),
            'haberes' => round((float) $r->haberes, 2), 'deducciones' => round((float) $r->deducciones, 2), 'neto' => round((float) $r->neto, 2),
        ])->all();

        return response()->json(['rows' => $out, 'total' => $total, 'truncado' => $total > $tope, 'tope' => $tope]);
    }

    /** @route GET /api/estadisticas/tipos-sueldo — tipos de liquidación presentes (para el filtro). */
    public function tiposSueldo(): JsonResponse
    {
        // Un ítem por LIQ_TIP, con la descripción (LIQ_TID) más frecuente de ese tipo.
        $rows = DB::table('LIQUIDAC')
            ->selectRaw('LIQ_TIP as tip, LTRIM(RTRIM(LIQ_TID)) as tid, COUNT(*) as n')
            ->groupByRaw('LIQ_TIP, LTRIM(RTRIM(LIQ_TID))')->get();
        $porTip = [];
        foreach ($rows as $r) {
            $tip = (int) $r->tip;
            if (!isset($porTip[$tip]) || $r->n > $porTip[$tip]['n']) $porTip[$tip] = ['tip' => $tip, 'label' => trim((string) $r->tid) ?: ('Tipo ' . $tip), 'n' => (int) $r->n];
        }
        ksort($porTip);
        return response()->json(array_map(fn ($x) => ['tip' => $x['tip'], 'label' => $x['label']], array_values($porTip)));
    }

    // ── 6) Empleados con más días de falta (ranking, de más a menos) ─
    private function faltasEmpleado(Request $request, \Carbon\Carbon $f1, \Carbon\Carbon $f2): array
    {
        $q = DB::table('reloj_faltas_diarias as a')->join('personal as p', 'a.AFD_PER', '=', 'p.PER_COD')
            ->whereRaw('CAST(a.AFD_FE1 AS DATE) <= ?', [$f2->format('Y-m-d')])
            ->whereRaw('CAST(a.AFD_FE2 AS DATE) >= ?', [$f1->format('Y-m-d')]);
        $this->aplicarFiltros($q, $request);
        $rows = $q->get(['a.AFD_PER', 'p.PER_NOM', 'p.PER_LEG', 'a.AFD_FE1', 'a.AFD_FE2']);

        $porEmp = [];
        foreach ($rows as $r) {
            $d1 = $this->fecha($r->AFD_FE1); $d2 = $this->fecha($r->AFD_FE2);
            if (!$d1 || !$d2) continue;
            if ($d1->lt($f1)) $d1 = $f1->copy();
            if ($d2->gt($f2)) $d2 = $f2->copy();
            $dias = (int) round($d1->diffInDays($d2)) + 1;
            if ($dias <= 0) continue;
            $cod = (int) $r->AFD_PER;
            $porEmp[$cod] ??= ['legajo' => (int) $r->PER_LEG, 'nombre' => trim((string) $r->PER_NOM), 'dias' => 0];
            $porEmp[$cod]['dias'] += $dias;
        }
        usort($porEmp, fn ($a, $b) => $b['dias'] <=> $a['dias']);
        return ['items' => array_slice(array_values($porEmp), 0, 12)];   // top 12
    }

    private function fecha($v): ?\Carbon\Carbon
    {
        if (!$v) return null;
        try { return \Carbon\Carbon::parse($v)->startOfDay(); } catch (\Throwable) { return null; }
    }
}
