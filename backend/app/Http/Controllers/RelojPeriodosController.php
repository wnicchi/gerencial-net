<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojPeriodosController — Reloj / Ajuste Horarios por Períodos (reloj_capturas_edicion.scx).
 *
 * Muestra, para un empleado y un rango de fechas, una grilla editable día por día con las 4 fichadas
 * (1ª entrada / 1ª salida / 2ª entrada / 2ª salida) y el tiempo trabajado. Las marcaciones antes de las
 * 3 de la mañana se imputan al día anterior (jornada nocturna). Al confirmar, por cada campo que el
 * operador haya cambiado: archiva y elimina la marcación real correspondiente (a reloj_eliminado) y
 * carga/actualiza el ajuste manual (reloj_ajustes) con el valor nuevo.
 *
 * La hora 00:00 se toma como "sin marcación".
 */
class RelojPeriodosController extends Controller
{
    /** @route GET /api/reloj/periodos/recuperar?cod=&fecha1=&fecha2= */
    public function recuperar(Request $request): JsonResponse
    {
        $d = $request->validate(['cod' => 'required|integer', 'fecha1' => 'required|date', 'fecha2' => 'required|date']);
        $cod = (int) $d['cod'];
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();

        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 404);

        // Marcaciones reales (hasta f2+1) + ajustes como pseudo-marcaciones.
        $marcas = DB::table('reloj')->where('rel_cod', $cod)
            ->whereBetween('rel_fec', [$f1->format('Y-m-d') . ' 00:00:00', $f2->copy()->addDay()->format('Y-m-d') . ' 23:59:59'])
            ->orderBy('rel_fec')->pluck('rel_fec')->map(fn ($x) => ['fec' => \Carbon\Carbon::parse($x), 'aj' => false])->all();
        foreach (DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereBetween('AJR_FEC', [$f1, $f2])->get() as $a) {
            $fa = \Carbon\Carbon::parse($a->AJR_FEC);
            foreach ([[$a->AJR_VE1, $a->AJR_HEN1], [$a->AJR_VS1, $a->AJR_HSA1], [$a->AJR_VE2, $a->AJR_HEN2], [$a->AJR_VS2, $a->AJR_HSA2]] as [$fl, $h]) {
                if (!(int) $fl) continue;
                $hh = (int) round((float) $h);
                $marcas[] = ['fec' => $fa->copy()->setTime(intdiv($hh, 100), $hh % 100, 0), 'aj' => true];
            }
        }
        usort($marcas, fn ($a, $b) => $a['fec'] <=> $b['fec']);
        while (!empty($marcas) && (int) $marcas[0]['fec']->hour < 3) array_shift($marcas);

        // Filas por día.
        $dias = [];
        for ($f = $f1->copy(); $f->lte($f2); $f->addDay()) {
            $k = $f->format('Y-m-d');
            $dias[$k] = [
                'fecha' => $k,
                'entraHor1' => 0, 'saleHor1' => 0, 'entraHor2' => 0, 'saleHor2' => 0,
                'antEnt1' => 0, 'antSal1' => 0, 'antEnt2' => 0, 'antSal2' => 0,
                'frealEh1' => null, 'frealSh1' => null, 'frealEh2' => null, 'frealSh2' => null,
                'trabajado' => 0, 'marcar' => false, 'cuantas' => 0,
            ];
        }
        // Asignar slots por fecha-corregida (hora<3 -> día anterior).
        foreach ($marcas as $m) {
            $fec = $m['fec'];
            $k = ((int) $fec->hour >= 3) ? $fec->format('Y-m-d') : $fec->copy()->subDay()->format('Y-m-d');
            if (!isset($dias[$k])) continue;
            $hhmm = (int) $fec->hour * 100 + (int) $fec->minute;
            $real = $fec->format('Y-m-d');
            $r =& $dias[$k];
            switch ($r['cuantas']) {
                case 0: $r['entraHor1'] = $hhmm; $r['antEnt1'] = $hhmm; $r['frealEh1'] = $real; break;
                case 1: $r['saleHor1'] = $hhmm; $r['antSal1'] = $hhmm; $r['frealSh1'] = $real; break;
                case 2: $r['entraHor2'] = $hhmm; $r['antEnt2'] = $hhmm; $r['frealEh2'] = $real; break;
                default: $r['saleHor2'] = $hhmm; $r['antSal2'] = $hhmm; $r['frealSh2'] = $real; break;
            }
            $r['cuantas'] = ($r['cuantas'] + 1) > 3 ? 0 : $r['cuantas'] + 1;
            unset($r);
        }
        foreach ($dias as &$r) $r['trabajado'] = $this->trabajado($r);
        unset($r);

        $filas = array_values($dias);
        return response()->json([
            'cod' => $cod, 'nombre' => trim((string) $p->PER_NOM), 'filas' => $filas,
            'totalHoras' => $this->totalHoras($filas),
        ]);
    }

    /** @route POST /api/reloj/periodos/confirmar */
    public function confirmar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cod' => 'required|integer', 'nombre' => 'nullable|string',
            'filas' => 'required|array|min:1',
        ]);
        $cod = (int) $d['cod'];
        $nombre = trim((string) ($d['nombre'] ?? ''));

        // Definición de los 4 slots: campo actual, campo original, freal, columna AJR de hora y flag.
        $slots = [
            ['entraHor1', 'antEnt1', 'frealEh1', 'AJR_HEN1', 'AJR_VE1'],
            ['saleHor1', 'antSal1', 'frealSh1', 'AJR_HSA1', 'AJR_VS1'],
            ['entraHor2', 'antEnt2', 'frealEh2', 'AJR_HEN2', 'AJR_VE2'],
            ['saleHor2', 'antSal2', 'frealSh2', 'AJR_HSA2', 'AJR_VS2'],
        ];

        $cambios = 0;
        DB::transaction(function () use ($d, $cod, $nombre, $slots, &$cambios) {
            foreach ($d['filas'] as $row) {
                $fecha = \Carbon\Carbon::parse($row['fecha'])->format('Y-m-d');
                foreach ($slots as [$campo, $antc, $frealc, $colHora, $colFlag]) {
                    $hor = (int) ($row[$campo] ?? 0);
                    $ant = (int) ($row[$antc] ?? 0);
                    if ($hor === $ant) continue;
                    $cambios++;

                    // Archivar y borrar la marcación real original (si existía).
                    if ($ant > 0 && !empty($row[$frealc])) {
                        $fr = \Carbon\Carbon::parse($row[$frealc])->format('Y-m-d');
                        $reales = DB::table('reloj')->where('rel_cod', $cod)
                            ->whereDate('rel_fec', $fr)
                            ->whereRaw('DATEPART(hour, rel_fec) = ?', [intdiv($ant, 100)])
                            ->whereRaw('DATEPART(minute, rel_fec) = ?', [$ant % 100])
                            ->get(['rel_cod', 'rel_fec', 'rel_id']);
                        foreach ($reales as $rl) {
                            DB::table('reloj_eliminado')->insert(['rel_cod' => $rl->rel_cod, 'rel_fec' => $rl->rel_fec, 'rel_id' => $rl->rel_id]);
                            DB::table('reloj')->where('rel_cod', $rl->rel_cod)->where('rel_fec', $rl->rel_fec)->delete();
                        }
                    }

                    // Upsert del ajuste manual (uno por empleado+fecha).
                    $existe = DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereDate('AJR_FEC', $fecha)->exists();
                    if (!$existe) {
                        DB::table('reloj_ajustes')->insert(Registro::completar('reloj_ajustes', [
                            'AJR_PER' => $cod, 'AJR_NOM' => $nombre, 'AJR_FEC' => $fecha,
                        ]));
                    }
                    DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereDate('AJR_FEC', $fecha)
                        ->update([$colHora => $hor, $colFlag => 1]);
                }
            }
        });

        return response()->json(['message' => "Ajustes confirmados ($cambios cambio(s)).", 'cambios' => $cambios]);
    }

    /** Tiempo trabajado de una fila (HHMM), sólo pares completos. */
    private function trabajado(array $r): int
    {
        $min = 0;
        if ($r['cuantas'] > 1) $min += $this->parMin($r['entraHor1'], $r['saleHor1']);
        if ($r['cuantas'] > 3) $min += $this->parMin($r['entraHor2'], $r['saleHor2']);
        return intdiv($min, 60) * 100 + $min % 60;
    }

    private function parMin(int $entra, int $sale): int
    {
        $em = intdiv($entra, 100) * 60 + $entra % 100;
        $sm = intdiv($sale, 100) * 60 + $sale % 100;
        if ($sale > $entra) return $sm - $em;
        if ($sale < $entra) return (24 * 60 + $sm) - $em; // cruza medianoche
        return 0;
    }

    /** Suma de los TRABAJADO (HHMM) en formato HHMM. */
    private function totalHoras(array $filas): int
    {
        $min = 0;
        foreach ($filas as $f) $min += intdiv($f['trabajado'], 100) * 60 + $f['trabajado'] % 100;
        return intdiv($min, 60) * 100 + $min % 60;
    }
}
