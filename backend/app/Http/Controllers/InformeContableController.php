<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * InformeContableController — Tablero Gerencial › Gestión (Logística) › Listados ›
 * Informe Contable de Ventas.
 *
 * Réplica (SOLO LECTURA) del Fox `ventas_informe_contable.scx` para el sistema de
 * LOGÍSTICA (SISTEMA_LOGISTICA = verdadero; no Silcar). Arma:
 *   1. IVA Ventas por rubro, discriminado por jurisdicción:
 *        TODAS · ROSARIO (suc 3) · ALVEAR (resto) · PEREZ (suc 6).
 *      Columnas: Gravado 21%, Gravado 10.5%, IVA 21%, IVA 10.5%, IIBB (Santa Fe),
 *      Percep. ARBA, Percep. CABA, Exento y TOTAL. Las NC restan.
 *   2. Cobranzas y Retenciones (RECIBOS + CTA_RETE por RET_COD).
 *   3. Detalle de Compras por rubro (COMPRAS, según COM_TCO).
 *   4. Pagos (O_PAGOS + retenciones de ganancias RET_GANA + impuestos SQL_IMPU_ITE).
 *
 * NO escribe nada. La opción "Bloquea Cobros" del Fox (que grababa IVA_BLO) se omite
 * a propósito: este tablero es de solo lectura. El filtro TIPO del Fox no afectaba los
 * totales (se calculaba pero no filtraba) → se respeta ese comportamiento.
 */
class InformeContableController extends Controller
{
    private function conn() { return DB::connection('gestion'); }

    /** GET /api/tablero/gestion/informe-contable?mes=&anio=&desde=&hasta= */
    public function index(Request $request): JsonResponse
    {
        try {
            $hoy  = Carbon::today();
            $mes  = (int) $request->query('mes', $hoy->month);
            $anio = (int) $request->query('anio', $hoy->year);
            if ($mes < 1 || $mes > 12) $mes = $hoy->month;

            $desde = $request->query('desde');   // 'Y-m-d' opcional
            $hasta = $request->query('hasta');
            $rango = $desde && $hasta;

            $ventas = $this->cargarVentas($mes, $anio, $desde, $hasta, $rango);

            // Rubros de venta (orden y descripción).
            $rubros = [];
            foreach ($this->conn()->table('RUB_OPER')->orderBy('RUB_COD')->get(['RUB_COD', 'RUB_DES']) as $r) {
                $rubros[(int) $r->RUB_COD] = trim((string) $r->RUB_DES);
            }

            // Matriz [jurisdiccion][rubro] = componentes acumulados.
            $J = ['T' => [], 'R' => [], 'P' => [], 'Z' => []];
            $tienePerez = false;

            foreach ($ventas as $v) {
                $c = $this->componentesVenta($v);
                $rub = (int) $v->VEN_RUB;
                if (!isset($rubros[$rub])) $rub = 2;   // Fox: sin rubro → rubro 2

                $suc = $this->sucursal($v);
                $this->acumular($J['T'], $rub, $c);                    // TODAS
                if ($suc === 3)               $this->acumular($J['R'], $rub, $c);   // ROSARIO
                elseif ($suc === 6)         { $this->acumular($J['Z'], $rub, $c); $tienePerez = true; } // PEREZ
                else                          $this->acumular($J['P'], $rub, $c);   // ALVEAR (resto)
            }

            $labels = ['T' => 'TODAS LAS JURISDICCIONES', 'R' => 'ROSARIO', 'P' => 'ALVEAR', 'Z' => 'PEREZ'];
            $jurisdicciones = [];
            foreach (['T', 'R', 'P', 'Z'] as $k) {
                if ($k === 'Z' && !$tienePerez) continue;
                $jurisdicciones[] = $this->armarJurisdiccion($k, $labels[$k], $J[$k], $rubros);
            }

            // Resumen global (a partir de TODAS).
            $resT = $jurisdicciones[0]['total'];
            $resumen = [
                'g21' => $resT['g21'], 'g10' => $resT['g10'],
                'i21' => $resT['i21'], 'i10' => $resT['i10'],
                'exe' => $resT['exe'], 'pib' => $resT['pib'],
                'arb' => $resT['arb'], 'cab' => $resT['cab'],
                'tot' => $resT['tot'],
                'dif' => round($resT['tot'] - ($resT['g21'] + $resT['g10'] + $resT['i21'] + $resT['i10'] + $resT['exe'] + $resT['pib'] + $resT['arb'] + $resT['cab']), 2),
            ];

            return response()->json([
                'mes' => $mes, 'anio' => $anio, 'desde' => $desde, 'hasta' => $hasta,
                'jurisdicciones' => $jurisdicciones,
                'resumen'   => $resumen,
                'cobranzas' => $this->cobranzas($mes, $anio),
                'compras'   => $this->compras($mes, $anio),
                'pagos'     => $this->pagos($mes, $anio, $desde, $hasta, $rango),
            ]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, request(), 'TABLERO-GESTION');
            return response()->json(['error' => 'No se pudo generar el informe contable.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Ventas
    // ─────────────────────────────────────────────────────────────────────────

    /** Lee las ventas del período (excluye canjes CICH/CINT y anuladas). */
    private function cargarVentas(int $mes, int $anio, $desde, $hasta, bool $rango)
    {
        $q = $this->conn()->table('VENTAS')
            ->whereRaw("LTRIM(RTRIM(VEN_TFA)) NOT IN ('CICH','CINT')")
            ->where(function ($w) { $w->whereNull('VEN_ANU')->orWhereRaw("LTRIM(RTRIM(VEN_ANU)) <> 'S'"); });

        if ($rango) $q->whereBetween('VEN_FEC', [$desde, $hasta]);
        else        $q->whereRaw('MONTH(VEN_FEC) = ?', [$mes])->whereRaw('YEAR(VEN_FEC) = ?', [$anio]);

        return $q->get();
    }

    /** Sucursal efectiva (VEN_SUCALT si es > 0; si no VEN_SUC). */
    private function sucursal($v): int
    {
        $alt = $v->VEN_SUCALT ?? null;
        if ($alt !== null && (int) $alt > 0) return (int) $alt;
        return (int) ($v->VEN_SUC ?? 0);
    }

    /**
     * Componentes fiscales de una venta (con signo; NC ya viene en negativo).
     * Réplica 1:1 del SCAN de VER_VENTAS del Fox (FC/ND/NC, letra A/B).
     * Devuelve: gra, g21, g10, iin, i21, i10, pib, arb, cab, exe, tot.
     */
    private function componentesVenta($v): array
    {
        $tfa = (string) $v->VEN_TFA;
        $t2  = substr($tfa, 0, 2);
        $t4  = substr($tfa, 3, 1);
        $sub = (float) $v->VEN_SUB; $exe = (float) $v->VEN_EXE; $tot = (float) $v->VEN_TOT;
        $iin = (float) $v->VEN_IIN; $pii = (float) $v->VEN_PII; $pib = (float) $v->VEN_PIB;
        $arb = (float) $v->VEN_ARB; $cab = (float) $v->VEN_PCABA; $ino = (float) ($v->VEN_INO ?? 0);
        $vcn = (string) ($v->VEN_VCN ?? ''); $chr = (string) ($v->VEN_CHR ?? '');

        $gra = 0.0; $exeR = $exe; $iinR = $iin; $mpib = $pib; $marb = $arb; $mcab = $cab; $mtot = $tot;
        $g21 = 0.0; $i21 = $iin; $g10 = 0.0; $i10 = 0.0;

        if ($t2 === 'ND') {
            if ($chr === 'S') {
                $gra = 0.0;
                if ($pii != 0) { $gra = $iin / ($pii / 100); $g21 = $gra; if ($pii == 10.5) { $g21 = 0; $g10 = $gra; } }
                $exeR = $tot - ($gra + $iin + $pib + $arb + $cab);
            } else {
                if (substr($tfa, 0, 4) === 'ND B') {
                    if ($pii > 0) {
                        $gra = 0.0;
                        if ($pii != 0) {
                            $gra = $tot / (1 + $pii / 100); $g21 = $gra; $i21 = $tot - $gra;
                            if ($pii == 10.5) { $g21 = 0; $g10 = $gra; $i21 = $tot - $gra; }
                        }
                        $iinR = $tot - $gra;
                    } else {
                        $gra = 0; $g21 = 0; $g10 = 0; $i21 = 0; $i10 = 0;
                        $exeR = $tot - ($pib + $arb + $cab);
                    }
                }
                if ($t2 === 'ND' && $t4 === 'A') {
                    if ($iin > 0) {
                        $gra = 0.0;
                        if ($pii != 0) { $gra = $iin / ($pii / 100); $g21 = $gra; if ($pii == 10.5) { $g21 = 0; $i21 = 0; $g10 = $gra; $i10 = $iin; } }
                        $exeR = $sub - $gra;
                        if ($exeR >= -5 && $exeR <= 5) { $gra += $exeR; if ($pii == 10.5) $g10 += $exeR; else $g21 += $exeR; $exeR = 0; }
                    } else { $gra = 0; $iinR = 0; $exeR = $tot - ($pib + $arb + $cab); }
                }
            }
        }

        if ($t2 === 'NC') {
            $mpib = $pib * -1; $marb = $arb * -1; $mcab = $cab * -1; $exeR = $exe * -1; $mtot = $tot * -1;
            if ($t4 === 'A') {
                $gra = 0.0;
                if ($pii != 0) { $gra = $iin / ($pii / 100); $g21 = $gra; if ($pii == 10.5) { $g21 = 0; $g10 = $gra; } }
                if ($iin != 0) { $gra = $sub; $g21 = $gra; $i21 = $iinR; if ($pii == 10.5) { $g21 = 0; $g10 = $gra; $i21 = 0; $i10 = $iinR; } }
                $exeR = $tot - ($gra + $iin + $ino + $pib + $arb + $cab);
                if ($exeR >= -0.03 && $exeR <= 0.03) { $gra += $exeR; $exeR = 0; }
                $gra *= -1; $g21 *= -1; $g10 *= -1; $iinR *= -1; $i21 *= -1; $i10 *= -1; $exeR *= -1;
            }
            if ($t4 === 'B') {
                $exeR = $sub + $exe; $g21 = 0; $g10 = 0; $i21 = 0; $i10 = 0;
                if ($iinR > 0) {
                    $gra = $sub; $g21 = $gra; $i21 = $iinR; $g10 = 0; $i10 = 0; $exeR = 0;
                    if ($pii == 10.5) { $g21 = 0; $g10 = $gra; $i21 = 0; $i10 = $iinR; }
                } else {
                    if (substr($vcn, 0, 2) === 'SI') {
                        $exeR = $exe; $gra = $sub / (1 + $pii / 100); $iinR = $sub - $sub / (1 + $pii / 100);
                        $g21 = $gra; $i21 = $iinR;
                        if ($pii == 10.5) { $g21 = 0; $g10 = $gra; $i21 = 0; $i10 = $iinR; }
                    }
                }
                $gra *= -1; $g21 *= -1; $g10 *= -1; $iinR *= -1; $i21 *= -1; $i10 *= -1; $exeR *= -1;
            }
        }

        if ($t2 === 'FC') {
            if ($t4 === 'A') {
                $gra = $sub; $g21 = $sub; $i21 = $iin;
                if (round($sub * .105, 2) == round($iin, 2)) { $g10 = $sub; $i10 = $iin; $g21 = 0; $i21 = 0; }
                if (round($sub * .21, 2) - 1 < $iin && round($sub * .21, 2) + 1 > $iin)   { $g21 = $sub; $i21 = $iin; $g10 = 0; $i10 = 0; }
                if (round($sub * .105, 2) - 1 < $iin && round($sub * .105, 2) + 1 > $iin) { $g10 = $sub; $i10 = $iin; $g21 = 0; $i21 = 0; }
                $exeR = $exe; $iinR = $iin; $mpib = $pib; $marb = $arb; $mcab = $cab; $mtot = $tot;
            }
            if ($t4 === 'B') {
                $exeR = $sub + $exe; $g21 = 0; $g10 = 0; $i21 = 0; $i10 = 0;
                if ($iinR > 0) {
                    $gra = $sub; $g21 = $gra; $i21 = $iinR; $g10 = 0; $i10 = 0; $exeR = 0;
                    if ($pii == 10.5) { $g21 = 0; $g10 = $gra; $i21 = 0; $i10 = $iinR; }
                } else {
                    if (substr($vcn, 0, 2) !== 'NO') {
                        $exeR = $exe; $gra = $sub / (1 + $pii / 100); $iinR = $sub - $sub / (1 + $pii / 100);
                        $g21 = $gra; $i21 = $iinR;
                        if ($pii == 10.5) { $g21 = 0; $g10 = $gra; $i21 = 0; $i10 = $iinR; }
                    }
                }
            }
        }

        return ['gra' => $gra, 'g21' => $g21, 'g10' => $g10, 'iin' => $iinR, 'i21' => $i21,
                'i10' => $i10, 'pib' => $mpib, 'arb' => $marb, 'cab' => $mcab, 'exe' => $exeR, 'tot' => $mtot];
    }

    private function acumular(array &$bucket, int $rub, array $c): void
    {
        if (!isset($bucket[$rub])) $bucket[$rub] = ['gra' => 0, 'g21' => 0, 'g10' => 0, 'iin' => 0, 'i21' => 0, 'i10' => 0, 'pib' => 0, 'arb' => 0, 'cab' => 0, 'exe' => 0, 'tot' => 0];
        foreach ($c as $k => $val) $bucket[$rub][$k] += $val;
    }

    private function armarJurisdiccion(string $key, string $label, array $bucket, array $rubros): array
    {
        $filas = []; $tot = ['gra' => 0, 'g21' => 0, 'g10' => 0, 'iin' => 0, 'i21' => 0, 'i10' => 0, 'pib' => 0, 'arb' => 0, 'cab' => 0, 'exe' => 0, 'tot' => 0];
        foreach ($bucket as $rub => $c) {
            $vacio = round($c['g21'], 2) == 0 && round($c['g10'], 2) == 0 && round($c['i21'], 2) == 0 && round($c['i10'], 2) == 0
                && round($c['pib'], 2) == 0 && round($c['arb'], 2) == 0 && round($c['cab'], 2) == 0 && round($c['exe'], 2) == 0 && round($c['tot'], 2) == 0;
            if ($vacio) continue;
            $filas[] = [
                'cod' => $rub, 'des' => $rubros[$rub] ?? '',
                'g21' => round($c['g21'], 2), 'g10' => round($c['g10'], 2),
                'i21' => round($c['i21'], 2), 'i10' => round($c['i10'], 2),
                'pib' => round($c['pib'], 2), 'arb' => round($c['arb'], 2), 'cab' => round($c['cab'], 2),
                'exe' => round($c['exe'], 2), 'tot' => round($c['tot'], 2),
                'gra' => round($c['gra'], 2), 'iin' => round($c['iin'], 2),
            ];
            foreach ($tot as $k => $_) $tot[$k] += $c[$k];
        }
        usort($filas, fn ($a, $b) => $a['cod'] <=> $b['cod']);
        foreach ($tot as $k => $val) $tot[$k] = round($val, 2);
        return ['key' => $key, 'label' => $label, 'filas' => $filas, 'total' => $tot];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Cobranzas y Retenciones
    // ─────────────────────────────────────────────────────────────────────────

    private function cobranzas(int $mes, int $anio): array
    {
        $neto = (float) $this->conn()->table('RECIBOS')
            ->whereRaw('MONTH(REC_FEC) = ?', [$mes])->whereRaw('YEAR(REC_FEC) = ?', [$anio])
            ->sum('REC_SUB');

        $catalogo = [];
        foreach ($this->conn()->table('RETENCIO')->get(['RET_COD', 'RET_DES']) as $r) $catalogo[(int) $r->RET_COD] = trim((string) $r->RET_DES);

        $porCod = [];
        foreach ($this->conn()->table('CTA_RETE')
            ->whereRaw('MONTH(RET_FEC) = ?', [$mes])->whereRaw('YEAR(RET_FEC) = ?', [$anio])
            ->get(['RET_COD', 'RET_IMP']) as $r) {
            $cod = (int) $r->RET_COD;
            $porCod[$cod] = ($porCod[$cod] ?? 0) + (float) $r->RET_IMP;
        }

        $retenciones = []; $totalRet = 0.0;
        foreach ($porCod as $cod => $imp) {
            if (round($imp, 2) == 0) continue;
            $retenciones[] = ['des' => $catalogo[$cod] ?? ('RET ' . $cod), 'imp' => round($imp, 2)];
            $totalRet += $imp;
        }
        usort($retenciones, fn ($a, $b) => strcmp($a['des'], $b['des']));

        return [
            'neto' => round($neto, 2),
            'retenciones' => $retenciones,
            'totalRet' => round($totalRet, 2),
            'totalCobranzas' => round($neto - $totalRet, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Detalle de Compras
    // ─────────────────────────────────────────────────────────────────────────

    private function compras(int $mes, int $anio): array
    {
        $rubros = [];
        foreach ($this->conn()->table('RUB_CPRA')->orderBy('RUB_COD')->get(['RUB_COD', 'RUB_DES']) as $r) $rubros[(int) $r->RUB_COD] = trim((string) $r->RUB_DES);
        $rubros[9999] = 'SIN CODIFICAR';

        $ivaProv = [];   // PVR_COD => PVR_IVA (para COM_TCO=39)
        foreach ($this->conn()->table('PROVEEDO')->where('PVR_COD', '<', 70000)->get(['PVR_COD', 'PVR_IVA']) as $p) $ivaProv[(int) $p->PVR_COD] = trim((string) $p->PVR_IVA);

        $mat = [];
        $add = function (int $rub, array $d) use (&$mat) {
            if (!isset($mat[$rub])) $mat[$rub] = ['gra' => 0, 'iin' => 0, 'i10' => 0, 'i27' => 0, 'ino' => 0, 'ret' => 0, 'tot' => 0];
            foreach ($d as $k => $v) $mat[$rub][$k] += $v;
        };

        $filasCompra = $this->conn()->table('COMPRAS')
            ->whereRaw('COM_IME = ?', [$mes])->whereRaw('COM_IAN = ?', [$anio])
            ->get();

        foreach ($filasCompra as $c) {
            $tco = (int) $c->COM_TCO;
            $tfa = (string) $c->COM_TFA;
            if ($tco === 99 || trim($tfa) === 'ND I' || trim($tfa) === 'NC I') continue;

            $rub = (int) $c->COM_RUB;
            if (!isset($rubros[$rub])) $rub = 9999;

            $bru = (float) $c->COM_BRU; $ino = (float) $c->COM_INO; $iin = (float) $c->COM_IIN;
            $i10 = (float) $c->COM_I10; $i27 = (float) $c->COM_I27 + (float) ($c->COM_I25 ?? 0) + (float) ($c->COM_I05 ?? 0);
            $ret = (float) $c->COM_RET; $net = (float) $c->COM_NET;
            $ret9 = 0.0; foreach (['COM_RE1','COM_RE2','COM_RE3','COM_RE4','COM_RE5','COM_RE6','COM_RE7','COM_RE8','COM_RE9'] as $rc) $ret9 += (float) ($c->$rc ?? 0);

            if (in_array($tco, [1, 2, 4, 14, 16], true)) {           // FC/ND/REC "A"
                $netA = $bru + $ino + $iin + $i10 + $i27 + $i27 + $ret;   // (réplica Fox, con COM_I27 doble)
                $add($rub, ['gra' => $bru, 'ino' => $ino, 'iin' => $iin, 'i10' => $i10, 'i27' => $i27, 'ret' => $ret, 'tot' => $tco !== 16 ? $net : $netA]);
            } elseif (in_array($tco, [3, 8, 13, 12], true)) {         // N. Crédito (resta)
                $add($rub, ['gra' => -$bru, 'ino' => -$ino, 'iin' => -$iin, 'i10' => -$i10, 'i27' => -$i27, 'ret' => -$ret9, 'tot' => -$net]);
            } elseif ($tco === 39) {
                $iva = $ivaProv[(int) $c->COM_PVR] ?? ' ';
                if ($iva !== 'E') $add($rub, ['gra' => $bru, 'ino' => $ino, 'iin' => $iin, 'i10' => $i10, 'i27' => $i27, 'ret' => $ret, 'tot' => $net]);
                else              $add($rub, ['ino' => $bru + $ino + $iin + $i27 + $i10, 'tot' => $net]);
            } elseif ($tco === 10) {                                  // Factura M
                $add($rub, ['gra' => $bru, 'iin' => $iin, 'i10' => $i10, 'i27' => $i27, 'ino' => $ino, 'ret' => $ret, 'tot' => $net]);
            } else {
                $add($rub, ['iin' => $iin, 'i10' => $i10, 'i27' => $i27, 'ino' => $ino + $bru, 'ret' => $ret9, 'tot' => $net]);
            }
        }

        $filas = []; $tot = ['gra' => 0, 'i10' => 0, 'iin' => 0, 'i27' => 0, 'ino' => 0, 'ret' => 0, 'tot' => 0];
        foreach ($mat as $rub => $d) {
            if (round($d['tot'], 2) == 0) continue;
            $filas[] = [
                'cod' => $rub, 'des' => $rubros[$rub] ?? '',
                'gra' => round($d['gra'], 2), 'i10' => round($d['i10'], 2), 'iin' => round($d['iin'], 2),
                'i27' => round($d['i27'], 2), 'ino' => round($d['ino'], 2), 'ret' => round($d['ret'], 2), 'tot' => round($d['tot'], 2),
            ];
            foreach ($tot as $k => $_) $tot[$k] += $d[$k];
        }
        usort($filas, fn ($a, $b) => strcmp($a['des'], $b['des']));
        foreach ($tot as $k => $v) $tot[$k] = round($v, 2);

        return ['filas' => $filas, 'total' => $tot];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Pagos
    // ─────────────────────────────────────────────────────────────────────────

    private function pagos(int $mes, int $anio, $desde, $hasta, bool $rango): array
    {
        // O_PAGOS del período.
        $qp = $this->conn()->table('O_PAGOS');
        if ($rango) $qp->whereBetween('OPA_FEC', [$desde, $hasta]);
        else        $qp->whereRaw('MONTH(OPA_FEC) = ?', [$mes])->whereRaw('YEAR(OPA_FEC) = ?', [$anio]);
        $op = $qp->selectRaw('COALESCE(SUM(OPA_IMP),0) imp, COALESCE(SUM(OPA_IBR),0) ibr, COALESCE(SUM(OPA_SUS),0) sus, COALESCE(SUM(OPA_LIM),0) lim, COALESCE(SUM(OPA_ARB),0) arb, COALESCE(SUM(OPA_PCABA),0) cab')->first();

        // Retenciones de ganancias (RET_GANA).
        $qg = $this->conn()->table('RET_GANA')->where('CTA_PVR', '>', 0);
        if ($rango) $qg->whereBetween('CTA_FEC', [$desde, $hasta]);
        else        $qg->whereRaw('MONTH(CTA_FEC) = ?', [$mes])->whereRaw('YEAR(CTA_FEC) = ?', [$anio]);
        $gan = (float) $qg->sum('CTA_RET');

        $imp = (float) $op->imp; $ibr = (float) $op->ibr; $sus = (float) $op->sus;
        $lim = (float) $op->lim; $arb = (float) $op->arb; $cab = (float) $op->cab;
        $resta = $gan + $ibr + $sus + $lim + $arb + $cab;

        // Pagos Cuentas Vs / Impuestos (SQL_IMPU_ITE agrupado por proveedor).
        $nombres = [];
        foreach ($this->conn()->table('PROVEEDO')->where('PVR_IMP', 'S')->get(['PVR_COD', 'PVR_NOM']) as $p) $nombres[(int) $p->PVR_COD] = trim((string) $p->PVR_NOM);

        $qi = $this->conn()->table('IMPU_ITE');
        if ($rango) $qi->whereBetween('IMP_FEC', [$desde, $hasta]);
        else        $qi->whereRaw('MONTH(IMP_FEC) = ?', [$mes])->whereRaw('YEAR(IMP_FEC) = ?', [$anio]);
        $porProv = [];
        foreach ($qi->get(['IMP_PVR', 'IMP_IMP']) as $r) {
            $cod = (int) $r->IMP_PVR;
            if (!isset($nombres[$cod])) continue;   // Fox: solo proveedores PVR_IMP='S'
            $porProv[$cod] = ($porProv[$cod] ?? 0) + (float) $r->IMP_IMP;
        }
        $cuentas = []; $totalCuentas = 0.0;
        foreach ($porProv as $cod => $val) {
            if (round($val, 2) == 0) continue;
            $cuentas[] = ['cod' => $cod, 'nom' => $nombres[$cod] ?? '', 'imp' => round($val, 2)];
            $totalCuentas += $val;
        }
        usort($cuentas, fn ($a, $b) => $b['imp'] <=> $a['imp']);

        return [
            'proveedores' => round($imp, 2), 'ganancias' => round($gan, 2),
            'iibb' => round($ibr, 2), 'suss' => round($sus, 2), 'limpieza' => round($lim, 2),
            'arba' => round($arb, 2), 'caba' => round($cab, 2),
            'netos' => round($imp - $resta, 2),
            'cuentas' => $cuentas, 'totalCuentas' => round($totalCuentas, 2),
        ];
    }
}
