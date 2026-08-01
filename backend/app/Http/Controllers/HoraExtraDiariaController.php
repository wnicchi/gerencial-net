<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HoraExtraDiariaController — Planilla de Horas Extras Diarias (y nocturnas).
 *
 * Para un día, empresa y contratista, analiza las marcaciones de reloj (+ ajustes
 * manuales) de cada empleado que ficha, calcula el tiempo trabajado, le resta las
 * horas normales del convenio según el día de la semana, y obtiene el TIEMPO EXTRA
 * y las HORAS NOCTURNAS. El operador distribuye el extra en 50% / 100% (HH:MM) y
 * confirma; se guarda en horas_extras_diaria.
 *
 * Los valores 50/100/nocturna se manejan como enteros HHMM (ej: 230 = 02:30).
 */
class HoraExtraDiariaController extends Controller
{
    /** @route GET /api/horas-extras-diarias?empresa=&contratista=&fecha= */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empresa'     => 'nullable|integer',
            'contratista' => 'required|integer',
            'fecha'       => 'required|date',
        ]);
        $empresa = (int) ($datos['empresa'] ?? 1);
        $contratista = (int) $datos['contratista'];
        $fecha = Carbon::parse($datos['fecha'])->startOfDay();

        // Ventana del día laboral: de las 03:00 a las 02:59 del día siguiente
        $desde = $fecha->copy()->setTime(3, 0, 0);
        $hasta = $fecha->copy()->addDay()->setTime(2, 59, 1);

        $personas = DB::table('personal as p')
            ->join('convenio as c', 'c.CON_COD', '=', 'p.PER_CON')
            ->where('p.PER_EMP', $empresa)
            ->where('p.PER_CONTRA', $contratista)
            ->where('p.PER_AOP', 'A')
            ->where('p.PER_CHE', 'S')
            ->orderBy('p.PER_NOM')
            ->get([
                'p.PER_COD', 'p.PER_NOM', 'p.PER_LEG', 'p.PER_CON',
                'c.CON_DOM', 'c.CON_LUN', 'c.CON_MAR', 'c.CON_MIE', 'c.CON_JUE', 'c.CON_VIE', 'c.CON_SAB',
            ]);

        $codes = $personas->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();
        if (empty($codes)) {
            return response()->json(['rows' => [], 'es_viernes' => $fecha->isFriday(), 'fecha' => $fecha->format('d/m/Y')]);
        }

        // Valores ya guardados para ese día
        $guardadas = DB::table('horas_extras_diaria')
            ->whereDate('HRE_FECHA', $fecha->toDateString())
            ->get()->keyBy('HRE_PER_COD');

        // Marcaciones de reloj del día (una sola consulta para todos)
        $punchesPorCod = [];
        foreach (DB::table('reloj')->whereIn('REL_COD', $codes)->where('IGNORAR', 0)
            ->whereBetween('REL_FEC', [$desde, $hasta])->get(['REL_COD', 'REL_FEC']) as $r) {
            $punchesPorCod[(int) $r->REL_COD][] = Carbon::parse($r->REL_FEC);
        }
        // Ajustes manuales del día (una sola consulta)
        foreach (DB::table('reloj_ajustes')->whereIn('AJR_PER', $codes)
            ->whereDate('AJR_FEC', $fecha->toDateString())->get() as $a) {
            foreach ([['AJR_VE1', 'AJR_HEN1'], ['AJR_VS1', 'AJR_HSA1'], ['AJR_VE2', 'AJR_HEN2'], ['AJR_VS2', 'AJR_HSA2']] as [$flag, $hhmm]) {
                if ((int) $a->$flag === 1) {
                    $h = (int) $a->$hhmm;
                    $punchesPorCod[(int) $a->AJR_PER][] = $fecha->copy()->setTime(intdiv($h, 100), $h % 100, 0);
                }
            }
        }

        $rows = [];
        foreach ($personas as $p) {
            $cod = (int) $p->PER_COD;

            $punches = $punchesPorCod[$cod] ?? [];
            if (count($punches) < 2) continue;

            usort($punches, fn ($x, $y) => $x->timestamp <=> $y->timestamp);
            $hora1 = $punches[0]; $hora2 = $punches[1];   // entrada / salida (primeras dos)

            $horasPasadas = ($hora2->timestamp - $hora1->timestamp) / 3600;

            // Horas normales del convenio según el día de la semana de la entrada
            $diaCol = [0 => 'CON_DOM', 1 => 'CON_LUN', 2 => 'CON_MAR', 3 => 'CON_MIE', 4 => 'CON_JUE', 5 => 'CON_VIE', 6 => 'CON_SAB'];
            $horasNormales = (float) $p->{$diaCol[$hora1->dayOfWeek]};

            $tiempoExtra = $horasPasadas > $horasNormales ? $horasPasadas - $horasNormales : 0;

            // Horas nocturnas (HHMM): según la hora de salida (21:00 a 03:00)
            $noct = 0;
            $h2 = $hora2->hour; $m2 = $hora2->minute;
            if ($h2 >= 21 || $h2 < 3) {
                if ($h2 < 3) {
                    $tn = $h2 + $m2 / 100;
                    $tn += ($hora1->hour >= 21) ? ($hora1->hour - 21) + $hora1->minute / 100 : 3;
                } else {
                    $tn = ($h2 - 21) + $m2 / 100;
                }
                $noct = $tn;
            }

            if (!($tiempoExtra > 0.41 || $noct > 0)) continue;

            $g = $guardadas[$cod] ?? null;
            $est50  = $g ? (int) round($g->HRE_EST50) : 0;
            $est100 = $g ? (int) round($g->HRE_EST100) : 0;
            $estNoc = $g ? (int) round($g->HRE_ESTNOC50) : (int) round($noct * 100);
            $calc   = $g ? (float) $g->HRE_CALCULADA : round($tiempoExtra, 2);

            $rows[] = [
                'codigo'        => $cod,
                'nombre'        => trim((string) $p->PER_NOM),
                'legajo'        => trim((string) $p->PER_LEG),
                'convenio'      => (int) $p->PER_CON,
                'entra'         => $hora1->format('H:i'),
                'sale'          => $hora2->format('H:i'),
                'horas_extra'   => $this->hhmm($tiempoExtra),
                'm_calculada'   => $calc,
                'm_est50'       => $est50,
                'm_est100'      => $est100,
                'm_estnoc50'    => $estNoc,
                // Valores ya guardados (0 si todavía no se confirmó). Sirven para
                // resaltar en amarillo lo calculado-pero-no-guardado (ej: la nocturna).
                'ant_est50'     => $est50,
                'ant_est100'    => $est100,
                'ant_estnoc50'  => $g ? $estNoc : 0,
                'reloj_entrada' => $hora1->format('Y-m-d H:i:s'),
                'reloj_salida'  => $hora2->format('Y-m-d H:i:s'),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return response()->json([
            'rows'      => $rows,
            'es_viernes' => $fecha->isFriday(),
            'fecha'     => $fecha->format('d/m/Y'),
        ]);
    }

    /**
     * Consulta de horas extras de UN empleado en un rango de fechas (solo lectura).
     * @route GET /api/horas-extras-diarias/empleado?codigo=&fecha1=&fecha2=
     */
    public function porEmpleado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo' => 'required|integer',
            'fecha1' => 'required|date',
            'fecha2' => 'required|date',
        ]);
        $cod = (int) $datos['codigo'];
        $f1 = Carbon::parse($datos['fecha1'])->startOfDay();
        $f2 = Carbon::parse($datos['fecha2'])->startOfDay();

        $p = DB::table('personal as p')->join('convenio as c', 'c.CON_COD', '=', 'p.PER_CON')
            ->where('p.PER_COD', $cod)
            ->first(['p.PER_NOM', 'c.CON_DOM', 'c.CON_LUN', 'c.CON_MAR', 'c.CON_MIE', 'c.CON_JUE', 'c.CON_VIE', 'c.CON_SAB']);
        if (!$p) return response()->json(['message' => 'Empleado no encontrado.'], 404);

        // Marcaciones del empleado en el rango (agrupadas por día calendario), sin las de 00:00
        $porDia = [];
        foreach (DB::table('reloj')->where('REL_COD', $cod)->where('IGNORAR', 0)
            ->whereBetween('REL_FEC', [$f1->copy(), $f2->copy()->addDay()])
            ->get(['REL_FEC']) as $r) {
            $dt = Carbon::parse($r->REL_FEC);
            if ($dt->hour === 0 && $dt->minute === 0) continue;
            if ($dt->lt($f1) || $dt->gt($f2->copy()->endOfDay())) continue;
            $porDia[$dt->toDateString()][] = $dt;
        }

        // Valores guardados en el rango
        $guardadas = DB::table('horas_extras_diaria')
            ->where('HRE_PER_COD', $cod)
            ->whereBetween(DB::raw('CAST(HRE_FECHA AS DATE)'), [$f1->toDateString(), $f2->toDateString()])
            ->get()->keyBy(fn ($g) => Carbon::parse($g->HRE_FECHA)->toDateString());

        $diaCol = [0 => 'CON_DOM', 1 => 'CON_LUN', 2 => 'CON_MAR', 3 => 'CON_MIE', 4 => 'CON_JUE', 5 => 'CON_VIE', 6 => 'CON_SAB'];

        $rows = [];
        for ($d = $f1->copy(); $d->lte($f2); $d->addDay()) {
            $key = $d->toDateString();
            $entra = ''; $sale = ''; $horasExtra = ''; $calc = 0;
            $punches = $porDia[$key] ?? [];
            $tieneCalc = false;
            if (count($punches) > 1) {
                usort($punches, fn ($x, $y) => $x->timestamp <=> $y->timestamp);
                $h1 = $punches[0]; $h2 = $punches[1];
                $horasPasadas = ($h2->timestamp - $h1->timestamp) / 3600;
                $horasNormales = (float) $p->{$diaCol[$h1->dayOfWeek]};
                $tiempoExtra = $horasPasadas > $horasNormales ? $horasPasadas - $horasNormales : 0;
                if ($tiempoExtra > 0.41) {
                    $entra = $h1->format('H:i'); $sale = $h2->format('H:i');
                    $horasExtra = $this->hhmm($tiempoExtra); $calc = round($tiempoExtra, 2);
                    $tieneCalc = true;
                }
            }
            $g = $guardadas[$key] ?? null;
            if (!$tieneCalc && !$g) continue;   // ese día no aporta

            $rows[] = [
                'fecha'       => $d->format('d/m/Y'),
                'entra'       => $entra,
                'sale'        => $sale,
                'horas_extra' => $horasExtra,
                'm_est50'     => $g ? (int) round($g->HRE_EST50) : 0,
                'm_est100'    => $g ? (int) round($g->HRE_EST100) : 0,
                'm_estnoc50'  => $g ? (int) round($g->HRE_ESTNOC50) : 0,
                'm_calculada' => $g ? (float) $g->HRE_CALCULADA : $calc,
            ];
        }

        // Total de horas extras (50 + 100 + nocturna)
        $totMin = 0;
        foreach ($rows as $r) {
            foreach (['m_est50', 'm_est100', 'm_estnoc50'] as $k) {
                $totMin += intdiv($r[$k], 100) * 60 + $r[$k] % 100;
            }
        }
        $totH = intdiv($totMin, 60); $totR = $totMin % 60;
        $equiv = round($totH + $totR / 60, 2);

        return response()->json([
            'nombre' => trim((string) $p->PER_NOM),
            'rows'   => $rows,
            'total'  => "TRABAJO EXTRA = $totH HORAS, $totR MINUTOS, equivalen a " . number_format($equiv, 2, ',', '.') . ' Hs extras',
        ]);
    }

    /** @route POST /api/horas-extras-diarias/confirmar  body: { fecha, rows:[...] } */
    public function confirmar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha'               => 'required|date',
            'rows'                => 'required|array|min:1',
            'rows.*.codigo'       => 'required|integer',
            'rows.*.nombre'       => 'nullable|string',
            'rows.*.reloj_entrada' => 'nullable|date',
            'rows.*.reloj_salida' => 'nullable|date',
            'rows.*.m_calculada'  => 'required|numeric',
            'rows.*.m_est50'      => 'required|integer',
            'rows.*.m_est100'     => 'required|integer',
            'rows.*.m_estnoc50'   => 'required|integer',
        ]);

        $fecha = Carbon::parse($datos['fecha'])->startOfDay();
        $usuario = (string) ($request->user()->NOMBRE ?? $request->user()->name ?? '');
        $ahora = Carbon::now();

        try {
        DB::transaction(function () use ($datos, $fecha, $usuario, $ahora) {
            foreach ($datos['rows'] as $r) {
                $q = DB::table('horas_extras_diaria')
                    ->whereDate('HRE_FECHA', $fecha->toDateString())
                    ->where('HRE_PER_COD', $r['codigo']);
                $vals = [
                    'HRE_CALCULADA' => $r['m_calculada'],
                    'HRE_EST50'     => $r['m_est50'],
                    'HRE_EST100'    => $r['m_est100'],
                    'HRE_ESTNOC50'  => $r['m_estnoc50'],
                    'HRE_MODUSU'    => $usuario,
                    'HRE_MODFEC'    => $ahora,
                    'HRE_ENTRA'     => $r['reloj_entrada'] ? Carbon::parse($r['reloj_entrada']) : '1900-01-01 00:00:00',
                    'HRE_SALE'      => $r['reloj_salida'] ? Carbon::parse($r['reloj_salida']) : '1900-01-01 00:00:00',
                ];
                if ($q->exists()) {
                    $q->update($vals);
                } else {
                    DB::table('horas_extras_diaria')->insert(Registro::completar('horas_extras_diaria', array_merge($vals, [
                        'HRE_FECHA'   => $fecha,
                        'HRE_EDITADA' => $usuario,
                        'HRE_PER_COD' => $r['codigo'],
                        'HRE_NOMBRE'  => mb_substr((string) ($r['nombre'] ?? ''), 0, 50),
                    ])));
                }
            }
        });
        } catch (\Throwable $e) {
            // Queda registrado en log_errores_sql y el operador recibe un mensaje claro
            // (sin exponer detalles del esquema).
            \App\Support\RegistroError::registrar($e, $request, 'HORAS EXTRAS DIARIAS');
            return response()->json(['message' => 'No se pudo grabar la planilla. El detalle quedó registrado en el Log de Errores (avisar al administrador).'], 500);
        }

        return response()->json(['ok' => true, 'grabados' => count($datos['rows'])]);
    }

    /** Convierte horas decimales a texto HH:MM. */
    private function hhmm(float $dec): string
    {
        $h = (int) floor($dec);
        $m = (int) round(($dec - $h) * 60);
        if ($m === 60) { $h++; $m = 0; }
        return sprintf('%02d:%02d', $h, $m);
    }
}
