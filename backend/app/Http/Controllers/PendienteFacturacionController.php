<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PendienteFacturacionController — Tablero Gerencial › Gestión (Logística) ›
 * Pendiente de Facturación.
 *
 * Réplica del informe de GESTION.NET (Ventas › Pendiente de Facturación), que a su
 * vez migra el Fox `log_pendiente_de_facturacion_general.scx`. SOLO LECTURA sobre la
 * base de GESTIÓN (conexión 'gestion' = sqlLOGIST). Suma 3 orígenes:
 *   1. CONTRATOS   → LOG_CONCEPTOS_CONSUMOS (CPT_VEN=0) ⨝ LOG_CONTRATOS_MODELO_DE_FACTURAS.
 *   2. TRANSPORTES → FACTURAR_VIAJES pendientes (último año) ⨝ VIAJES_CLIENTES ⨝ CLIENTES.
 *   3. SERVICIOS   → WFSER_SERVICIOS sin facturar (WSER_FFAC=1900-01-01) ⨝ WFSER_CLIENTES.
 * No escribe nada.
 */
class PendienteFacturacionController extends Controller
{
    private const CONEXION = 'gestion';

    /** @route GET /api/tablero/gestion/pendiente-facturacion */
    public function index(): JsonResponse
    {
        try {
            $filas = array_merge($this->contratos(), $this->transportes(), $this->servicios());

            usort($filas, fn ($a, $b) =>
                [$a['cliente_nombre'], $a['servicio'], $a['anio'], $a['mes']]
                <=> [$b['cliente_nombre'], $b['servicio'], $b['anio'], $b['mes']]);

            $sub = fn (string $srv) => round(array_sum(array_map(
                fn ($f) => $f['servicio'] === $srv ? $f['monto_neto'] : 0, $filas)), 2);
            $contratos = $sub('CONTRATOS'); $transportes = $sub('TRANSPORTES'); $servicios = $sub('SERVICIOS');

            // Ranking por cliente (para el gráfico gerencial).
            $porCliente = [];
            foreach ($filas as $f) {
                $k = $f['cliente_codigo'];
                $porCliente[$k] ??= ['cliente' => $f['cliente_nombre'], 'monto' => 0.0];
                $porCliente[$k]['monto'] += $f['monto_neto'];
            }
            $porCliente = array_values($porCliente);
            foreach ($porCliente as &$c) $c['monto'] = round($c['monto'], 2);
            unset($c);
            usort($porCliente, fn ($a, $b) => $b['monto'] <=> $a['monto']);

            return response()->json([
                'filas'       => $filas,
                'contratos'   => $contratos,
                'transportes' => $transportes,
                'servicios'   => $servicios,
                'total'       => round($contratos + $transportes + $servicios, 2),
                'porCliente'  => $porCliente,
                'clientes'    => count($porCliente),
            ]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, request(), 'TABLERO-GESTION');
            return response()->json(['message' => 'No se pudo calcular el Pendiente de Facturación (base de Gestión).'], 500);
        }
    }

    /** CONTRATOS: consumos no facturados (CPT_VEN=0) que tienen modelo de factura. */
    private function contratos(): array
    {
        $rows = DB::connection(self::CONEXION)->table('LOG_CONCEPTOS_CONSUMOS as c')
            ->join('LOG_CONTRATOS_MODELO_DE_FACTURAS as m', function ($j) {
                $j->on('c.CPT_NRO', '=', 'm.CMF_NRO')->on('c.CPT_ORD', '=', 'm.CMF_ORD');
            })
            ->where('c.CPT_VEN', 0)
            ->groupBy('c.CPT_CLI', DB::raw('RTRIM(c.CPT_CLD)'), 'c.CPT_IME', 'c.CPT_IAN')
            ->get([
                DB::raw('c.CPT_CLI AS cliente_codigo'),
                DB::raw('RTRIM(c.CPT_CLD) AS cliente_nombre'),
                DB::raw('c.CPT_IME AS mes'),
                DB::raw('c.CPT_IAN AS anio'),
                DB::raw('SUM(c.CPT_TOT) AS monto_neto'),
            ]);
        return $this->normalizar($rows, 'CONTRATOS');
    }

    /** TRANSPORTES: viajes pendientes del último año, por cliente real (mes/año actual). */
    private function transportes(): array
    {
        $desde = Carbon::today()->subDays(365)->toDateString();
        $hasta = Carbon::today()->toDateString();
        $mes   = (int) Carbon::today()->format('n');
        $anio  = (int) Carbon::today()->format('Y');

        $rows = DB::connection(self::CONEXION)->table('FACTURAR_VIAJES as fv')
            ->join('VIAJES_CLIENTES as vc', function ($j) {
                $j->on('fv.VIA_CLI', '=', 'vc.VIR_TRA')->where('vc.VIR_CLI', '>', 0);
            })
            ->join('CLIENTES as cl', 'vc.VIR_CLI', '=', 'cl.CLI_COD')
            ->where(function ($q) {
                $q->where(fn ($w) => $w->where('fv.FACTURADO', 0)->where('fv.IMPORTE_FACTURA', '>', 0))
                  ->orWhere(fn ($w) => $w->where('fv.FACTURADO_ADICIONAL', 0)->where('fv.IMPORTE_FACTURA_AD', '>', 0));
            })
            ->whereBetween('fv.VIA_FIN', [$desde, $hasta])
            ->groupBy('cl.CLI_COD', DB::raw('RTRIM(cl.CLI_NOM)'))
            ->get([
                DB::raw('cl.CLI_COD AS cliente_codigo'),
                DB::raw('RTRIM(cl.CLI_NOM) AS cliente_nombre'),
                DB::raw("$mes AS mes"),
                DB::raw("$anio AS anio"),
                DB::raw('SUM(fv.IMPORTE_FACTURA + fv.IMPORTE_FACTURA_AD) AS monto_neto'),
            ]);
        return $this->normalizar($rows, 'TRANSPORTES');
    }

    /** SERVICIOS: servicios WFSER sin facturar (WSER_FFAC=1900-01-01), por cliente real. */
    private function servicios(): array
    {
        $rows = DB::connection(self::CONEXION)->table('WFSER_SERVICIOS as s')
            ->join('WFSER_CLIENTES as wc', 's.WSER_CLI', '=', 'wc.CLI_COD')
            ->whereRaw("CAST(s.WSER_FFAC AS DATE) = '1900-01-01'")
            ->groupBy('wc.CLI_REAL', DB::raw('RTRIM(wc.CLI_NOMREAL)'), DB::raw('MONTH(s.WSER_FEC)'), DB::raw('YEAR(s.WSER_FEC)'))
            ->get([
                DB::raw('wc.CLI_REAL AS cliente_codigo'),
                DB::raw('RTRIM(wc.CLI_NOMREAL) AS cliente_nombre'),
                DB::raw('MONTH(s.WSER_FEC) AS mes'),
                DB::raw('YEAR(s.WSER_FEC) AS anio'),
                DB::raw('SUM(s.WSER_IMPORTE) AS monto_neto'),
            ]);
        return $this->normalizar($rows, 'SERVICIOS');
    }

    /** Normaliza tipos y agrega el nombre del servicio. */
    private function normalizar($rows, string $servicio): array
    {
        return $rows->map(fn ($r) => [
            'cliente_codigo' => (int) $r->cliente_codigo,
            'cliente_nombre' => trim((string) $r->cliente_nombre),
            'mes'            => (int) $r->mes,
            'anio'           => (int) $r->anio,
            'servicio'       => $servicio,
            'monto_neto'     => round((float) $r->monto_neto, 2),
        ])->all();
    }
}
