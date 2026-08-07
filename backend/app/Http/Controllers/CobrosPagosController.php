<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CobrosPagosController — Estadísticas › Estadística Cobros y Pagos (mensual).
 *
 * Migra estadistica_cobros_pagos_mensual.scx. Muestra, para una ventana móvil de
 * 12 meses (que termina en el mes/año elegido), el PROMEDIO DE DÍAS ponderado por
 * importe de:
 *   - COBROS (RECIBOS: REC_SUB × REC_PD3, rubro 1)
 *   - PAGOS por proveedor (O_PAGOS: OPA_GRA × OPA_PD3), clasificados por OPA_CO1:
 *       12343 → TOYOTA EQUIPOS (2) · 3805/11528 → TOYOTA RESPUESTOS (3) · resto → OTROS (4)
 * Cada celda = Σ(importe×días) / Σ(importe), truncado (como el Fox, campo N(16,2)
 * mostrado como entero). "Promedio Anual" = el mismo cociente sobre los 12 meses.
 *
 * SOLO LECTURA. Ventana: 12 meses que terminan en (anio, mes).
 */
class CobrosPagosController extends Controller
{
    private function conn()
    {
        return DB::connection('gestion');
    }

    private const ABREV = ['', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    /**
     * GET /api/estadisticas/cobros-pagos — informe.
     * Params: mes (1-12), anio. Default: mes/año de hoy-30 (mes anterior), como el Fox.
     */
    public function calcular(Request $request): JsonResponse
    {
        $hace30 = Carbon::now()->subDays(30);
        $mes  = max(1, min(12, (int) $request->query('mes', $hace30->month)));
        $anio = (int) $request->query('anio', $hace30->year);

        // Ventana: 12 meses que terminan en (anio, mes).
        $finMes = Carbon::create($anio, $mes, 1);
        $ini    = $finMes->copy()->subMonths(11);
        $desde  = $ini->format('Y-m-d');
        $hasta  = $finMes->copy()->endOfMonth()->format('Y-m-d');

        // Columnas: 12 meses desde $ini.
        $columnas = [];
        $cur = $ini->copy();
        for ($i = 0; $i < 12; $i++) {
            $columnas[] = [
                'key'   => $cur->year . '-' . $cur->month,
                'label' => self::ABREV[$cur->month] . ' ' . substr((string) $cur->year, 2),
            ];
            $cur->addMonth();
        }

        $c = $this->conn();

        // COBROS (rubro 1): RECIBOS.
        $cobros = $c->table('RECIBOS')
            ->whereBetween('REC_FEC', [$desde, $hasta])
            ->selectRaw('YEAR(REC_FEC) a, MONTH(REC_FEC) m, SUM(REC_SUB*REC_PD3) sp, SUM(REC_SUB) s')
            ->groupByRaw('YEAR(REC_FEC), MONTH(REC_FEC)')->get();

        // PAGOS (rubros 2/3/4 por OPA_CO1): O_PAGOS.
        $rubroCase = "CASE WHEN OPA_CO1=12343 THEN 2 WHEN OPA_CO1 IN (3805,11528) THEN 3 ELSE 4 END";
        $pagos = $c->table('O_PAGOS')
            ->whereBetween('OPA_FEC', [$desde, $hasta])
            ->selectRaw("YEAR(OPA_FEC) a, MONTH(OPA_FEC) m, $rubroCase rubro, SUM(OPA_GRA*OPA_PD3) sp, SUM(OPA_GRA) s")
            ->groupByRaw("YEAR(OPA_FEC), MONTH(OPA_FEC), $rubroCase")->get();

        // Acumular por rubro y mes: [rubro][key] = ['sp'=>, 's'=>]
        $acum = [];
        $sumar = function (int $rubro, $r) use (&$acum) {
            $k = ((int) $r->a) . '-' . ((int) $r->m);
            $acum[$rubro][$k]['sp'] = ($acum[$rubro][$k]['sp'] ?? 0) + (float) $r->sp;
            $acum[$rubro][$k]['s']  = ($acum[$rubro][$k]['s'] ?? 0) + (float) $r->s;
        };
        foreach ($cobros as $r) $sumar(1, $r);
        foreach ($pagos as $r)  $sumar((int) $r->rubro, $r);

        // Filas fijas (igual que el Fox). rubro 0 = separador "PAGOS PVR:".
        $definicion = [
            ['rubro' => 1, 'nombre' => 'COBROS'],
            ['rubro' => 0, 'nombre' => 'PAGOS PVR:'],
            ['rubro' => 2, 'nombre' => 'TOYOTA EQUIPOS'],
            ['rubro' => 3, 'nombre' => 'TOYOTA RESPUESTOS'],
            ['rubro' => 4, 'nombre' => 'OTROS PROVEEDORES'],
        ];

        $filas = [];
        foreach ($definicion as $def) {
            $valores = []; $totSP = 0.0; $totS = 0.0;
            foreach ($columnas as $col) {
                $d = $acum[$def['rubro']][$col['key']] ?? null;
                if ($d && $d['s'] != 0) {
                    $valores[] = (int) floor($d['sp'] / $d['s']);   // truncado, como el Fox
                    $totSP += $d['sp']; $totS += $d['s'];
                } else {
                    $valores[] = 0;
                }
            }
            $filas[] = [
                'rubro'    => $def['rubro'],
                'nombre'   => $def['nombre'],
                'valores'  => $valores,
                'promedio' => $totS != 0 ? (int) round($totSP / $totS) : 0,
                'separador'=> $def['rubro'] === 0,   // "PAGOS PVR:" es encabezado
            ];
        }

        return response()->json([
            'mes'      => $mes,
            'anio'     => $anio,
            'columnas' => array_map(fn ($x) => $x['label'], $columnas),
            'filas'    => $filas,
        ]);
    }
}
