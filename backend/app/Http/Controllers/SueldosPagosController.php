<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SueldosPagosController — Comparar con Pagos Bancarios (sueldos_pagos).
 *
 * Cruza lo LIQUIDADO Y PAGADO (cabeceras de liquidación pagadas en un rango) contra los
 * PAGOS BANCARIOS REALES registrados en la base de Gestión (SILCAR), para detectar diferencias
 * por banco.
 *
 *  - Lado liquidaciones (conexión RRHH): LIQUIDAC + LIQ_ITE + personal, filtrando por la fecha de
 *    PAGO de la liquidación (LIQ_PAG) dentro del "Período de Liquidación", más empresa / banco / tipo.
 *  - Lado pagos bancarios (conexión gestion = SILCAR): IMPU_ITE (imputaciones al proveedor de sueldos)
 *    unidas a MOVI_BCO (ingresos 'I') por número de movimiento, filtrando por la fecha de pago
 *    ("Pagado desde"). Se acumula el importe por cuenta bancaria (MOV_CTA) y por empresa base.
 *
 * El proveedor de sueldos de cada empresa base se toma de empresas.EMP_CHA (base 1 = SILCAR,
 * base 2 = Logística). El número de empresa base se compara contra el código de empresa del
 * empleado (personal.PER_EMP), igual que el sistema FoxPro original.
 *
 * Modos de salida:
 *  - detallado : una fila por liquidación pagada (haberes / deducciones / neto).
 *  - totalizada: totales por banco y tipo de liquidación.
 *  - bancarios : totales por banco, con lo PAGADO según banco y la DIFERENCIA.
 *
 * Nota: el FoxPro original pre-filtraba personal por PER_BAJ; en esta base esa fecha marca a los
 * empleados dados de BAJA, por lo que aplicarla dejaría afuera a casi todos los liquidados. El
 * universo correcto lo define el cruce con las liquidaciones pagadas del período.
 */
class SueldosPagosController extends Controller
{
    /**
     * @route GET /api/sueldos-pagos
     * @query modo=detallado|totalizada|bancarios
     *        liqFec1,liqFec2 (período de liquidación → LIQ_PAG)
     *        pagFec1,pagFec2 (pagado desde → gestión IMP_FEC)
     *        empresa, banco, tipo (0 = todos)
     */
    public function index(Request $request): JsonResponse
    {
        $modo  = (string) $request->input('modo', 'detallado');
        $liq1  = $request->input('liqFec1');
        $liq2  = $request->input('liqFec2');
        $pag1  = $request->input('pagFec1');
        $pag2  = $request->input('pagFec2');

        // ── Lado liquidaciones (cabecera pagada por LIQ_PAG dentro del período) ──
        $q = DB::table('LIQUIDAC as l')
            ->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->join('personal as p', 'l.LIQ_COD', '=', 'p.PER_COD')
            ->leftJoin('ctas_ban as cb', 'p.PER_BAN', '=', 'cb.CBA_COD');

        if ($liq1) $q->whereDate('l.LIQ_PAG', '>=', $liq1);
        if ($liq2) $q->whereDate('l.LIQ_PAG', '<=', $liq2);
        if ($emp = (int) $request->input('empresa', 0))  $q->where('p.PER_EMP', $emp);
        if ($ban = (int) $request->input('banco', 0))    $q->where('p.PER_BAN', $ban);
        if ($tip = (int) $request->input('tipo', 0))     $q->where('l.LIQ_TIP', $tip);

        $rows = $q->groupBy(
                'l.LIQ_NRO', 'l.LIQ_COD', 'l.LIQ_DEP', 'p.PER_EMP', 'p.PER_BAN', 'cb.CBA_DES',
                'l.LIQ_TIP', 'l.LIQ_TID', 'l.LIQ_MES', 'l.LIQ_ANO', 'l.LIQ_FEC', 'l.LIQ_PAG'
            )
            ->orderByRaw('cb.CBA_DES, l.LIQ_TID, l.LIQ_DEP')
            ->get([
                'l.LIQ_NRO', 'l.LIQ_COD', 'l.LIQ_DEP', 'p.PER_EMP', 'p.PER_BAN', 'cb.CBA_DES',
                'l.LIQ_TIP', 'l.LIQ_TID', 'l.LIQ_MES', 'l.LIQ_ANO', 'l.LIQ_FEC', 'l.LIQ_PAG',
                DB::raw('SUM(li.LIT_HAB) as haberes'), DB::raw('SUM(li.LIT_DED) as deducciones'),
            ]);

        $liquidaciones = [];
        $totGeneral = 0.0;
        foreach ($rows as $r) {
            $hab = round((float) $r->haberes, 2);
            $ded = round((float) $r->deducciones, 2);
            $neto = round($hab - $ded, 2);
            $totGeneral += $neto;
            $liquidaciones[] = [
                'nro'          => (int) $r->LIQ_NRO,
                'codigo'       => (int) $r->LIQ_COD,
                'nombre'       => trim((string) $r->LIQ_DEP),
                'empCod'       => (int) $r->PER_EMP,
                'bancoCod'     => (int) $r->PER_BAN,
                'bancoDesc'    => trim((string) ($r->CBA_DES ?? '')) ?: 'SIN BANCO',
                'tipo'         => (int) $r->LIQ_TIP,
                'tipoDesc'     => trim((string) $r->LIQ_TID) ?: 'SIN TIPO',
                'mes'          => (int) $r->LIQ_MES,
                'anio'         => (int) $r->LIQ_ANO,
                'fecha'        => $this->fecha($r->LIQ_FEC),
                'pago'         => $this->fecha($r->LIQ_PAG),
                'haberes'      => $hab,
                'deducciones'  => $ded,
                'neto'         => $neto,
            ];
        }

        // ── Lado pagos bancarios reales (conexión gestión / SILCAR) ──
        $pagban = $this->pagosBancarios($pag1, $pag2);

        // ── Totales por banco (banco + empresa) con lo pagado y la diferencia ──
        $bancosMap = [];
        foreach ($liquidaciones as $l) {
            $k = $l['empCod'] . '-' . $l['bancoCod'];
            if (!isset($bancosMap[$k])) {
                $bancosMap[$k] = [
                    'empCod'    => $l['empCod'], 'bancoCod' => $l['bancoCod'], 'bancoDesc' => $l['bancoDesc'],
                    'liquidado' => 0.0, 'pagadoBanco' => round((float) ($pagban[$l['empCod']][$l['bancoCod']] ?? 0), 2),
                ];
            }
            $bancosMap[$k]['liquidado'] += $l['neto'];
        }
        $bancos = [];
        foreach ($bancosMap as $b) {
            $b['liquidado'] = round($b['liquidado'], 2);
            $b['diferencia'] = round($b['liquidado'] - $b['pagadoBanco'], 2);
            $bancos[] = $b;
        }
        usort($bancos, fn ($a, $b) => strcmp($a['bancoDesc'], $b['bancoDesc']));

        return response()->json([
            'modo'          => $modo,
            'liquidaciones' => $liquidaciones,
            'bancos'        => $bancos,
            'totalGeneral'  => round($totGeneral, 2),
            'cantidad'      => count($liquidaciones),
        ]);
    }

    /**
     * Acumula los pagos bancarios reales por [empresa base][cuenta bancaria] desde Gestión.
     * @return array<int, array<int, float>>
     */
    private function pagosBancarios(?string $pag1, ?string $pag2): array
    {
        // Proveedor de sueldos por empresa base (1 = SILCAR, 2 = Logística).
        $cuentas = [];
        foreach ([1, 2] as $base) {
            $e = DB::table('empresas')->where('EMP_BAS', $base)->where('EMP_CHA', '>', 0)->orderBy('EMP_COD')->first();
            if ($e) $cuentas[$base] = (int) $e->EMP_CHA;
        }

        $pagban = [];
        foreach ($cuentas as $base => $cuenta) {
            try {
                $g = DB::connection('gestion')->table('IMPU_ITE as i')
                    ->join('MOVI_BCO as m', 'i.IMP_NRO', '=', 'm.MOV_NRO')
                    ->where('m.MOV_TIP', 'I')
                    ->where('i.IMP_PVR', $cuenta);
                if ($pag1) $g->whereDate('i.IMP_FEC', '>=', $pag1);
                if ($pag2) $g->whereDate('i.IMP_FEC', '<=', $pag2);
                $sumas = $g->groupBy('m.MOV_CTA')->get(['m.MOV_CTA', DB::raw('SUM(i.IMP_IMP) as imp')]);
                foreach ($sumas as $s) {
                    $pagban[$base][(int) $s->MOV_CTA] = ($pagban[$base][(int) $s->MOV_CTA] ?? 0) + (float) $s->imp;
                }
            } catch (\Throwable $e) {
                \Log::warning('[SueldosPagos] gestión base ' . $base . ': ' . $e->getMessage());
            }
        }
        return $pagban;
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y');
    }
}
