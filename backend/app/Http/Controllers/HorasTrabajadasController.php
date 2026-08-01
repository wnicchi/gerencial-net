<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HorasTrabajadasController — Reloj / Consulta de Horas Trabajadas (reloj_horarios_trabajados.scx).
 *
 * Calcula, para cada empleado seleccionado y cada día del rango, las horas trabajadas a partir de las
 * marcaciones del reloj, el turno del grupo (reloj_grupos), los ajustes manuales (reloj_ajustes), las
 * faltas/licencias (reloj_faltas_diarias) y las vacaciones. Determina horas normales, extras al 50% y al
 * 100% (feriados y sábado tarde), adicional nocturno y extra nocturno.
 *
 * Dos modos:
 *   • "estandar"  — el día se toma por la fecha de la marcación.
 *   • "logistica" — la jornada va de las 04:00 a las 03:59 del día siguiente (turnos que cruzan medianoche).
 */
class HorasTrabajadasController extends Controller
{
    private const DIAS = [1 => 'DOMINGO', 2 => 'LUNES', 3 => 'MARTES', 4 => 'MIERCOLES', 5 => 'JUEVES', 6 => 'VIERNES', 7 => 'SABADO'];

    /** @route GET /api/reloj/horas-trabajadas/empleados — lista de empleados filtrada (TRAER EMPLEADOS). */
    public function empleados(Request $request): JsonResponse
    {
        $orden = (int) ($request->input('orden', 1));
        $q = DB::table('personal')->where('PER_AOP', '!=', 'P');
        foreach ([['empresa', 'PER_EMP'], ['contratista', 'PER_CONTRA'], ['lugar', 'PER_LUGAR'], ['convenio', 'PER_CON'], ['categoria', 'PER_CAT'], ['sector', 'PER_SEC'], ['subsector', 'PER_SUC']] as [$p, $col]) {
            if ($request->filled($p)) $q->where($col, (int) $request->input($p));
        }
        match ($orden) {
            2 => $q->orderBy('PER_LEG'),
            3 => $q->orderBy('PER_ING'),
            4 => $q->orderByRaw('MONTH(PER_FNA)')->orderByRaw('DAY(PER_FNA)'),
            default => $q->orderBy('PER_NOM'),
        };

        $filas = $q->get(['PER_COD', 'PER_LEG', 'PER_NOM', 'PER_TDO', 'PER_NDO', 'PER_CUI', 'PER_DOM', 'PER_CON'])->map(fn ($p) => [
            'cod' => (int) $p->PER_COD, 'legajo' => (int) $p->PER_LEG, 'nombre' => trim((string) $p->PER_NOM),
            'tdoc' => trim((string) $p->PER_TDO), 'ndoc' => trim((string) $p->PER_NDO), 'cuil' => trim((string) $p->PER_CUI),
            'domicilio' => trim((string) $p->PER_DOM), 'convenio' => (int) $p->PER_CON, 'sel' => true,
        ])->values();

        return response()->json(['total' => $filas->count(), 'empleados' => $filas]);
    }

    /** @route POST /api/reloj/horas-trabajadas/generar — calcula las horas (GENERAR TURNOS). */
    public function generar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigos'   => 'required|array|min:1',
            'codigos.*' => 'integer',
            'fecha1'    => 'required|date',
            'fecha2'    => 'required|date',
            'modo'      => 'nullable|in:estandar,logistica',
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();
        $logistica = ($d['modo'] ?? 'estandar') === 'logistica';
        $codigos = array_map('intval', $d['codigos']);

        $ctx = $this->cargarContexto($codigos, $f1, $f2);
        $filas = [];
        foreach ($codigos as $cod) {
            $p = $ctx['personas']->get($cod);
            if (!$p) continue;
            foreach ($this->diasEmpleado($cod, $p, $f1, $f2, $logistica, $ctx) as $r) $filas[] = $r;
        }

        // Orden por nombre, fecha (como el FoxPro).
        usort($filas, fn ($a, $b) => [$a['nombre'], $a['fecha']] <=> [$b['nombre'], $b['fecha']]);

        // Resumen por empleado.
        $resumen = [];
        foreach ($filas as $r) {
            $k = $r['cod'];
            $resumen[$k] ??= ['cod' => $r['cod'], 'legajo' => $r['legajo'], 'nombre' => $r['nombre'], 'trab' => 0, 'h50' => 0, 'h100' => 0, 'adicNoc' => 0, 'extraNoc' => 0, 'total' => 0, 'dias' => 0];
            $resumen[$k]['trab'] += $r['trab']; $resumen[$k]['h50'] += $r['h50']; $resumen[$k]['h100'] += $r['h100'];
            $resumen[$k]['adicNoc'] += $r['adicNoc']; $resumen[$k]['extraNoc'] += $r['extraNoc']; $resumen[$k]['total'] += $r['total'];
            if ($r['entra1'] > 0) $resumen[$k]['dias']++;
        }

        return response()->json(['detalle' => $filas, 'resumen' => array_values($resumen), 'cabeceras' => $this->cabeceras($codigos)]);
    }

    /** Datos de encabezado por empleado (para el impreso, réplica del frx de Fox). */
    private function cabeceras(array $codigos): array
    {
        $rows = DB::table('personal as p')
            ->leftJoin('estadocivil as e', 'p.PER_ECI', '=', 'e.ECI_COD')
            ->whereIn('p.PER_COD', $codigos)
            ->get([
                'p.PER_COD', 'p.PER_LEG', 'p.PER_NOM', 'p.PER_DOM', 'p.PER_LOC', 'p.PER_EMD',
                'p.PER_CDE', 'p.PER_CAD', 'p.PER_ING', 'p.PER_FNA', 'p.PER_BAJ', 'p.PER_CBU',
                'p.PER_TDO', 'p.PER_NDO', 'p.PER_CUI', 'p.PER_TEL', 'p.PER_CEL', 'p.PER_NES', 'e.ECI_DES',
            ]);
        $fd = fn ($v) => ($v && substr((string) $v, 0, 4) > '1900') ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '';
        $out = [];
        foreach ($rows as $p) {
            $out[(int) $p->PER_COD] = [
                'codigo' => (int) $p->PER_COD, 'legajo' => (int) $p->PER_LEG, 'nombre' => trim((string) $p->PER_NOM),
                'domicilio' => trim((string) $p->PER_DOM), 'localidad' => trim((string) $p->PER_LOC), 'empresa' => trim((string) $p->PER_EMD),
                'convenio' => trim((string) $p->PER_CDE), 'categoria' => trim((string) $p->PER_CAD),
                'ingreso' => $fd($p->PER_ING), 'nacimiento' => $fd($p->PER_FNA), 'baja' => $fd($p->PER_BAJ),
                'cbu' => trim((string) $p->PER_CBU), 'tdoc' => trim((string) $p->PER_TDO), 'ndoc' => trim((string) $p->PER_NDO),
                'cuil' => trim((string) $p->PER_CUI), 'telefono' => trim((string) $p->PER_TEL), 'celular' => trim((string) $p->PER_CEL),
                'conyuge' => trim((string) $p->PER_NES), 'estadoCivil' => trim((string) ($p->ECI_DES ?? '')),
            ];
        }
        return $out;
    }

    /** @route POST /api/reloj/parte-diario — parte diario de una fecha (entradas + observaciones). */
    public function parteDiario(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigos' => 'required|array|min:1', 'codigos.*' => 'integer',
            'fecha' => 'required|date', 'noRegistrados' => 'nullable|boolean',
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha'])->startOfDay();
        $codigos = array_map('intval', $d['codigos']);
        $ctx = $this->cargarContexto($codigos, $f1, $f1);

        $filas = [];
        foreach ($codigos as $cod) {
            $p = $ctx['personas']->get($cod);
            if (!$p) continue;
            $dias = $this->diasEmpleado($cod, $p, $f1, $f1, false, $ctx);
            $r = reset($dias);
            $obs = '';
            if ($r['esFalta']) $obs = $r['queFalta'];
            $filas[] = [
                'cod' => $cod, 'legajo' => (int) $p->PER_LEG, 'nombre' => trim((string) $p->PER_NOM),
                'entra1' => $r['entra1'], 'entra2' => $r['entra2'], 'observa' => $obs,
            ];
        }
        if ($d['noRegistrados'] ?? false) $filas = array_values(array_filter($filas, fn ($x) => $x['entra1'] === 0 && $x['observa'] === ''));
        usort($filas, fn ($a, $b) => $a['nombre'] <=> $b['nombre']);

        return response()->json(['fecha' => $f1->format('Y-m-d'), 'total' => count($filas), 'parte' => $filas]);
    }

    /** @route POST /api/reloj/llegadas-tarde — informe de llegadas/salidas tarde por período. */
    public function llegadasTarde(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigos' => 'required|array|min:1', 'codigos.*' => 'integer',
            'fecha1' => 'required|date', 'fecha2' => 'required|date',
            'maxTarde' => 'nullable|integer', 'maxSalida' => 'nullable|integer',
            'negativos' => 'nullable|boolean',
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();
        $maxTarde = (int) ($d['maxTarde'] ?? 30);
        $maxSalida = (int) ($d['maxSalida'] ?? 60);
        $negativos = (bool) ($d['negativos'] ?? false);
        $codigos = array_map('intval', $d['codigos']);
        $ctx = $this->cargarContexto($codigos, $f1, $f2);

        $detalle = [];
        foreach ($codigos as $cod) {
            $p = $ctx['personas']->get($cod);
            if (!$p) continue;
            foreach ($this->diasEmpleado($cod, $p, $f1, $f2, false, $ctx) as $r) {
                $llegada = 0; $salida = 0;
                $mt = $r['entra1'] % 100;
                if ($mt > 0 && $mt < $maxTarde && $r['entra1'] > $r['horaEntrada']) $llegada = $mt;
                $mt = $r['sale1'] % 100;
                if ($mt > 0 && $mt < $maxSalida && $r['sale1'] > $r['horaSalida']) $salida = $mt;
                $dif = ($llegada > 0 || $salida > 0) ? $llegada - $salida : 0;
                $incluir = $negativos ? ($dif !== 0) : ($dif > 0);
                if (!$incluir) continue;
                $detalle[] = [
                    'legajo' => $r['legajo'], 'nombre' => $r['nombre'], 'fecha' => $r['fecha'],
                    'minLlegadaTarde' => $llegada, 'minSalidaTarde' => $salida, 'difMinutos' => $dif,
                ];
            }
        }
        usort($detalle, fn ($a, $b) => [$a['nombre'], $a['fecha']] <=> [$b['nombre'], $b['fecha']]);

        $resumen = [];
        foreach ($detalle as $r) {
            $resumen[$r['nombre']] ??= ['legajo' => $r['legajo'], 'nombre' => $r['nombre'], 'difMinutos' => 0];
            $resumen[$r['nombre']]['difMinutos'] += $r['difMinutos'];
        }
        return response()->json(['detalle' => $detalle, 'resumen' => array_values($resumen)]);
    }

    /** @route POST /api/reloj/secretaria-trabajo — planilla para Secretaría de Trabajo (pares de fichadas). */
    public function secretaria(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigos' => 'required|array|min:1', 'codigos.*' => 'integer',
            'fecha1' => 'required|date', 'fecha2' => 'required|date',
        ]);
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();
        if ($f1->gt($f2)) return response()->json(['message' => 'La fecha de inicio no puede ser mayor que la final.'], 422);
        $codigos = array_map('intval', $d['codigos']);

        $personas = DB::table('personal as p')->leftJoin('empresas as e', 'p.PER_EMP', '=', 'e.EMP_COD')
            ->whereIn('p.PER_COD', $codigos)->get(['p.PER_COD', 'p.PER_LEG', 'p.PER_NOM', 'p.PER_CUI', 'e.EMP_NOM', 'e.EMP_CUI'])
            ->keyBy(fn ($x) => (int) $x->PER_COD);
        $ajustes = DB::table('reloj_ajustes')->whereIn('AJR_PER', $codigos)->whereBetween('AJR_FEC', [$f1, $f2])->get();
        $faltas = DB::table('reloj_faltas_diarias')->whereIn('AFD_PER', $codigos)->get(['AFD_PER', 'AFD_FE1', 'AFD_FE2', 'AFD_LID']);
        $vacas = DB::table('vacaciones')->whereIn('VAC_PER', $codigos)->where('VAC_FDE', '>', $f1->copy()->subDays(60))->get(['VAC_PER', 'VAC_FDE', 'VAC_FHA']);
        $periodo = self::MESES_NOMBRE[(int) $f1->month] . ' ' . $f1->year;

        $filas = [];
        foreach ($codigos as $cod) {
            $p = $personas->get($cod);
            if (!$p) continue;
            // Marcaciones del rango (hasta f2+1) + ajustes como pseudo-marcaciones.
            $marcas = DB::table('reloj')->where('rel_cod', $cod)->where('ignorar', 0)
                ->whereBetween('rel_fec', [$f1->format('Y-m-d') . ' 00:00:00', $f2->copy()->addDay()->format('Y-m-d') . ' 23:59:59'])
                ->orderBy('rel_fec')->pluck('rel_fec')->map(fn ($x) => ['fec' => \Carbon\Carbon::parse($x), 'aj' => false])->all();
            foreach ($ajustes as $a) {
                if ((int) $a->AJR_PER !== $cod) continue;
                $fa = \Carbon\Carbon::parse($a->AJR_FEC);
                foreach ([[$a->AJR_VE1, $a->AJR_HEN1], [$a->AJR_VS1, $a->AJR_HSA1], [$a->AJR_VE2, $a->AJR_HEN2], [$a->AJR_VS2, $a->AJR_HSA2]] as [$fl, $h]) {
                    if (!(int) $fl) continue;
                    $hh = (int) round((float) $h);
                    $marcas[] = ['fec' => $fa->copy()->setTime(intdiv($hh, 100), $hh % 100, 0), 'aj' => true];
                }
            }
            usort($marcas, fn ($a, $b) => $a['fec'] <=> $b['fec']);
            // Descartar la primera si es antes de las 03:00 (jornada del día anterior sin par).
            while (!empty($marcas) && (int) $marcas[0]['fec']->hour < 3) array_shift($marcas);

            // Construir días.
            $dias = [];
            for ($f = $f1->copy(); $f->lte($f2); $f->addDay()) {
                $dias[$f->format('Y-m-d')] = [
                    'cod' => $cod, 'legajo' => (int) $p->PER_LEG, 'nombre' => trim((string) $p->PER_NOM), 'cuil' => trim((string) $p->PER_CUI),
                    'fecha' => $f->format('Y-m-d'), 'dia' => substr(self::DIAS[$this->vfpDow($f->format('Y-m-d'))], 0, 2) . ' ' . $f->format('d'),
                    'entra1' => '', 'sale1' => '', 'entra2' => '', 'sale2' => '', 'totalSeg' => 0, 'observa' => '', 'ajuste' => false,
                    'empresa' => trim((string) ($p->EMP_NOM ?? '')), 'empresaCuit' => trim((string) ($p->EMP_CUI ?? '')), 'periodo' => $periodo,
                ];
            }
            // Asignar pares (entra/sale) por fecha-corregida (antes de 03:00 -> día anterior).
            $cuantas = [];
            foreach ($marcas as $m) {
                $fec = $m['fec'];
                $key = ((int) $fec->hour >= 3) ? $fec->format('Y-m-d') : $fec->copy()->subDay()->format('Y-m-d');
                if (!isset($dias[$key])) continue;
                $c = $cuantas[$key] ?? 0;
                $r =& $dias[$key];
                $hm = $fec->format('H:i');
                if ($c === 0) { $r['entra1'] = $hm; $r['_e1'] = $fec->copy(); }
                elseif ($c === 1) { $r['sale1'] = $hm; $r['totalSeg'] += $this->difSeg($r['_e1'] ?? null, $fec); }
                elseif ($c === 2) { $r['entra2'] = $hm; $r['_e2'] = $fec->copy(); }
                else { $r['sale2'] = $hm; $r['totalSeg'] += $this->difSeg($r['_e2'] ?? null, $fec); }
                if ($m['aj']) $r['ajuste'] = true;
                $cuantas[$key] = ($c + 1) > 3 ? 0 : $c + 1;
                unset($r);
            }
            // Observaciones: faltas y vacaciones.
            foreach ($dias as $key => &$r) {
                foreach ($faltas as $x) {
                    if ((int) $x->AFD_PER === $cod && $key >= \Carbon\Carbon::parse($x->AFD_FE1)->format('Y-m-d') && $key <= \Carbon\Carbon::parse($x->AFD_FE2)->format('Y-m-d')) {
                        $r['observa'] = trim((string) $x->AFD_LID);
                    }
                }
                foreach ($vacas as $x) {
                    if ((int) $x->VAC_PER === $cod && $key >= \Carbon\Carbon::parse($x->VAC_FDE)->format('Y-m-d') && $key <= \Carbon\Carbon::parse($x->VAC_FHA)->format('Y-m-d')) {
                        $r['entra1'] = ''; $r['sale1'] = ''; $r['entra2'] = ''; $r['sale2'] = ''; $r['totalSeg'] = 0; $r['observa'] = 'VACACIONES';
                    }
                }
                unset($r['_e1'], $r['_e2']);
            }
            unset($r);

            $totalEmp = 0;
            foreach ($dias as $r) $totalEmp += $r['totalSeg'];
            $superH = intdiv($totalEmp, 3600); $superM = intdiv($totalEmp - $superH * 3600, 60);
            foreach ($dias as $r) {
                $th = intdiv($r['totalSeg'], 3600); $tm = intdiv($r['totalSeg'] - $th * 3600, 60);
                $r['tiempo'] = $r['totalSeg'] > 0 ? sprintf('%02d:%02d', $th, $tm) : '';
                $r['supertotal'] = sprintf('%d:%02d', $superH, $superM);   // minutos con 2 dígitos (183:08, no 183:8)
                $filas[] = $r;
            }
        }
        usort($filas, fn ($a, $b) => [$a['nombre'], $a['fecha']] <=> [$b['nombre'], $b['fecha']]);

        return response()->json(['periodo' => $periodo, 'planilla' => $filas]);
    }

    // ── Helpers de cálculo ───────────────────────────────────────────

    private const MESES_NOMBRE = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

    private function difSeg(?\Carbon\Carbon $a, ?\Carbon\Carbon $b): int
    {
        if (!$a || !$b) return 0;
        return abs($b->diffInSeconds($a));
    }

    /** Carga las tablas auxiliares en memoria para un conjunto de empleados y rango. */
    private function cargarContexto(array $codigos, \Carbon\Carbon $f1, \Carbon\Carbon $f2): array
    {
        return [
            'grupos' => DB::table('reloj_grupos')->get()->keyBy(fn ($g) => (int) $g->rgr_cod),
            'convenios' => DB::table('convenio')->get()->keyBy(fn ($c) => (int) $c->CON_COD),
            'feriados' => DB::table('feriados')->whereBetween('FER_FEC', [$f1->format('Y-m-d'), $f2->format('Y-m-d')])
                ->pluck('FER_FEC')->map(fn ($x) => \Carbon\Carbon::parse($x)->format('Y-m-d'))->flip(),
            'personas' => DB::table('personal')->whereIn('PER_COD', $codigos)
                ->get(['PER_COD', 'PER_LEG', 'PER_NOM', 'PER_TDO', 'PER_NDO', 'PER_CUI', 'PER_DOM', 'PER_CON', 'PER_GRU', 'PER_HNORMAL'])
                ->keyBy(fn ($p) => (int) $p->PER_COD),
            'faltas' => DB::table('reloj_faltas_diarias as a')->join('licencias as b', 'a.AFD_LIC', '=', 'b.LIC_COD')
                ->whereIn('a.AFD_PER', $codigos)
                ->where(function ($q) use ($f1, $f2) {
                    $q->whereBetween('a.AFD_FE1', [$f1, $f2])->orWhereBetween('a.AFD_FE2', [$f1, $f2])
                      ->orWhere(fn ($w) => $w->where('a.AFD_FE1', '<=', $f1)->where('a.AFD_FE2', '>=', $f1));
                })->get(['a.AFD_PER', 'a.AFD_FE1', 'a.AFD_FE2', 'a.AFD_LID', 'a.AFD_OBS']),
            'vacas' => DB::table('vacaciones')->whereIn('VAC_PER', $codigos)
                ->where(function ($q) use ($f1, $f2) {
                    $q->whereBetween('VAC_FDE', [$f1, $f2])->orWhereBetween('VAC_FHA', [$f1, $f2])
                      ->orWhere(fn ($w) => $w->where('VAC_FDE', '<=', $f1)->where('VAC_FHA', '>=', $f1));
                })->get(['VAC_PER', 'VAC_FDE', 'VAC_FHA', 'VAC_OBS']),
            'ajustes' => DB::table('reloj_ajustes')->whereIn('AJR_PER', $codigos)
                ->whereBetween('AJR_FEC', [$f1->format('Y-m-d'), $f2->copy()->addDay()->format('Y-m-d')])->get(),
        ];
    }

    /** Construye las filas (una por día) de un empleado con todo el cálculo de horas. */
    private function diasEmpleado(int $cod, $p, \Carbon\Carbon $f1, \Carbon\Carbon $f2, bool $logistica, array $ctx): array
    {
        if ($logistica) {
            $ini = $f1->copy()->addHours(4);
            $fin = $f2->copy()->addHours(27)->addMinutes(59);
        } else {
            $ini = $f1->copy(); $fin = $f2->copy()->endOfDay();
        }
        $marcas = DB::table('reloj')->where('rel_cod', $cod)->where('ignorar', 0)
            ->whereBetween('rel_fec', [$ini->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s')])
            ->orderBy('rel_fec')->get(['rel_fec', 'rel_id']);

        $dias = [];
        for ($f = $f1->copy(); $f->lte($f2); $f->addDay()) {
            $key = $f->format('Y-m-d');
            $dias[$key] = $this->filaVacia($cod, $p, $f->copy());
            foreach ($ctx['faltas'] as $x) {
                if ((int) $x->AFD_PER === $cod && $key >= \Carbon\Carbon::parse($x->AFD_FE1)->format('Y-m-d') && $key <= \Carbon\Carbon::parse($x->AFD_FE2)->format('Y-m-d')) {
                    $dias[$key]['esFalta'] = true; $dias[$key]['queFalta'] = trim((string) $x->AFD_LID) . ': ' . trim((string) $x->AFD_OBS); break;
                }
            }
            foreach ($ctx['vacas'] as $x) {
                if ((int) $x->VAC_PER === $cod && $key >= \Carbon\Carbon::parse($x->VAC_FDE)->format('Y-m-d') && $key <= \Carbon\Carbon::parse($x->VAC_FHA)->format('Y-m-d')) {
                    $dias[$key]['esFalta'] = true; $dias[$key]['queFalta'] = 'VACACIONES: ' . trim((string) $x->VAC_OBS); break;
                }
            }
        }

        foreach ($marcas as $m) {
            $fec = \Carbon\Carbon::parse($m->rel_fec);
            if ((int) $fec->hour === 0 && (int) $fec->minute === 0 && (int) $fec->second === 0) $fec->setTime(0, 1, 0);
            $diaKey = $logistica ? $fec->copy()->subHours(4)->format('Y-m-d') : $fec->format('Y-m-d');
            if (!isset($dias[$diaKey])) continue;
            $hhmm = (int) $fec->hour * 100 + (int) $fec->minute;
            $r =& $dias[$diaKey];
            if ($r['entra1'] === 0) $r['entra1'] = $hhmm;
            elseif ($r['sale1'] === 0) $r['sale1'] = $hhmm;
            elseif ($r['entra2'] === 0) $r['entra2'] = $hhmm;
            else $r['sale2'] = $hhmm;
            $r['empresa'] = (int) round((float) $m->rel_id);
            unset($r);
        }

        foreach ($ctx['ajustes'] as $a) {
            if ((int) $a->AJR_PER !== $cod) continue;
            $key = \Carbon\Carbon::parse($a->AJR_FEC)->format('Y-m-d');
            if (!isset($dias[$key])) continue;
            $r =& $dias[$key];
            if ((int) $a->AJR_VE1) { if ($r['entra1'] > 0) $r['sale1'] = $r['entra1']; $r['entra1'] = (int) round((float) $a->AJR_HEN1); }
            if ((int) $a->AJR_VS1) { if ($r['sale1'] > 0) $r['entra1'] = $r['sale1']; $r['sale1'] = (int) round((float) $a->AJR_HSA1); }
            if ((int) $a->AJR_VE2) { if ($r['entra2'] > 0) $r['sale2'] = $r['entra2']; $r['entra2'] = (int) round((float) $a->AJR_HEN2); }
            if ((int) $a->AJR_VS2) { if ($r['sale2'] > 0) $r['entra2'] = $r['sale2']; $r['sale2'] = (int) round((float) $a->AJR_HSA2); }
            unset($r);
        }

        $grupo = $ctx['grupos']->get((int) $p->PER_GRU);
        foreach ($dias as &$r) {
            if ($grupo) $this->calcularTurno($r, $grupo, $logistica, (float) $p->PER_HNORMAL);
            $this->aplicarConvenio($r, $ctx['convenios']->get((int) $p->PER_CON));
            $this->nocturnas($r, $logistica);
            if ($ctx['feriados']->has($r['fecha'])) {
                $r['h100'] = $r['trab'] + $r['h50']; $r['trab'] = 0; $r['h50'] = 0; $r['feriado'] = true;
            }
            if ($this->vfpDow($r['fecha']) === 7 && $r['h50'] > 0 && $r['saleCal'] > 1300 && $r['h50'] > 0.5) {
                $r['h100'] = $r['h50'] - 0.5; $r['h50'] = 0.5;
            }
            if ($r['total'] < 0) { $r['total'] = 0; $r['trab'] = 0; $r['h50'] = 0; $r['h100'] = 0; $r['adicNoc'] = 0; $r['extraNoc'] = 0; }
        }
        unset($r);

        return array_values($dias);
    }

    private function filaVacia(int $cod, $p, \Carbon\Carbon $f): array
    {
        return [
            'cod' => $cod, 'legajo' => (int) $p->PER_LEG, 'nombre' => trim((string) $p->PER_NOM),
            'tdoc' => trim((string) $p->PER_TDO), 'ndoc' => trim((string) $p->PER_NDO), 'cuil' => trim((string) $p->PER_CUI),
            'domicilio' => trim((string) $p->PER_DOM), 'convenio' => (int) $p->PER_CON,
            'fecha' => $f->format('Y-m-d'), 'dia' => self::DIAS[$this->vfpDow($f->format('Y-m-d'))],
            'entra1' => 0, 'sale1' => 0, 'entra2' => 0, 'sale2' => 0, 'entraCal' => 0, 'saleCal' => 0,
            'total' => 0.0, 'trab' => 0.0, 'h50' => 0.0, 'h100' => 0.0, 'adicNoc' => 0.0, 'extraNoc' => 0.0,
            'empresa' => 0, 'turno' => 0, 'semana' => 0, 'feriado' => false, 'esFalta' => false, 'queFalta' => '',
            'horasConvenio' => 0.0, 'minutosTarde' => 0.0, 'minLlegadaTarde' => 0, 'minSalidaTarde' => 0, 'difMinutos' => 0,
            'horaEntrada' => 0, 'horaSalida' => 0,
        ];
    }

    /** Convierte un valor HHMM (o duración en HHMM) a horas decimales. */
    private function conv(float $hhmm): float
    {
        $neg = $hhmm < 0; $hhmm = abs($hhmm);
        $h = intval($hhmm / 100); $m = $hhmm - $h * 100;
        $r = $h + $m / 60;
        return $neg ? -$r : $r;
    }

    /** Día de la semana estilo VFP: 1=Domingo … 7=Sábado. */
    private function vfpDow(string $fecha): int
    {
        return ((int) \Carbon\Carbon::parse($fecha)->dayOfWeek) + 1; // Carbon dayOfWeek 0=Dom..6=Sab
    }

    private function calcularTurno(array &$r, $g, bool $logistica, float $hNormal): void
    {
        $dow = $this->vfpDow($r['fecha']);
        // Total de turnos del grupo.
        $totalTurnos = 1;
        if ($this->turnoTieneHoras($g, 2)) $totalTurnos = 2;
        if ($this->turnoTieneHoras($g, 3)) $totalTurnos = 3;
        $semana = (int) \Carbon\Carbon::parse($r['fecha'])->isoWeek();
        $r['semana'] = $semana;

        $turno = 1;
        if (($totalTurnos === 2 && $semana % 2 === 0) || ($totalTurnos === 3 && $semana % 3 === 2)) $turno = 2;
        if ($totalTurnos === 3 && $semana % 3 === 0) $turno = 3;
        $r['turno'] = $turno;

        $he = (int) round((float) $g->{"rgr_t{$turno}d{$dow}d"});
        $hs = (int) round((float) $g->{"rgr_t{$turno}d{$dow}h"});
        $r['horaEntrada'] = $he; $r['horaSalida'] = $hs;

        if ($hs > $he) $horasNormales = ($hs - $he) / 100;
        else $horasNormales = (($logistica ? (2400 - $he + $hs) : (2400 + $hs - $he))) / 100;
        $horasNormales = $this->conv($horasNormales * 100);

        if ($r['entra1'] <= 0) return;

        // Entrada calculada (tolerancia 20 min tarde).
        if ($r['entra1'] < $he || ($r['entra1'] > $he && ($r['entra1'] - $he) <= 20)) $r['entraCal'] = $he;
        else $r['entraCal'] = $r['entra1'];

        $ultimaSalida = $r['sale2'] > 0 ? $r['sale2'] : $r['sale1'];
        if ($ultimaSalida > $hs) {
            $dif = $this->conv($ultimaSalida) - $this->conv($hs);
            $r['saleCal'] = $dif > 0.41 ? $ultimaSalida : $hs;
        } else {
            $r['saleCal'] = $ultimaSalida;
        }

        // Tiempo real entre entrada y salida calculadas.
        if ($logistica && $r['saleCal'] <= $r['entraCal']) {
            $tiempoReal = $this->conv(2400 - $r['entraCal'] + $r['saleCal']);
        } else {
            $tiempoReal = $this->conv($r['saleCal']) - $this->conv($r['entraCal']);
        }
        $calc = $tiempoReal;

        // Restar horas intermedias (almuerzo) si hay segundo par.
        if ($r['entra2'] > 0 || $r['sale2'] > 0) {
            if ($r['entra2'] > $r['sale1']) $noComp = $this->conv($r['entra2']) - $this->conv($r['sale1']);
            else $noComp = $this->conv(2400 + $r['entra2']) - $this->conv($r['sale1']);
            $tiempoReal -= $noComp;

            $ee1 = $r['entra1'] < $he ? $he : $r['entra1'];
            $ss1 = $r['sale1']; if ($ee1 > $ss1) $ss1 = 2400 + $ss1;
            $ee2 = $r['entra2']; if ($ss1 > $ee2) $ee2 = 2400 + $ee2;
            $ss2 = $r['sale2']; if ($ee2 > $ss2) $ss2 = 2400 + $ss2;
            $tiempoBruto = $this->conv($ss2 - $ee2) + $this->conv($ss1 - $ee1);
            $tiempoReal = $this->redondearMediaHora($tiempoBruto);
        }

        $calc = $this->redondearMediaHora($tiempoReal);
        $r['total'] = $calc;

        if ($calc <= $horasNormales) {
            $r['trab'] = $calc;
        } else {
            $r['trab'] = $horasNormales;
            $r['h50'] = $calc - $horasNormales;
        }
        if ($r['trab'] > 0 && $hNormal > 0) $r['trab'] = $hNormal;
    }

    private function turnoTieneHoras($g, int $t): bool
    {
        for ($dia = 1; $dia <= 7; $dia++) {
            if ((float) $g->{"rgr_t{$t}d{$dia}d"} > 0 || (float) $g->{"rgr_t{$t}d{$dia}h"} > 0) return true;
        }
        return false;
    }

    /** Ajuste de media hora: >.9166 sube a entero, >.4167 sube a media. */
    private function redondearMediaHora(float $valor): float
    {
        $dec = $valor - floor($valor);
        $ent = (float) intval($valor);
        if ($dec > 0.9166) return $ent + 1;
        if ($dec > 0.4167) return $ent + 0.5;
        return $ent;
    }

    private function aplicarConvenio(array &$r, $c): void
    {
        if (!$c) return;
        $map = [2 => 'CON_LUN', 3 => 'CON_MAR', 4 => 'CON_MIE', 5 => 'CON_JUE', 6 => 'CON_VIE', 7 => 'CON_SAB', 1 => 'CON_DOM'];
        $dow = $this->vfpDow($r['fecha']);
        $r['horasConvenio'] = (float) ($c->{$map[$dow]} ?? 0);

        if ($r['trab'] > 0) $r['trab'] = $r['horasConvenio'];
        if ($r['horasConvenio'] > $r['total'] && $r['total'] > 0) $r['minutosTarde'] = $r['horasConvenio'] - $r['total'];

        $mt = $r['entra1'] % 100;
        if ($mt > 0 && $mt < 16) $r['minLlegadaTarde'] = $mt;
        $mt = $r['sale1'] % 100;
        if ($mt > 0 && $mt < 30) $r['minSalidaTarde'] = $mt;
        if ($r['minLlegadaTarde'] > 0 || $r['minSalidaTarde'] > 0) $r['difMinutos'] = $r['minLlegadaTarde'] - $r['minSalidaTarde'];
    }

    private function nocturnas(array &$r, bool $logistica): void
    {
        $sale = $r['saleCal'];
        $esNoct = $sale > 2100 || ($logistica && $sale > 0 && $sale < 400);
        if ($esNoct) {
            $trabajaNoche = $sale > 2100 ? ($sale - 2100) : ((2400 - 2100) + $sale);
            $r['adicNoc'] = round($trabajaNoche / 100, 0);
        }
        if ($r['h50'] > 0) {
            if ($r['h50'] > $r['adicNoc']) {
                $r['extraNoc'] = $r['adicNoc']; $r['h50'] -= $r['extraNoc']; $r['adicNoc'] = 0;
            } else {
                $r['extraNoc'] = $r['h50']; $r['adicNoc'] -= $r['h50']; $r['h50'] = 0;
            }
        }
    }
}
