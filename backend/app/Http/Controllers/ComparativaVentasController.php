<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ComparativaVentasController — Estadísticas › Comparativa de Ventas.
 *
 * Migra el form Fox estadistica_comparativa_ventas.scx (botón GENERAR INFORME).
 * Arma la tabla de ventas por mes y año (desde un mes/año hasta hoy) y la tabla
 * interanual de variación % (año vs. año anterior). Filtros: rubro (todos/uno),
 * moneda (PESOS/DÓLARES), incluye IVA (sí/no).
 *
 * SOLO LECTURA (informe). Replica la lógica fiscal del Fox por tipo de comprobante
 * (FC/ND/NC, letra A/B), gravado + exento (VEN_SUB) o total con IVA (VEN_TOT); las
 * Notas de Crédito (NC) restan. En dólares divide por la cotización (VEN_COT, o la
 * de la tabla DOLAR por fecha cuando VEN_COT = 1). El PERIODO contable del Fox no
 * afecta los totales (son por mes calendario) → se omite.
 */
class ComparativaVentasController extends Controller
{
    private function conn()
    {
        return DB::connection('gestion');
    }

    private const MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','setiembre','octubre','noviembre','diciembre'];

    /** GET /api/estadisticas/comparativa-ventas/catalogo — rubros + año mínimo. */
    public function catalogo(): JsonResponse
    {
        $c = $this->conn();
        $rubros = $c->table('RUB_OPER')->where('RUB_ACT', 0)->where('RUB_COD', '<', 999)
            ->orderBy('RUB_DES')->get(['RUB_COD', 'RUB_DES'])
            ->map(fn ($r) => ['cod' => (int) $r->RUB_COD, 'des' => trim((string) $r->RUB_DES)])->values();
        $anioMin = (int) Carbon::parse($c->table('VENTAS')->min('VEN_FEC'))->year;
        return response()->json([
            'rubros'    => $rubros,
            'anio_min'  => $anioMin,
            'anio_hoy'  => (int) Carbon::now()->year,
        ]);
    }

    /**
     * GET /api/estadisticas/comparativa-ventas — informe comparativo.
     * Params: rubro (0=todos | cod), moneda (P|D), desde_mes (1-12), desde_anio, incluye_iva (0|1).
     */
    public function calcular(Request $request): JsonResponse
    {
        $rubro   = (int) $request->query('rubro', 0);            // 0 = todos
        $moneda  = $request->query('moneda', 'P') === 'D' ? 'D' : 'P';
        $desdeMes  = max(1, min(12, (int) $request->query('desde_mes', 1)));
        $desdeAnio = (int) $request->query('desde_anio', Carbon::now()->year);
        $inclIva = (int) $request->query('incluye_iva', 0) === 1;

        $c = $this->conn();
        $fecDes = Carbon::create($desdeAnio, $desdeMes, 1)->format('Y-m-d');

        // Ventas desde la fecha (solo columnas necesarias). Filtra por rubro si corresponde.
        $q = $c->table('VENTAS')
            ->where('VEN_FEC', '>=', $fecDes)
            ->selectRaw('VEN_FEC, VEN_RUB, VEN_TFA, VEN_SUB, VEN_EXE, VEN_TOT, VEN_IIN, VEN_PII, VEN_PIB, VEN_ARB, VEN_PCABA, VEN_INO, VEN_VCN, VEN_CHR, VEN_COT, VEN_NFA, VEN_SUC');
        if ($rubro > 0) $q->where('VEN_RUB', $rubro);
        $ventas = $q->get();

        // Cotizaciones por fecha (para VEN_COT = 1) — solo si es en dólares.
        $dolar = [];
        if ($moneda === 'D') {
            foreach ($c->table('DOLAR')->get(['DOL_FEC', 'DOL_COT']) as $d) {
                $dolar[Carbon::parse($d->DOL_FEC)->format('Y-m-d')] = (float) $d->DOL_COT;
            }
        }

        // Acumuladores: [anio][mes] = monto.
        $acum = [];
        $errores = [];   // ventas sin cotización (solo dólares)
        foreach ($ventas as $v) {
            $tfa = (string) $v->VEN_TFA;
            $t2  = substr($tfa, 0, 2);
            $base = self::valorVenta($v, $inclIva);   // en pesos (gravado+exento o total)

            if ($moneda === 'D') {
                $cot = (float) $v->VEN_COT;
                if ($cot == 1.0) $cot = $dolar[Carbon::parse($v->VEN_FEC)->format('Y-m-d')] ?? 1.0;
                if ($cot < 2 && (int) $v->VEN_NFA > 0 && count($errores) <= 20) {
                    $errores[] = trim($tfa) . ' ' . ((int) $v->VEN_SUC) . '-' . str_pad((string) (int) $v->VEN_NFA, 6, '0', STR_PAD_LEFT)
                        . ' del ' . Carbon::parse($v->VEN_FEC)->format('d/m/Y');
                }
                $base = $cot > 0 ? round($base / $cot, 2) : $base;
            }

            $signo = $t2 === 'NC' ? -1 : 1;
            $f = Carbon::parse($v->VEN_FEC);
            $acum[$f->year][$f->month] = ($acum[$f->year][$f->month] ?? 0) + $signo * $base;
        }

        if ($moneda === 'D' && count($errores) > 0) {
            return response()->json([
                'error'    => 'Hay ventas sin cotización de dólar cargada.',
                'detalles' => $errores,
            ], 422);
        }

        // Tabla de presentación: una fila por año (desdeAnio..hoy), 12 meses + total.
        $anioHoy = (int) Carbon::now()->year;
        $presentacion = [];
        for ($a = $desdeAnio; $a <= $anioHoy; $a++) {
            $meses = [];
            $tot = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $val = round($acum[$a][$m] ?? 0, 2);
                $meses[] = $val;
                $tot += $val;
            }
            $presentacion[] = ['anio' => $a, 'meses' => $meses, 'total' => round($tot, 2)];
        }

        // Tabla interanual: variación % de cada año vs. el anterior (por mes + total).
        $interanual = [];
        for ($i = 1; $i < count($presentacion); $i++) {
            $act = $presentacion[$i];
            $ant = $presentacion[$i - 1];
            $por = [];
            for ($m = 0; $m < 12; $m++) {
                $por[] = $ant['meses'][$m] != 0 ? round(($act['meses'][$m] * 100 / $ant['meses'][$m]) - 100, 2) : 0;
            }
            $porTot = $ant['total'] != 0 ? round(($act['total'] * 100 / $ant['total']) - 100, 2) : 0;
            $interanual[] = ['detalle' => $act['anio'] . ' Vs. ' . $ant['anio'], 'meses' => $por, 'total' => $porTot];
        }

        return response()->json([
            'meses'        => array_map('ucfirst', self::MESES),
            'presentacion' => $presentacion,
            'interanual'   => $interanual,
            'moneda'       => $moneda === 'D' ? 'DÓLARES' : 'PESOS',
            'incluye_iva'  => $inclIva,
        ]);
    }

    /**
     * Valor de una venta según la lógica fiscal del Fox (GENERAR INFORME).
     * Devuelve el importe POSITIVO (gravado+exento, o total con IVA); el signo de
     * las NC lo aplica el que acumula. Réplica 1:1 del SCAN de veRVENTAS del Fox.
     */
    public static function valorVenta($v, bool $inclIva): float
    {
        $tfa = (string) $v->VEN_TFA;
        $t2  = substr($tfa, 0, 2);
        $t4  = substr($tfa, 3, 1);
        $sub = (float) $v->VEN_SUB; $exe = (float) $v->VEN_EXE; $tot = (float) $v->VEN_TOT;
        $iin = (float) $v->VEN_IIN; $pii = (float) $v->VEN_PII; $pib = (float) $v->VEN_PIB;
        $arb = (float) $v->VEN_ARB; $cab = (float) $v->VEN_PCABA; $ino = (float) $v->VEN_INO;
        $vcn = (string) $v->VEN_VCN; $chr = (string) $v->VEN_CHR;

        $mgra = 0.0; $mexe = $exe; $miin = $iin;

        if ($t2 === 'ND') {
            if ($chr === 'S') {
                $mgra = $pii != 0 ? $iin / ($pii / 100) : 0;
                $mexe = $tot - ($mgra + $iin + $pib + $arb + $cab);
            } else {
                if (substr($tfa, 0, 4) === 'ND B') {
                    if ($pii > 0) { $mgra = $pii != 0 ? $tot / (1 + $pii / 100) : 0; $miin = $tot - $mgra; }
                    else { $mgra = 0; $mexe = $tot - ($pib + $arb + $cab); }
                }
                if ($t2 === 'ND' && $t4 === 'A') {
                    if ($iin > 0) {
                        $mgra = $pii != 0 ? $iin / ($pii / 100) : 0;
                        $mexe = $sub - $mgra;
                        if ($mexe >= -5 && $mexe <= 5) { $mgra += $mexe; $mexe = 0; }
                    } else { $mgra = 0; $miin = 0; $mexe = $tot - ($pib + $arb + $cab); }
                }
            }
        }
        if ($t2 === 'NC') {
            if ($t4 === 'A') {
                $mgra = $pii != 0 ? $iin / ($pii / 100) : 0;
                if ($iin != 0) $mgra = $sub;
                $mexe = $tot - ($mgra + $iin + $ino + $pib + $arb + $cab);
                if ($mexe >= -0.03 && $mexe <= 0.03) { $mgra += $mexe; $mexe = 0; }
            }
            if ($t4 === 'B') {
                $mgra = $pii != 0 ? ($tot - ($pib + $arb + $cab)) / (1 + $pii / 100) : 0;
                if (substr($vcn, 0, 2) === 'NO') $mgra = 0;
                if ($exe == 0) $miin = $tot - ($pib + $arb + $cab) + $exe - $mgra;
                else $mexe = $exe;
            }
        }
        if ($t2 === 'FC') {
            $mgra = $sub;
            if ($t4 === 'B') {
                if ($miin == 0) {
                    if (substr($vcn, 0, 2) !== 'NO') { $mgra = $sub / (1 + $pii / 100); $miin = $sub - $sub / (1 + $pii / 100); }
                    else { $mgra = $sub; $miin = 0; }
                }
            }
        }

        $subNew = $mgra + $mexe;
        return $inclIva ? $tot : $subNew;
    }
}
