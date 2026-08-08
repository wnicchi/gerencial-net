<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ComprasMensualController — Estadísticas › Comparativas Mensuales › Compras.
 *
 * Migra estadistica_compras_mensual.scx. Matriz RUBRO DE COMPRA × 12 MESES para una
 * ventana móvil de 12 meses que termina en el mes/año elegido. Cada celda es la suma
 * de las compras (COM_BRU+COM_INO) de ese rubro imputadas a ese mes (COM_IME/COM_IAN);
 * las notas de crédito restan. Se excluyen los comprobantes de impuestos ND I / NC I.
 * Cada fila tiene su Total Anual y su Porcentaje sobre el total general. SOLO LECTURA.
 */
class ComprasMensualController extends Controller
{
    private const MES3 = ['', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    private function conn() { return DB::connection('gestion'); }

    /** GET /api/estadisticas/compras-mensual?mes=&anio= — matriz rubro × 12 meses. */
    public function index(Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        $mes = (int) $request->query('mes', $hoy->copy()->subMonthNoOverflow()->month);
        $anio = (int) $request->query('anio', $hoy->copy()->subMonthNoOverflow()->year);
        if ($mes < 1 || $mes > 12) $mes = $hoy->month;

        // 12 meses que terminan en (anio, mes). Índice = anio*12 + (mes-1) [0-based].
        $fin = $anio * 12 + ($mes - 1);
        $ini = $fin - 11;
        $meses = [];            // orden de columnas: [ ['key'=>y*12+m1, 'label'=>'AGO 25', 'y'=>..,'m'=>..], ... ]
        for ($idx = $ini; $idx <= $fin; $idx++) {
            $y = intdiv($idx, 12); $m = ($idx % 12) + 1;
            $meses[] = ['key' => $y * 100 + $m, 'label' => self::MES3[$m] . ' ' . substr((string) $y, 2), 'y' => $y, 'm' => $m];
        }
        $keys = array_column($meses, 'key');
        $iniYm = $meses[0]['y'] * 12 + $meses[0]['m'];   // 1-based mes
        $finYm = $meses[11]['y'] * 12 + $meses[11]['m'];

        // Rubros de compra.
        $rubros = [];
        foreach ($this->conn()->table('RUB_CPRA')->get(['RUB_COD', 'RUB_DES']) as $r) $rubros[(int) $r->RUB_COD] = trim((string) $r->RUB_DES);

        // Compras imputadas dentro de la ventana (por COM_IAN/COM_IME), NC resta, sin ND I / NC I.
        $rows = $this->conn()->select(
            "SELECT COM_RUB, COM_IAN, COM_IME,
                    SUM(CASE WHEN LEFT(COM_TFA,2)='NC' THEN -(ISNULL(COM_BRU,0)+ISNULL(COM_INO,0)) ELSE (ISNULL(COM_BRU,0)+ISNULL(COM_INO,0)) END) monto
             FROM COMPRAS
             WHERE (COM_IAN*12 + COM_IME) BETWEEN ? AND ?
               AND LTRIM(RTRIM(COM_TFA)) NOT IN ('ND I','NC I')
             GROUP BY COM_RUB, COM_IAN, COM_IME",
            [$iniYm, $finYm]
        );

        // Matriz [rubro][mesKey] = monto.
        $mat = [];
        foreach ($rows as $r) {
            $rub = (int) $r->COM_RUB;
            if (!isset($rubros[$rub])) continue;   // sólo rubros del catálogo (como el Fox)
            $mk = (int) $r->COM_IAN * 100 + (int) $r->COM_IME;
            $mat[$rub][$mk] = ($mat[$rub][$mk] ?? 0.0) + (float) $r->monto;
        }

        // Filas por rubro con total != 0.
        $filas = [];
        $totalGeneral = 0.0;
        foreach ($rubros as $cod => $des) {
            $montos = array_map(fn ($k) => round($mat[$cod][$k] ?? 0.0, 2), $keys);
            $total = round(array_sum($montos), 2);
            if ($total == 0.0) continue;
            $filas[] = ['codigo' => $cod, 'nombre' => $des, 'montos' => $montos, 'total' => $total];
            $totalGeneral += $total;
        }
        usort($filas, fn ($a, $b) => $b['total'] <=> $a['total']);
        foreach ($filas as &$f) $f['porcentaje'] = $totalGeneral != 0.0 ? round($f['total'] / $totalGeneral * 100, 2) : 0.0;
        unset($f);

        // Fila TOTAL (por mes + total general + 100%).
        $totMes = array_fill(0, 12, 0.0);
        foreach ($filas as $f) foreach ($f['montos'] as $i => $v) $totMes[$i] += $v;

        return response()->json([
            'mes' => $mes, 'anio' => $anio,
            'meses' => array_column($meses, 'label'),
            'filas' => $filas,
            'total' => [
                'montos' => array_map(fn ($v) => round($v, 2), $totMes),
                'total' => round($totalGeneral, 2),
                'porcentaje' => round(array_sum(array_column($filas, 'porcentaje')), 2),
            ],
        ]);
    }
}
