<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ComparativaGastosController — Estadísticas › Comparativa de Gastos.
 *
 * Migra estadistica_comparativa_gastos.scx (botón GENERAR INFORME). Arma la tabla
 * de gastos (COMPRAS) por mes y año de los últimos 10 años + la tabla interanual %.
 * Filtro: rubro de compra (TODOS = -1, o un rubro de RUB_CPRA). Opción "en miles".
 *
 * SOLO LECTURA. Gasto = SUM(COM_BRU + COM_INO); las NC (COM_TFA "NC…") restan.
 *   - TOTAL (rubro -1): agrupa por imputación COM_IAN/COM_IME (todas las compras).
 *   - Un rubro:         COMPRAS WHERE COM_RUB = rubro, agrupa por YEAR/MONTH(COM_FEC).
 * Los impuestos (IMPUESTO / IMPU_ITE) están DESACTIVADOS en el Fox (Sum comentado)
 * → no suman; por eso el combo solo ofrece TOTAL + rubros de compra.
 */
class ComparativaGastosController extends Controller
{
    private function conn()
    {
        return DB::connection('gestion');
    }

    private const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Setiembre','Octubre','Noviembre','Diciembre'];

    /** GET /api/estadisticas/comparativa-gastos/catalogo — rubros de compra + rango de años. */
    public function catalogo(): JsonResponse
    {
        $rubros = $this->conn()->table('RUB_CPRA')->orderBy('RUB_DES')->get(['RUB_COD', 'RUB_DES'])
            ->map(fn ($r) => ['cod' => (int) $r->RUB_COD, 'des' => trim((string) $r->RUB_DES)])->values();
        $anioHoy = (int) Carbon::now()->year;
        return response()->json([
            'rubros'   => $rubros,
            'anio_desde' => $anioHoy - 10,
            'anio_hoy' => $anioHoy,
        ]);
    }

    /**
     * GET /api/estadisticas/comparativa-gastos — informe.
     * Params: rubro (-1 = TOTAL | cod de RUB_CPRA), en_miles (0|1).
     */
    public function calcular(Request $request): JsonResponse
    {
        $rubro  = (int) $request->query('rubro', -1);
        $miles  = (int) $request->query('en_miles', 0) === 1;
        $c = $this->conn();
        $anioHoy = (int) Carbon::now()->year;
        $anioDesde = $anioHoy - 10;

        // Suma con signo: NC resta. COM_BRU + COM_INO.
        $sumExpr = "SUM((CASE WHEN LEFT(COM_TFA,2)='NC' THEN -1 ELSE 1 END) * (ISNULL(COM_BRU,0) + ISNULL(COM_INO,0)))";

        if ($rubro === -1) {
            // TOTAL: todas las compras, por imputación (COM_IAN/COM_IME).
            $rows = $c->table('COMPRAS')
                ->whereBetween('COM_IAN', [$anioDesde, $anioHoy])
                ->selectRaw("COM_IAN AS anio, COM_IME AS mes, $sumExpr AS monto")
                ->groupBy('COM_IAN', 'COM_IME')->get();
        } else {
            // Un rubro: por fecha real (COM_FEC).
            $rows = $c->table('COMPRAS')
                ->where('COM_RUB', $rubro)
                ->where('COM_FEC', '>=', Carbon::create($anioDesde, 1, 1)->format('Y-m-d'))
                ->selectRaw("YEAR(COM_FEC) AS anio, MONTH(COM_FEC) AS mes, $sumExpr AS monto")
                ->groupByRaw('YEAR(COM_FEC), MONTH(COM_FEC)')->get();
        }

        // Acumular [anio][mes].
        $acum = [];
        foreach ($rows as $r) {
            $a = (int) $r->anio; $m = (int) $r->mes;
            if ($a < $anioDesde || $a > $anioHoy || $m < 1 || $m > 12) continue;
            $acum[$a][$m] = ($acum[$a][$m] ?? 0) + (float) $r->monto;
        }

        // Presentación: fila por año (anioDesde..hoy), 12 meses + total (valores completos).
        $presFull = [];
        for ($a = $anioDesde; $a <= $anioHoy; $a++) {
            $meses = []; $tot = 0.0;
            for ($m = 1; $m <= 12; $m++) { $v = round($acum[$a][$m] ?? 0, 2); $meses[] = $v; $tot += $v; }
            $presFull[] = ['anio' => $a, 'meses' => $meses, 'total' => round($tot, 2)];
        }

        // Interanual (% sobre valores completos; el % es invariante a la escala).
        $interanual = [];
        for ($i = 1; $i < count($presFull); $i++) {
            $act = $presFull[$i]; $ant = $presFull[$i - 1]; $por = [];
            for ($m = 0; $m < 12; $m++) {
                $por[] = $ant['meses'][$m] != 0 ? round(($act['meses'][$m] * 100 / $ant['meses'][$m]) - 100, 2) : 0;
            }
            $porTot = $ant['total'] != 0 ? round(($act['total'] * 100 / $ant['total']) - 100, 2) : 0;
            $interanual[] = ['detalle' => $act['anio'] . ' Vs. ' . $ant['anio'], 'meses' => $por, 'total' => $porTot];
        }

        // Presentación para mostrar: dividir por 1000 (Int) si "en miles".
        $presentacion = array_map(function ($p) use ($miles) {
            if (!$miles) return $p;
            return [
                'anio'  => $p['anio'],
                'meses' => array_map(fn ($v) => floor($v / 1000), $p['meses']),
                'total' => floor($p['total'] / 1000),
            ];
        }, $presFull);

        return response()->json([
            'meses'        => self::MESES,
            'presentacion' => $presentacion,
            'interanual'   => $interanual,
            'en_miles'     => $miles,
        ]);
    }
}
