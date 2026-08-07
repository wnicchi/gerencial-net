<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ProyeccionController — Estadísticas › Proyecciones Financieras Mensual.
 *
 * Migra proyecciones_interface.scx (botón GENERAR). Arma una grilla de 4 semanas
 * (desde el lunes de la semana actual) con el flujo proyectado por concepto:
 *   ingresos (Cheques en Cartera R, Cobranzas O)  → SUMAN
 *   egresos  (Impuestos I, Leasing L, Créditos C, Fondo Fijo F, Seguros S,
 *             Haberes H, Cheques Diferidos D, Transferencias T, Cheques a
 *             Proveedores P, Compras Equipos E, Manuales M, O.Compras Varias V) → RESTAN
 * Cada concepto sólo se calcula si está ACTIVO en PROYECCION_PARAMETROS (Manuales,
 * Cheques en Cartera y Cobranzas se calculan siempre). El bucket semanal usa la
 * función WEEK() de FoxPro (semana que contiene el 1/1, arranca domingo).
 *
 * SOLO LECTURA (no escribe en PROYECCION_CUENTAS: agrupa por tipo+detalle).
 * Multi-base: gestión en sqlLOGIST (conexión sqlsrv_rrhh); personal en sqlRRHHlog
 * (nombre de 3 partes). No usa Autoelevadores (sqlSILCAR).
 */
class ProyeccionController extends Controller
{
    /** Base de RRHH de Logística (para Fondo Fijo y Haberes), vía nombre de 3 partes. */
    private const RRHH_DB = 'sqlRRHHlog';

    private function conn()
    {
        return DB::connection('gestion');
    }

    // Ventana (se setean en index()).
    private Carbon $f1, $f2, $f3, $f4;
    private array $sem = [];          // [1..4] => nº de semana Fox
    private string $d1, $d4mas6;      // límites string 'Y-m-d'
    private array $muestra = [];      // [tipo][detalle] => fila

    /** WEEK() de FoxPro por defecto (nFirstWeek=1, primer día = domingo). */
    private function fw(Carbon $d): int
    {
        $jan1 = Carbon::create($d->year, 1, 1);
        $foxDow = $jan1->dayOfWeek === 0 ? 1 : $jan1->dayOfWeek + 1;   // dom=1..sáb=7
        $inicio = $jan1->copy()->subDays($foxDow - 1);                 // domingo de la semana 1
        $dias = (int) floor(($d->copy()->startOfDay()->timestamp - $inicio->startOfDay()->timestamp) / 86400);
        return intdiv($dias, 7) + 1;
    }

    private function pf(?string $v): ?Carbon
    {
        if (!$v) return null;
        try { $c = Carbon::parse($v); return $c->year <= 1900 ? null : $c; } catch (\Throwable $e) { return null; }
    }

    /** Acumula un importe en la columna de la semana correspondiente (o lo ignora). */
    private function acum(string $tipo, string $detalle, int $bancoCod, string $bancoNom, ?Carbon $fecha, float $importe, int $signo): void
    {
        if (!$fecha) return;
        $w = $this->fw($fecha);
        $col = array_search($w, $this->sem, true);   // 1..4 o false
        if ($col === false) return;
        $k = trim($detalle);
        if (!isset($this->muestra[$tipo][$k])) {
            $this->muestra[$tipo][$k] = [
                'detalle' => $k, 'tipo' => $tipo, 'banco_cod' => $bancoCod, 'banco_nom' => $bancoNom,
                's1' => 0.0, 's2' => 0.0, 's3' => 0.0, 's4' => 0.0,
            ];
        }
        $this->muestra[$tipo][$k]['s' . $col] += $signo * $importe;
    }

    /** GET /api/estadisticas/proyecciones — arma la proyección de 4 semanas. */
    public function index(): JsonResponse
    {
        $c = $this->conn();

        // Ventana: lunes de la semana actual (domingo si hoy es domingo).
        $hoy = Carbon::today();
        $this->f1 = $hoy->dayOfWeek === 0 ? $hoy->copy() : $hoy->copy()->startOfWeek(Carbon::MONDAY);
        $this->f2 = $this->f1->copy()->addDays(7);
        $this->f3 = $this->f1->copy()->addDays(14);
        $this->f4 = $this->f1->copy()->addDays(21);
        $this->sem = [1 => $this->fw($this->f1), 2 => $this->fw($this->f2), 3 => $this->fw($this->f3), 4 => $this->fw($this->f4)];
        $this->d1 = $this->f1->format('Y-m-d');
        $this->d4mas6 = $this->f4->copy()->addDays(6)->format('Y-m-d');
        $this->muestra = [];

        // Parámetros activos.
        $activos = [];
        foreach ($c->table('PROYECCION_PARAMETROS')->get() as $p) {
            $activos[trim((string) $p->TIPO)] = (int) $p->ACTIVA === 1 || $p->ACTIVA === true || trim((string) $p->ACTIVA) === '1';
        }
        $on = fn (string $t) => $activos[$t] ?? false;

        // Conceptos condicionales.
        if ($on('I')) $this->cargarImpuestos();
        if ($on('L')) $this->cargarCuotas('L', 'LEA', 'LEASING', 'LEA');
        if ($on('C')) $this->cargarCuotas('C', 'CCRE', 'CREDITOS', 'CRE');
        if ($on('F')) $this->cargarFondoFijo();
        if ($on('S')) $this->cargarSeguros();
        if ($on('H')) $this->cargarHaberes();
        if ($on('D')) $this->cargarChequesDiferidos();
        if ($on('T')) $this->cargarTransferencias();
        if ($on('P')) $this->cargarChequesCorrientes();
        if ($on('E')) $this->cargarPedidoAutoelevadores();
        // Incondicionales.
        $this->cargarManuales();
        if ($on('V')) $this->cargarOrdenesComprasVarias();
        $this->cargarChequesEnCartera();
        $this->cargarCobranzas();

        // Aplanar + ordenar por detalle.
        $filas = [];
        foreach ($this->muestra as $porTipo) foreach ($porTipo as $f) $filas[] = $f;
        usort($filas, fn ($a, $b) => strcmp($a['detalle'], $b['detalle']));
        foreach ($filas as &$f) {
            $f['s1'] = round($f['s1'], 2); $f['s2'] = round($f['s2'], 2);
            $f['s3'] = round($f['s3'], 2); $f['s4'] = round($f['s4'], 2);
        }
        unset($f);

        $t = ['s1' => 0.0, 's2' => 0.0, 's3' => 0.0, 's4' => 0.0];
        foreach ($filas as $f) foreach (['s1', 's2', 's3', 's4'] as $k) $t[$k] += $f[$k];

        $rot = fn (Carbon $d) => 'Sem.' . $d->format('d/m/Y');
        return response()->json([
            'columnas' => [$rot($this->f1), $rot($this->f2), $rot($this->f3), $rot($this->f4)],
            'rangos'   => [
                $this->rango($this->f1), $this->rango($this->f2), $this->rango($this->f3), $this->rango($this->f4),
            ],
            'filas'    => array_values($filas),
            'totales'  => [round($t['s1'], 2), round($t['s2'], 2), round($t['s3'], 2), round($t['s4'], 2)],
            'total_gral' => round($t['s1'] + $t['s2'] + $t['s3'] + $t['s4'], 2),
        ]);
    }

    private function rango(Carbon $d): string
    {
        return 'del ' . $d->format('j/n') . ' al ' . $d->copy()->addDays(4)->format('j/n');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOADERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * I — Impuestos y Servicios (OBLIGACIONES locales de sqlLOGIST; 2 vencimientos).
     * Resta. NO se unen las de Autoelevadores (sqlSILCAR): Logística no las tiene
     * (no compra autoelevadores, los alquila).
     */
    private function cargarImpuestos(): void
    {
        $local = $this->conn()->table('OBLIGACIONES')
            ->where(function ($q) {
                $q->whereBetween('OBL_FEC', [$this->d1, $this->d4mas6])
                  ->orWhereBetween('OBL_FE2', [$this->d1, $this->d4mas6]);
            })->get();
        foreach ($local as $o) $this->procesarObligacion($o);
    }

    /** Carga una obligación (2 vencimientos) tomando el importe real o el estimado/histórico. */
    private function procesarObligacion($o): void
    {
        $det = trim((string) $o->OBL_DES) . ' ( NUEVO BANCO DE SANTA FE S.A. )';
        // El histórico (cuando el importe es 0) el Fox SIEMPRE lo busca en la base
        // local (sqlLOGIST), sin importar de dónde venga la obligación.
        // 1er vencimiento
        $f1 = $this->pf($o->OBL_FEC);
        if ($f1) {
            $imp = (float) $o->OBL_IMP > 0 ? (float) $o->OBL_IMP : (float) $o->OBL_EST;
            if ((float) $o->OBL_IMP == 0.0) { $h = $this->impuestoHistorico('OBLIGACIONES', $o->OBL_DES, $f1, 'OBL_IMP'); if ($h > 0) $imp = $h; }
            if ($imp > 0) $this->acum('I', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $f1, $imp, -1);
        }
        // 2do vencimiento
        $f2 = $this->pf($o->OBL_FE2);
        if ($f2) {
            $imp = (float) $o->OBL_IM2;
            if ($imp == 0.0) { $h = $this->impuestoHistorico('OBLIGACIONES', $o->OBL_DES, $f1 ?? $f2, 'OBL_IM2'); if ($h > 0) $imp = $h; }
            if ($imp > 0) $this->acum('I', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $f2, $imp, -1);
        }
    }

    /** Último pago histórico de una obligación (misma semana Fox) en la base indicada. */
    private function impuestoHistorico(string $tabla, string $desc, ?Carbon $refFec, string $col): float
    {
        if (!$refFec) return 0.0;
        $rows = $this->conn()->select(
            "SELECT TOP 60 $col v, OBL_FEC f FROM $tabla WHERE OBL_FEC < ? AND LTRIM(RTRIM(OBL_DES)) = ? AND $col > 0 ORDER BY OBL_FEC DESC",
            [$this->d1, trim($desc)]
        );
        $wref = $this->fw($refFec);
        foreach ($rows as $r) {
            $f = $this->pf($r->f);
            if ($f && $this->fw($f) === $wref) return (float) $r->v;
        }
        return 0.0;
    }

    /** L/C — Leasing y Créditos (cuotas ⨝ contrato). Resta. */
    private function cargarCuotas(string $tipo, string $pre, string $tblContrato, string $preContrato): void
    {
        // $pre: prefijo de columnas de la cuota (LEA/CCRE). $preContrato: prefijo del contrato (LEA/CRE).
        $c = $this->conn();
        $tblCuota = $pre === 'LEA' ? 'LEA_CUOT' : 'CRE_CUOT';
        $fkCuota  = $pre === 'LEA' ? 'LEA_NRO' : 'CCRE_NRO';
        $venCol   = $pre === 'LEA' ? 'LEA_VEN' : 'CCRE_VEN';
        $cttCol   = $pre === 'LEA' ? 'LEA_CTT' : 'CCRE_CTT';
        $totCol   = $pre === 'LEA' ? 'LEA_TOTAL' : 'CCRE_TOTAL';
        $nroCtr   = $preContrato . '_NRO';
        $botCol   = $preContrato . '_BOT';
        $banCol   = $preContrato . '_BAN';
        $badCol   = $preContrato . '_BAD';

        $rows = $c->table($tblCuota)
            ->join($tblContrato, "$tblCuota.$fkCuota", '=', "$tblContrato.$nroCtr")
            ->whereBetween("$tblCuota.$venCol", [$this->d1, $this->d4mas6])
            ->get(["$tblCuota.*", "$tblContrato.$botCol", "$tblContrato.$banCol", "$tblContrato.$badCol"]);

        $nroC = $pre === 'LEA' ? 'LEA_NRO' : 'CCRE_NRO';
        foreach ($rows as $r) {
            $etq = $pre === 'LEA' ? 'LEASING' : 'CREDITO';
            $det = "$etq " . (int) $r->{$nroC} . ' - CONTRATO ' . (int) $r->{$cttCol};
            if (trim((string) $r->{$botCol}) === 'B') $det .= ' (' . trim((string) $r->{$badCol}) . ')';
            $this->acum($tipo, $det, (int) $r->{$banCol}, trim((string) $r->{$badCol}), $this->pf($r->{$venCol}), (float) $r->{$totCol}, -1);
        }
    }

    /** D — Cheques Diferidos (MOVI_BCO, MOV_CHE>0). Resta. */
    private function cargarChequesDiferidos(): void
    {
        $c = $this->conn();
        $desde = $this->f1->copy()->subDays(2)->format('Y-m-d');
        $hasta = $this->f4->copy()->addDays(4)->format('Y-m-d');
        $rows = $c->table('MOVI_BCO')->whereBetween('MOV_FEC', [$desde, $hasta])
            ->where('MOV_HAB', '>', 0)->where('MOV_CHE', '>', 0)
            ->get(['MOV_FEC', 'MOV_CTA', 'MOV_HAB']);
        $bancos = $this->bancos();
        foreach ($rows as $r) {
            $ban = trim((string) ($bancos[(int) $r->MOV_CTA]['des'] ?? ''));
            $det = 'CHEQUES DIFERIDOS ( ' . $ban . ' )';
            $this->acum('D', $det, (int) $r->MOV_CTA, $ban, $this->pf($r->MOV_FEC), (float) $r->MOV_HAB, -1);
        }
    }

    /** R — Cheques en Cartera (CART_CHE, RECI). Suma. Fila única. */
    private function cargarChequesEnCartera(): void
    {
        $c = $this->conn();
        $rows = $c->table('CART_CHE')->whereBetween('CAR_FEC', [$this->d1, $this->d4mas6])
            ->where('CAR_SAL', '<>', 'S')->whereRaw("LEFT(CAR_DET,4)='RECI'")
            ->get(['CAR_FEC', 'CAR_IMP']);
        $det = 'CHEQUES EN CARTERA ( NUEVO BANCO DE SANTA FE S.A. )';
        foreach ($rows as $r) {
            $this->acum('R', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->CAR_FEC), (float) $r->CAR_IMP, +1);
        }
    }

    /** E — Pedido de Autoelevadores. Resta. Fila única. */
    private function cargarPedidoAutoelevadores(): void
    {
        $c = $this->conn();
        $hasta = $this->f4->copy()->addDays(6)->format('Y-m-d');
        $rows = $c->table('AUTOELEV_PEDIDO')->whereBetween('APED_FPA', [$this->d1, $hasta])
            ->get(['APED_FPA', 'APED_MONTO']);
        $det = 'PEDIDO DE AUTOELEVADORES ( NUEVO BANCO DE SANTA FE S.A. )';
        foreach ($rows as $r) {
            $this->acum('E', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->APED_FPA), (float) $r->APED_MONTO, -1);
        }
    }

    /** M — Manuales / Interbanking (PROYECCION_MANUAL). Resta. */
    private function cargarManuales(): void
    {
        $c = $this->conn();
        $hasta = $this->f4->copy()->addDays(4)->format('Y-m-d');
        $rows = $c->table('PROYECCION_MANUAL')->whereBetween('CMPRO_FEC', [$this->d1, $hasta])->get();
        foreach ($rows as $r) {
            $det = mb_strtoupper(trim((string) $r->CMPRO_DES)) . ' (NUEVO BCO.SANTA FE S.A.)';
            $this->acum('M', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->CMPRO_FEC), (float) $r->CMPRO_IMP, -1);
        }
    }

    /** V — Órdenes de Compras Varias (OCOMPRAS_VARIOS). Resta. */
    private function cargarOrdenesComprasVarias(): void
    {
        $c = $this->conn();
        $hasta = $this->f4->copy()->addDays(4)->format('Y-m-d');
        $rows = $c->table('OCOMPRAS_VARIOS')->whereBetween('COM_FPA', [$this->d1, $hasta])->get();
        foreach ($rows as $r) {
            $det = 'O.COMPRAS VARIAS: Rubro:' . mb_strtoupper(trim((string) $r->COM_RUD)) . ' (NUEVO BCO.SANTA FE S.A.)';
            $this->acum('V', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->COM_FPA), (float) $r->COM_TOT, -1);
        }
    }

    /** S — Seguros (MOVI_BCO, proveedor 482). Resta. */
    private function cargarSeguros(): void
    {
        $c = $this->conn();
        $hasta = $this->f4->copy()->addDays(6)->format('Y-m-d');
        $rows = $c->table('MOVI_BCO')->whereBetween('MOV_FEC', [$this->d1, $hasta])
            ->where('MOV_HAB', '>', 0)->where('MOV_PVR', 482)->where('MOV_CHE', 0)
            ->get(['MOV_FEC', 'MOV_CTA', 'MOV_PVR', 'MOV_HAB']);
        $prov = $this->proveedores();
        foreach ($rows as $r) {
            $nom = trim((string) ($prov[(int) $r->MOV_PVR] ?? ''));
            $det = mb_substr('CTA ' . (int) $r->MOV_CTA . ' - PROV. (' . (int) $r->MOV_PVR . ') ' . $nom . ' ( BANCO DE SANTA FE S.A. )', 0, 100);
            $this->acum('S', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->MOV_FEC), (float) $r->MOV_HAB, -1);
        }
    }

    /** P — Cheques a Proveedores (saldo pendiente CC_PROVEEDOR). Resta. */
    private function cargarChequesCorrientes(): void
    {
        $c = $this->conn();
        $fdes = $this->f1->copy()->subDays(31)->format('Y-m-d');
        $fhas = $this->f1->copy()->subDays(1)->format('Y-m-d');
        // proveedores con cheques 'P' en el último mes
        $movs = $c->table('MOVI_BCO')->join('O_PAGOS', 'MOVI_BCO.MOV_NRO', '=', 'O_PAGOS.OPA_PAG')
            ->where('MOVI_BCO.MOV_TIP', 'P')->whereBetween('O_PAGOS.OPA_FEC', [$fdes, $fhas])
            ->where('MOVI_BCO.MOV_PVR', '>', 0)
            ->distinct()->get(['MOVI_BCO.MOV_PVR', 'MOVI_BCO.MOV_CTA']);
        $prov = $this->proveedores();
        $bancos = $this->bancos();
        $fin = $this->f4->copy()->addDays(6);
        foreach ($movs as $m) {
            $cc = $c->table('CC_PROVEEDOR')->where('CTA_COD', (int) $m->MOV_PVR)->where('CTA_TIP', 'P')->get();
            $sal = 0.0; $ven = null;
            foreach ($cc as $r) {
                $vencim = $this->pf($r->CTA_VEN);
                if (!$vencim) continue;
                if (round((float) $r->CTA_HAB, 2) == round((float) $r->CTA_TOT, 2)) continue;
                $pen = round((float) $r->CTA_HAB, 2) - round((float) $r->CTA_TOT, 2);
                if (substr((string) $r->CTA_DES, 0, 2) === 'NC') $pen *= -1;
                $sal += $pen; $ven = $vencim;
            }
            if ($sal <= 0 || !$ven) continue;
            if ($ven->lt($this->f1) || $ven->gt($fin)) continue;
            $nom = trim((string) ($prov[(int) $m->MOV_PVR] ?? ''));
            $ban = trim((string) ($bancos[(int) $m->MOV_CTA]['nba'] ?? ''));
            $det = 'CH.CORRIENTE A ' . $nom . ($ban !== '' ? ' ( ' . $ban . ' )' : '');
            $this->acum('P', $det, (int) ($bancos[(int) $m->MOV_CTA]['cod'] ?? 0), $ban, $ven, $sal, -1);
        }
    }

    /** F — Fondo Fijo (VALES_EMPLEADOS + PERSONAL de sqlRRHHlog). Resta. */
    private function cargarFondoFijo(): void
    {
        $c = $this->conn();
        $fdes = $this->f1->copy()->subDays(31)->format('Y-m-d');
        $fhas = $this->f1->copy()->subDays(1)->format('Y-m-d');
        $vales = $c->table('VALES_EMPLEADOS')->whereBetween('VLE_FEC', [$fdes, $fhas])->get();
        $personal = collect($c->select("SELECT PER_COD, PER_NOM FROM " . self::RRHH_DB . ".dbo.PERSONAL WHERE PER_AOP='A'"))
            ->keyBy(fn ($p) => (int) $p->PER_COD);
        $porEmp = [];   // [per] => [importe, fecha]
        foreach ($vales as $v) {
            $per = (int) $v->VLE_PER;
            if (!$personal->has($per)) continue;
            if (!isset($porEmp[$per])) $porEmp[$per] = ['imp' => 0.0, 'fec' => $this->pf($v->VLE_FEC)?->copy()->addDays(31)];
            $porEmp[$per]['imp'] += (float) $v->VLE_IMP;
        }
        foreach ($porEmp as $per => $d) {
            if ($d['imp'] == 0.0 || !$d['fec']) continue;
            $nom = trim((string) ($personal[$per]->PER_NOM ?? ''));
            $det = '(' . str_pad((string) $per, 6, ' ', STR_PAD_LEFT) . ' ) ' . $nom . ' ( SANTANDER RIO )';
            $this->acum('F', $det, 1, 'SANTANDER RIO', $d['fec'], $d['imp'], -1);
        }
    }

    /** H — Haberes (últimas liquidaciones de sqlRRHHlog; estima fecha de pago). Resta. */
    private function cargarHaberes(): void
    {
        $c = $this->conn();
        $db = self::RRHH_DB;
        $desde = $this->f1->copy()->subDays(360)->format('Y-m-d');
        // empleados activos de logística (PER_EMP=2)
        $emp = collect($c->select("SELECT PER_COD, PER_NOM, PER_EMP, Per_BAN, Per_BAD FROM $db.dbo.PERSONAL WHERE PER_AOP='A' AND PER_EMP=2"))
            ->keyBy(fn ($p) => (int) $p->PER_COD);
        if ($emp->isEmpty()) return;
        // liquidaciones último año, tipo sueldo(1)/anticipo(3)
        $liqs = $c->select("SELECT LIQ_COD, LIQ_NRO, LIQ_TIP, LIQ_FEC FROM $db.dbo.LIQUIDAC WHERE LIQ_FEC >= ? AND (LIQ_TIP=1 OR LIQ_TIP=3) ORDER BY LIQ_FEC DESC", [$desde]);
        // para cada (empleado, concepto) quedarse con la última liquidación
        $ultima = [];   // [cod][tip] => ['nro','fec']
        foreach ($liqs as $l) {
            $cod = (int) $l->LIQ_COD; $tip = (int) $l->LIQ_TIP;
            if (!$emp->has($cod)) continue;
            if (!isset($ultima[$cod][$tip])) $ultima[$cod][$tip] = ['nro' => (int) $l->LIQ_NRO, 'fec' => $this->pf($l->LIQ_FEC)];
        }
        // sumar netos por (empleado, concepto) desde LIQ_ITE de esa liquidación
        foreach ($ultima as $cod => $tips) {
            foreach ($tips as $tip => $liq) {
                $neto = (float) ($c->select("SELECT SUM(ISNULL(LIT_HAB,0)-ISNULL(LIT_DED,0)) n FROM $db.dbo.LIQ_ITE WHERE LIT_NRO = ?", [$liq['nro']])[0]->n ?? 0);
                if ($neto == 0.0) continue;
                $e = $emp[$cod];
                $empNom = (int) $e->PER_EMP === 1 ? 'AUTOELEVADORES' : 'LOGISTICA';
                $banNom = trim((string) $e->Per_BAD);
                $fecEst = $this->fechaHaber($tip);
                if (!$fecEst || $fecEst->gt($this->f4)) continue;
                // aguinaldo: si la semana cae en junio o diciembre, ×1.5
                $mesSem = $fecEst->month;
                $monto = $neto * (($mesSem === 6 || $mesSem === 12) ? 1.5 : 1.0);
                $det = 'HABERES ' . $empNom . ' ( ' . $banNom . ' )';
                $this->acum('H', $det, (int) $e->Per_BAN, $banNom, $fecEst, $monto, -1);
            }
        }
    }

    /** Fecha estimada de pago de haberes: sueldo(1)=último hábil del mes próximo; anticipo(3)=15 hábil. */
    private function fechaHaber(int $concepto): ?Carbon
    {
        $base = Carbon::today();
        $mes = $base->month + 1; $anio = $base->year;
        if ($mes > 12) { $mes = 1; $anio++; }
        if ($concepto === 1) {
            $f = Carbon::create($anio, $mes, 1)->subDay();               // último día del mes actual+? => (mes próximo,1)-1 = fin de mes en curso?
            // Fox: Date(MANIO,MMES,1)-1  con MMES=mes próximo => último día del mes ACTUAL
            if ($f->month === 12 && $f->day === 31) $f->subDay();
        } else {
            $f = Carbon::create($anio, $mes, 15);
        }
        while ($f->dayOfWeek === 0 || $f->dayOfWeek === 6) $f->subDay();   // dom/sáb → hábil anterior
        return $f;
    }

    /** T — Transferencias posibles (proyectar_transferencias_de_una_semana por cada semana). Resta. */
    private function cargarTransferencias(): void
    {
        foreach ([$this->f1, $this->f2, $this->f3, $this->f4] as $lunes) {
            foreach ($this->proyectarTransferenciasSemana($lunes) as $t) {
                // semana: Week(fecha); si cae domingo, +1 (regla Fox)
                $fecha = $t['fecha'];
                $this->acum('T', $t['detalle'], $t['banco_cod'], $t['banco_nom'], $fecha, $t['importe'], -1);
            }
        }
    }

    /** Réplica de rutinas.proyectar_transferencias_de_una_semana(). */
    private function proyectarTransferenciasSemana(Carbon $lunes): array
    {
        $c = $this->conn();
        $fdesde = $lunes->copy()->subDays(2);                 // sábado anterior
        $fhasta = $fdesde->copy()->addDays(6);
        // mismo rango un mes antes (mismo día): desde = fdesde - ~1 mes (mismo día del mes)
        $desdeMesAnt = $this->mismoDiaMesAnterior($fdesde);
        $hastaMesAnt = $desdeMesAnt->copy()->addDays(6);

        // formas de pago tipo transferencia/interbanking pagadas en el rango del mes anterior
        $mtr = $c->table('cta_forma as A')->join('O_PAGOS as B', 'A.CTA_OPA', '=', 'B.OPA_PAG')
            ->whereBetween('B.OPA_FEC', [$desdeMesAnt->format('Y-m-d'), $hastaMesAnt->format('Y-m-d')])
            ->where(function ($q) { $q->whereRaw("UPPER(A.CTA_DET) LIKE '%TRANS%'")->orWhereRaw("UPPER(A.CTA_DET) LIKE '%INTERBANKING%'"); })
            ->get(['A.CTA_OPA', 'A.CTA_COD', 'B.OPA_FEC', 'A.CTA_DET']);

        // por proveedor (CTA_COD): última fecha de pago del rango
        $porProv = [];
        foreach ($mtr as $m) {
            $cod = (int) $m->CTA_COD;
            $f = $this->pf($m->OPA_FEC);
            if (!isset($porProv[$cod]) || ($f && $f->gt($porProv[$cod]['fec']))) {
                $porProv[$cod] = ['fec' => $f, 'det' => (string) $m->CTA_DET];
            }
        }

        $prov = $this->proveedores();
        $bancos = $this->bancos();
        $out = [];
        foreach ($porProv as $cod => $info) {
            // saldo pendiente de CC_PROVEEDOR
            $cc = $c->table('CC_PROVEEDOR')->where('CTA_COD', $cod)->where('CTA_TIP', 'P')
                ->whereRaw('(CTA_VEN) <> ?', ['1900-01-01'])->get();
            $sal = 0.0;
            foreach ($cc as $r) {
                $vencim = $this->pf($r->CTA_VEN);
                if (!$vencim) continue;
                $vm30 = $vencim->copy()->subDays(30);
                if ($vm30->lt(Carbon::create(2000, 1, 1)) || $vm30->gt(Carbon::today()->addDays(90))) continue;
                if (round((float) $r->CTA_HAB, 2) == round((float) $r->CTA_TOT, 2)) continue;
                $pen = round((float) $r->CTA_HAB, 2) - round((float) $r->CTA_TOT, 2);
                if (substr((string) $r->CTA_DES, 0, 2) === 'NC') $pen *= -1;
                $sal += $pen;
            }
            if ($sal <= 0 || !$info['fec']) continue;
            // fecha estimada = último pago del mes anterior + ~1 mes (mismo día)
            $fecEst = $this->mismoDiaMesSiguiente($info['fec']);
            if ($fecEst->lt($fdesde) || $fecEst->gt($fhasta)) continue;
            // banco: el que aparece en el detalle de la forma de pago
            $bancoCod = 0; $bancoNom = '';
            foreach ($bancos as $b) {
                if ($b['nba'] !== '' && stripos($info['det'], $b['nba']) !== false) { $bancoCod = $b['cod']; $bancoNom = $b['nba']; break; }
            }
            $nom = trim((string) ($prov[$cod] ?? ''));
            $det = 'TRANSFERENCIA A ' . $nom . ($bancoNom !== '' ? ' ( ' . $bancoNom . ' )' : '');
            $out[] = ['detalle' => $det, 'importe' => $sal, 'fecha' => $fecEst, 'banco_cod' => $bancoCod, 'banco_nom' => $bancoNom];
        }
        return $out;
    }

    /** O — Cobranzas (proyección por días promedio de cobro). Suma. Fila única. */
    private function cargarCobranzas(): void
    {
        $c = $this->conn();
        $hoy = Carbon::today();
        // MHOY = lunes de esta semana (o mañana si hoy es domingo)
        $mhoy = $hoy->dayOfWeek === 0 ? $hoy->copy()->addDay() : $hoy->copy()->startOfWeek(Carbon::MONDAY);
        // 9 semanas desde MHOY (semana 0 = vencidos, antes de MHOY)
        $semanas = [];
        for ($i = 0; $i < 9; $i++) {
            $d = $mhoy->copy()->addDays($i * 7);
            $semanas[] = ['num' => $i + 1, 'desde' => $d, 'hasta' => $d->copy()->addDays(6), 'importe' => 0.0];
        }
        $vencidos = ['desde' => Carbon::create(2000, 1, 1), 'hasta' => $mhoy->copy()->subDay()];
        $mhasta = $semanas[8]['desde'];   // límite: inicio de la semana 9

        $desde = $hoy->copy()->subDays(365)->format('Y-m-d');
        $hasta = $hoy->format('Y-m-d');

        // promedio de días de cobro por cliente (de RECIBOS último año)
        $rec = $c->table('RECIBOS')->whereBetween('REC_FEC', [$desde, $hasta])
            ->get(['REC_COD', 'REC_TOT', 'REC_PD1', 'REC_PD2']);
        $prom = [];   // [cli] => ['cob','t1','t2']
        foreach ($rec as $r) {
            $cli = (int) $r->REC_COD;
            $prom[$cli]['cob'] = ($prom[$cli]['cob'] ?? 0) + (float) $r->REC_TOT;
            $prom[$cli]['t1'] = ($prom[$cli]['t1'] ?? 0) + (float) $r->REC_TOT * ((float) $r->REC_PD1 > 0 ? (float) $r->REC_PD1 : 1);
            $prom[$cli]['t2'] = ($prom[$cli]['t2'] ?? 0) + (float) $r->REC_TOT * ((float) $r->REC_PD2 > 0 ? (float) $r->REC_PD2 : 1);
        }
        $prom3 = function (int $cli): int {
            $p = $this->promCache[$cli] ?? null;
            return $p ?? 0;
        };
        $this->promCache = [];
        foreach ($prom as $cli => $d) {
            $p1 = ($d['cob'] > 0 && $d['t1'] > $d['cob']) ? round($d['t1'] / $d['cob']) : 0;
            $p2 = ($d['cob'] > 0 && $d['t2'] > $d['cob']) ? round($d['t2'] / $d['cob']) : 0;
            $this->promCache[$cli] = (int) round($p1 + $p2);
        }

        // NOTA: los cheques en cartera NO se suman acá (van en la línea aparte tipo R);
        // en el Fox alimentan LOSCLIENTES/LOSMESES pero no LASSEMANAS.IMPORTE.

        // saldos abiertos de CC_CLIENTES → fecha estimada = CTA_FEC + prom3(cliente)
        $ccs = $c->table('CC_CLIENTES')->where('CTA_TIP', 'C')
            ->whereRaw("CTA_VEN <> '1900-01-01'")->whereRaw('CTA_DEB + CTA_HAB > CTA_TOT')->get();
        foreach ($ccs as $r) {
            $msal = round((float) $r->CTA_HAB, 2) - round((float) $r->CTA_TOT, 2);
            if (substr((string) $r->CTA_DES, 0, 2) === 'NC') $msal = -$msal;
            $mfec = $this->pf($r->CTA_FEC);
            if (!$mfec) continue;
            $mdias = $this->promCache[(int) $r->CTA_COD] ?? 0;
            $mven = $mfec->copy()->addDays($mdias);
            $this->bucketSemana($semanas, $vencidos, $mhasta, $mven, $msal);
        }

        // cargar a la muestra: fila única "PROYECCION COBRANZAS", por semana Fox del "desde"
        $det = 'PROYECCION COBRANZAS';
        foreach ($semanas as $s) {
            if ($s['importe'] == 0.0) continue;
            $this->acum('O', $det, 0, '', $s['desde'], $s['importe'], +1);
        }
    }

    private array $promCache = [];

    /** Suma $imp a la semana cuyo [desde,hasta] contiene $ven (o a la semana 9 si ≥ mhasta). */
    private function bucketSemana(array &$semanas, array $vencidos, Carbon $mhasta, Carbon $ven, float $imp): void
    {
        if ($ven->gte($mhasta)) { $semanas[8]['importe'] += $imp; return; }
        foreach ($semanas as &$s) {
            if ($ven->betweenIncluded($s['desde'], $s['hasta'])) { $s['importe'] += $imp; return; }
        }
        // si es anterior a la semana 1, cae en "vencidos" (no se muestra)
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cachés de apoyo
    // ─────────────────────────────────────────────────────────────────────────

    private ?array $bancosCache = null;
    /** [cod] => ['cod','des','nba'] */
    private function bancos(): array
    {
        if ($this->bancosCache !== null) return $this->bancosCache;
        $this->bancosCache = [];
        foreach ($this->conn()->table('CTAS_BAN')->get(['CBA_COD', 'CBA_DES', 'CBA_NBA']) as $b) {
            $this->bancosCache[(int) $b->CBA_COD] = ['cod' => (int) $b->CBA_COD, 'des' => trim((string) $b->CBA_DES), 'nba' => trim((string) $b->CBA_NBA)];
        }
        return $this->bancosCache;
    }

    private ?array $provCache = null;
    /** [cod] => nombre */
    private function proveedores(): array
    {
        if ($this->provCache !== null) return $this->provCache;
        $this->provCache = [];
        foreach ($this->conn()->table('PROVEEDO')->get(['PVR_COD', 'PVR_NOM']) as $p) {
            $this->provCache[(int) $p->PVR_COD] = trim((string) $p->PVR_NOM);
        }
        return $this->provCache;
    }

    /** Mismo día, un mes antes (como el bucle Fox que resta hasta cambiar de día/mes). */
    private function mismoDiaMesAnterior(Carbon $f): Carbon
    {
        return $f->copy()->subMonthNoOverflow();
    }
    private function mismoDiaMesSiguiente(Carbon $f): Carbon
    {
        return $f->copy()->addMonthNoOverflow();
    }
}
