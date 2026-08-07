<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ComparativaUtilidadesController — Estadísticas › Comparativa de Utilidades.
 *
 * Migra estadistica_comparativa_utilidades.scx. Utilidad mensual (desde 2012) =
 *   VENTAS  (SUM VEN_TOT con IVA / VEN_SUB sin IVA; NC resta; directo, sin lógica
 *            fiscal por comprobante)
 *   − GASTOS (COMPRAS: SUM COM_NET con NC restando  +  IMPUESTO: SUM PIM_IMP)
 * Todo por MONTH/YEAR de la fecha real (VEN_FEC / COM_FEC / PIM_FEC). Arma la tabla
 * de presentación (año × 12 meses + total) y la interanual de variación %.
 *
 * SOLO LECTURA. Los valores pueden ser negativos (pérdida).
 */
class ComparativaUtilidadesController extends Controller
{
    private function conn()
    {
        return DB::connection('gestion');
    }

    private const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Setiembre','Octubre','Noviembre','Diciembre'];
    private const ANIO_DESDE = 2012;

    /**
     * GET /api/estadisticas/comparativa-utilidades — informe.
     * Params: incluye_iva (0|1, default 1).
     */
    public function calcular(Request $request): JsonResponse
    {
        $inclIva = (int) $request->query('incluye_iva', 1) === 1;
        $c = $this->conn();
        $desde = self::ANIO_DESDE . '-01-01';
        $anioHoy = (int) Carbon::now()->year;

        // Ventas (NC resta): VEN_TOT (con IVA) o VEN_SUB (sin IVA).
        $colVta = $inclIva ? 'VEN_TOT' : 'VEN_SUB';
        $ventas = $c->table('VENTAS')->where('VEN_FEC', '>=', $desde)
            ->selectRaw("YEAR(VEN_FEC) a, MONTH(VEN_FEC) m, SUM((CASE WHEN LEFT(VEN_TFA,2)='NC' THEN -1 ELSE 1 END)*ISNULL($colVta,0)) v")
            ->groupByRaw('YEAR(VEN_FEC), MONTH(VEN_FEC)')->get();

        // Compras (NC resta): COM_NET.
        $compras = $c->table('COMPRAS')->where('COM_FEC', '>=', $desde)
            ->selectRaw("YEAR(COM_FEC) a, MONTH(COM_FEC) m, SUM((CASE WHEN LEFT(COM_TFA,2)='NC' THEN -1 ELSE 1 END)*ISNULL(COM_NET,0)) v")
            ->groupByRaw('YEAR(COM_FEC), MONTH(COM_FEC)')->get();

        // Impuestos (pagos varios): PIM_IMP.
        $impuestos = $c->table('IMPUESTO')->where('PIM_FEC', '>=', $desde)
            ->selectRaw('YEAR(PIM_FEC) a, MONTH(PIM_FEC) m, SUM(ISNULL(PIM_IMP,0)) v')
            ->groupByRaw('YEAR(PIM_FEC), MONTH(PIM_FEC)')->get();

        // Utilidad[anio][mes] = ventas − (compras + impuestos).
        $vta = []; $gas = [];
        foreach ($ventas as $r)    $vta[(int) $r->a][(int) $r->m] = (float) $r->v;
        foreach ($compras as $r)   $gas[(int) $r->a][(int) $r->m] = ($gas[(int) $r->a][(int) $r->m] ?? 0) + (float) $r->v;
        foreach ($impuestos as $r) $gas[(int) $r->a][(int) $r->m] = ($gas[(int) $r->a][(int) $r->m] ?? 0) + (float) $r->v;

        $presentacion = [];
        for ($a = self::ANIO_DESDE; $a <= $anioHoy; $a++) {
            $meses = []; $tot = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $u = round(($vta[$a][$m] ?? 0) - ($gas[$a][$m] ?? 0), 2);
                $meses[] = $u; $tot += $u;
            }
            $presentacion[] = ['anio' => $a, 'meses' => $meses, 'total' => round($tot, 2)];
        }

        // Interanual: variación % año vs. anterior (mes a mes + total).
        $interanual = [];
        for ($i = 1; $i < count($presentacion); $i++) {
            $act = $presentacion[$i]; $ant = $presentacion[$i - 1]; $por = [];
            for ($m = 0; $m < 12; $m++) {
                $por[] = $ant['meses'][$m] != 0 ? round(($act['meses'][$m] * 100 / $ant['meses'][$m]) - 100, 2) : 0;
            }
            $porTot = $ant['total'] != 0 ? round(($act['total'] * 100 / $ant['total']) - 100, 2) : 0;
            $interanual[] = ['detalle' => $act['anio'] . ' Vs. ' . $ant['anio'], 'meses' => $por, 'total' => $porTot];
        }

        return response()->json([
            'meses'        => self::MESES,
            'presentacion' => $presentacion,
            'interanual'   => $interanual,
            'incluye_iva'  => $inclIva,
        ]);
    }
}
