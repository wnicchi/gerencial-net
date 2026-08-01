<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CostoLaboralService — cálculo del costo laboral de un empleado por período,
 * según su convenio. Lógica compartida por los módulos de Costos Laborales:
 * Individual, Grupales e Informe General (empleados_costos_*.scx).
 *
 * calcular() devuelve los rubros REMUNERATIVOS/CARGAS/(NO REMUNERATIVOS) y los
 * costos fijos (GASTOS VARIOS) por separado, más el sueldo bruto y la rama de
 * convenio, para que cada módulo arme la previsión y el total como corresponde
 * (la previsión se incluye como fila en Individual/Grupales y va en columna
 * aparte en el Informe General).
 */
class CostoLaboralService
{
    private bool $silcar;
    private $vacadias;                 // cache de la tabla vacadias
    private array $fijosCache = [];    // costos fijos por "mes-anio"

    public function __construct()
    {
        $this->silcar = config('rrhh.empresa', 'silcar') !== 'logist';
    }

    /**
     * @return array{rows: list<array>, gastos: list<array>, bruto: float,
     *   branch: string, antiguedad: int, corresponden: int}
     */
    public function calcular(object $per, int $mes, int $anio): array
    {
        $con = (int) $per->PER_CON;
        $cat = (int) $per->PER_CAT;

        $fechaPeriodo   = Carbon::create($anio, $mes, 1);
        $fechaComputada = Carbon::create($anio - 1, 12, 31);
        $ing = ($per->PER_ING && substr((string) $per->PER_ING, 0, 4) !== '1900')
            ? Carbon::parse(substr((string) $per->PER_ING, 0, 10)) : $fechaPeriodo->copy();
        $base = $fechaComputada->lt($ing) ? $fechaPeriodo : $fechaComputada;
        $diasTrab = (int) $ing->diffInDays($base, false);

        $corresponden = 0;
        foreach ($this->vacadias() as $v0) {
            $v = array_change_key_case((array) $v0, CASE_UPPER);
            $diasIni = (int) $v['VRE_AINI'] * 365 + (int) $v['VRE_MINI'] * 30;
            $diasFin = (int) $v['VRE_AFIN'] * 365 + (int) $v['VRE_MFIN'] * 30;
            if ($diasTrab >= $diasIni && $diasTrab <= $diasFin && (int) $v['VRE_DIAS'] > $corresponden) {
                $corresponden = (int) $v['VRE_DIAS'];
                break;
            }
        }
        $antiguedad = $diasTrab > 0 ? intdiv($diasTrab, 365) : 0;

        $rows = [];
        $add = function (string $rubro, string $detalle, float $importe) use (&$rows) {
            $rows[] = ['rubro' => $rubro, 'detalle' => $detalle, 'importe' => round($importe, 2)];
        };
        $sum = fn () => array_sum(array_column($rows, 'importe'));
        $bruto = 0.0;
        $branch = '';

        if ((!$this->silcar && $con === 1) || ($this->silcar && $con === 7)) {
            $branch = 'smata';
            $bruto = $this->sueldoBrutoPeriodo((int) $per->PER_COD, $anio, $mes);
            $add('REMUNERATIVOS', 'SUELDO BRUTO - SALARIO BASE', $bruto);
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 24%', $sum() * 24 / 100);
        } elseif ($con === 3) {
            $branch = 'fuera';
            $bruto = $this->sueldoBrutoPeriodo((int) $per->PER_COD, $anio, $mes);
            $add('REMUNERATIVOS', 'SUELDO BRUTO - SALARIO BASE', $bruto);
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 21%', $sum() * 21 / 100);
        } elseif (!$this->silcar && $con === 6 && $cat !== 48) {
            $branch = 'nolarga';
            $item = $this->itemsUltimos90($fechaPeriodo, (int) $per->PER_COD);
            $sal = $item(10, 'SALARIO BASE'); $bruto = $sal['importe'];
            $add('REMUNERATIVOS', $sal['detalle'], $sal['importe']);
            $ant = $item(255, 'ANTIGÜEDAD');
            $add('REMUNERATIVOS', $ant['detalle'], $ant['importe']);
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 26%', $sum() * 26 / 100);
        } elseif ((!$this->silcar && $con === 6 && $cat === 48) || ($this->silcar && $con === 4)) {
            $branch = 'larga';
            $item = $this->itemsUltimos90($fechaPeriodo, (int) $per->PER_COD);
            $sal = $item(10, 'SALARIO BASE'); $bruto = $sal['importe'];
            foreach ([
                [10, 'SALARIO BASE'], [241, 'KM RECORRIDO SIMPLE'], [75, 'KM RECORRIDO AL 100%'],
                [76, 'CONTROL DE DESCARGA'], [263, 'PERMANENCIA FUERA RESIDENCIA'], [78, 'SIMPLE PRESENCIA'],
                [255, 'ANTIGÜEDAD'],
            ] as [$cod, $def]) {
                $it = $item($cod, $def);
                $add('REMUNERATIVOS', $it['detalle'], $it['importe']);
            }
            $add('REMUNERATIVOS', 'AGUINALDO', $bruto / 12);
            $add('REMUNERATIVOS', 'VACACIONES', (($bruto / 25) * $corresponden) / 12);
            $add('CARGAS', 'REMUNERACIONES 26%', $sum() * 26 / 100);
            $via = $item(74, 'VIATICO POR KM RECORRIDO');
            $add('NO REMUNERATIVOS', $via['detalle'], $via['importe']);
        }

        $gastos = [];
        foreach ($this->costosFijos($mes, $anio) as $c) {
            $gastos[] = ['rubro' => 'GASTOS VARIOS', 'detalle' => trim((string) $c->COS_DET), 'importe' => round((float) $c->COS_IMP, 2)];
        }

        return compact('rows', 'gastos', 'bruto', 'branch', 'antiguedad', 'corresponden');
    }

    /** Fila de previsión de despidos con la fórmula de Individual/Grupales. */
    public function previsionIndividual(string $branch, float $bruto): array
    {
        return match ($branch) {
            'smata', 'fuera' => ['detalle' => 'DESPIDOS - CONTRATO 9 AÑOS', 'importe' => round(($bruto * 9) / 108, 2)],
            'nolarga'        => ['detalle' => 'DESPIDOS - CONTRATO 5 AÑOS', 'importe' => round(($bruto * 5) / 108, 2)],
            'larga'          => ['detalle' => 'DESPIDOS - CONTRATO 5 AÑOS', 'importe' => round(($bruto * 5) / 60, 2)],
            default          => ['detalle' => '', 'importe' => 0.0],
        };
    }

    /** Monto de previsión con la fórmula del Informe General (no-larga usa 9/108). */
    public function previsionInforme(string $branch, float $bruto): float
    {
        return match ($branch) {
            'smata', 'fuera', 'nolarga' => round(($bruto * 9) / 108, 2),
            'larga'                     => round(($bruto * 5) / 60, 2),
            default                     => 0.0,
        };
    }

    private function vacadias()
    {
        return $this->vacadias ??= DB::table('vacadias')->get();
    }

    private function costosFijos(int $mes, int $anio)
    {
        return $this->fijosCache["$mes-$anio"] ??=
            DB::table('costo_laboral_fijo')->where('COS_MES', $mes)->where('COS_ANIO', $anio)->get();
    }

    private function sueldoBrutoPeriodo(int $emp, int $anio, int $mes): float
    {
        $nros = DB::table('liquidac')
            ->where('LIQ_COD', $emp)->where('LIQ_TIP', 1)->where('LIQ_ANO', $anio)->where('LIQ_MES', $mes)
            ->pluck('LIQ_NRO');
        if ($nros->isEmpty()) {
            return 0.0;
        }
        return (float) DB::table('liq_ite')->whereIn('LIT_NRO', $nros)->where('LIT_HAB', '>', 0)->sum('LIT_HAB');
    }

    private function itemsUltimos90(Carbon $fechaPeriodo, int $emp): callable
    {
        $desde = $fechaPeriodo->copy()->subDays(90)->format('Y-m-d');
        $nros = DB::table('liquidac')->where('LIQ_COD', $emp)->where('LIQ_FEC', '>', $desde)->pluck('LIQ_NRO');

        return function (int $litCod, string $def) use ($nros): array {
            if ($nros->isEmpty()) {
                return ['detalle' => $def, 'importe' => 0.0];
            }
            $it = DB::table('liq_ite')->whereIn('LIT_NRO', $nros)
                ->where('LIT_COD', $litCod)->where('LIT_HAB', '>', 0)->orderByDesc('LIT_NRO')->first();
            if (!$it) {
                return ['detalle' => $def, 'importe' => 0.0];
            }
            $a = array_change_key_case((array) $it, CASE_UPPER);
            $des = trim((string) ($a['LIT_DES'] ?? ''));
            return ['detalle' => $des !== '' ? $des : $def, 'importe' => (float) ($a['LIT_HAB'] ?? 0)];
        };
    }
}
