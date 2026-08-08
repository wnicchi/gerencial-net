<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VentasMensualController — Estadísticas › Comparativas Mensuales › Ventas.
 *
 * Migra estadistica_ventas_mensual.scx. Matriz RUBRO DE VENTA × 12 MESES para una
 * ventana móvil de 12 meses que termina en el mes/año elegido. El valor de cada venta
 * se calcula con la MISMA lógica fiscal por comprobante (FC/ND/NC letra A/B) que la
 * Comparativa de Ventas (gravado + exento, sin IVA); las NC restan. Se excluyen los
 * comprobantes de canje (CICH/CINT). Cada fila con Total Anual y Porcentaje sobre el
 * total general (orden desc) + fila TOTAL. SOLO LECTURA.
 */
class VentasMensualController extends Controller
{
    private const MES3 = ['', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    private function conn() { return DB::connection('gestion'); }

    /** GET /api/estadisticas/ventas-mensual?mes=&anio= — matriz rubro × 12 meses. */
    public function index(Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        $mes = (int) $request->query('mes', $hoy->copy()->subMonthNoOverflow()->month);
        $anio = (int) $request->query('anio', $hoy->copy()->subMonthNoOverflow()->year);
        if ($mes < 1 || $mes > 12) $mes = $hoy->month;

        // Ventana: 12 meses que terminan en (anio, mes). MFEC_DES = 1° del mes siguiente del año anterior.
        $ultMes = $mes === 12 ? 1 : $mes + 1;
        $ultAnio = $mes === 12 ? $anio + 1 : $anio;
        $fecHas = Carbon::create($ultAnio, $ultMes, 1)->subDay();          // último día del mes elegido
        $fecDes = Carbon::create($ultAnio - 1, $ultMes, 1);                // 1° del mes siguiente, año anterior

        // Etiquetas/orden de las 12 columnas (por VEN_FEC).
        $meses = []; $wf = $fecDes->copy();
        for ($i = 0; $i < 12; $i++) { $meses[] = ['key' => $wf->year * 100 + $wf->month, 'label' => self::MES3[$wf->month] . ' ' . substr((string) $wf->year, 2)]; $wf->addMonthNoOverflow(); }
        $keys = array_column($meses, 'key');

        // Rubros de venta.
        $rubros = [];
        foreach ($this->conn()->table('RUB_OPER')->get(['RUB_COD', 'RUB_DES']) as $r) $rubros[(int) $r->RUB_COD] = trim((string) $r->RUB_DES);

        // Ventas de la ventana (por VEN_FEC), con columnas fiscales.
        $ventas = $this->conn()->table('VENTAS')
            ->whereBetween('VEN_FEC', [$fecDes->format('Y-m-d'), $fecHas->format('Y-m-d')])
            ->whereRaw("LTRIM(RTRIM(VEN_TFA)) NOT IN ('CICH','CINT')")
            ->selectRaw('VEN_FEC, VEN_RUB, VEN_TFA, VEN_SUB, VEN_EXE, VEN_TOT, VEN_IIN, VEN_PII, VEN_PIB, VEN_ARB, VEN_PCABA, VEN_INO, VEN_VCN, VEN_CHR')
            ->get();

        // Matriz [rubro][mesKey] = Σ MSUB signado (NC resta).
        $mat = [];
        foreach ($ventas as $v) {
            $rub = (int) $v->VEN_RUB;
            if (!isset($rubros[$rub])) continue;
            $t2 = substr((string) $v->VEN_TFA, 0, 2);
            $val = ComparativaVentasController::valorVenta($v, false);   // gravado + exento (sin IVA), positivo
            $signo = $t2 === 'NC' ? -1 : 1;
            $f = Carbon::parse($v->VEN_FEC);
            $mk = $f->year * 100 + $f->month;
            $mat[$rub][$mk] = ($mat[$rub][$mk] ?? 0.0) + $signo * $val;
        }

        // Filas por rubro con total != 0.
        $filas = []; $totalGeneral = 0.0;
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
