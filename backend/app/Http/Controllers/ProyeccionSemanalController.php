<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ProyeccionSemanalController — Estadísticas › Proyecciones Financieras Semanal.
 *
 * Migra proyecciones_semanales.scx. Misma lógica que la Mensual (14 conceptos,
 * ingresos suman / egresos restan) pero sobre UNA semana (lunes a viernes) y
 * pivoteando por BANCO en vez de por semana. Los importes se muestran en ABSOLUTO.
 * NO incluye Cobranzas (O) ni Cheques en Cartera (R) (comentados en el Fox). El
 * bucket por día usa Dow: sábado/domingo/lunes → LUNES; martes..viernes cada uno.
 *
 * SOLO LECTURA. Impuestos SOLO de Logística (sqlLOGIST), sin Autoelevadores.
 * Personal (Fondo Fijo/Haberes) desde sqlRRHHlog (cross-DB nombre de 3 partes).
 */
class ProyeccionSemanalController extends Controller
{
    private const RRHH_DB = 'sqlRRHHlog';

    private function conn() { return DB::connection('gestion'); }

    private Carbon $f1, $f2;      // lunes / viernes
    private string $d1, $d2, $dSab;
    private array $muestra = [];  // [tipo][detalle] => ['banco_cod','banco_nom','d'=>[1..5]]

    private const LABELS = [
        'I' => 'IMPUESTOS Y SERVICIOS', 'L' => 'LEASING', 'C' => 'CREDITOS', 'F' => 'FONDO FIJO',
        'S' => 'SEGURO', 'H' => 'HABERES', 'D' => 'CHEQUES DIFERIDOS', 'T' => 'TRANSFERENCIAS',
        'P' => 'CHEQUES A PROVEEDORES', 'E' => 'COMPRAS EQUIPOS', 'M' => 'INTERBANKING ESTUDIO',
        'V' => 'ORDEN DE COMPRAS VARIAS',
    ];

    private function pf(?string $v): ?Carbon
    {
        if (!$v) return null;
        try { $c = Carbon::parse($v); return $c->year <= 1900 ? null : $c; } catch (\Throwable $e) { return null; }
    }

    /** Bucket por día de la semana: Sáb/Dom/Lun→1(Lunes); Mar→2; Mié→3; Jue→4; Vie→5. */
    private function acum(string $tipo, string $detalle, int $bancoCod, string $bancoNom, ?Carbon $f, float $imp, int $signo): void
    {
        if (!$f) return;
        $foxDow = $f->dayOfWeek === 0 ? 1 : $f->dayOfWeek + 1;   // dom=1..sáb=7
        $col = ($foxDow <= 2 || $foxDow === 7) ? 1 : ($foxDow - 1);   // 3→2,4→3,5→4,6→5
        if ($col < 1 || $col > 5) return;
        $k = trim($detalle);
        if (!isset($this->muestra[$tipo][$k])) {
            $this->muestra[$tipo][$k] = ['banco_cod' => $bancoCod, 'banco_nom' => $bancoNom, 'd' => [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0]];
        }
        $this->muestra[$tipo][$k]['d'][$col] += $signo * $imp;
    }

    /** GET /api/estadisticas/proyecciones-semanal?lunes=YYYY-MM-DD (opcional). */
    public function index(Request $request): JsonResponse
    {
        $c = $this->conn();
        $base = $request->query('lunes') ? Carbon::parse($request->query('lunes')) : Carbon::today();
        $this->f1 = $base->dayOfWeek === 0 ? $base->copy() : $base->copy()->startOfWeek(Carbon::MONDAY);
        $this->f2 = $this->f1->copy()->addDays(4);
        $this->d1 = $this->f1->format('Y-m-d');
        $this->d2 = $this->f2->format('Y-m-d');
        $this->dSab = $this->f1->copy()->subDays(2)->format('Y-m-d');
        $this->muestra = [];

        $activos = [];
        foreach ($c->table('PROYECCION_PARAMETROS')->get() as $p) $activos[trim((string) $p->TIPO)] = trim((string) $p->ACTIVA) === '1' || $p->ACTIVA === true || (int) $p->ACTIVA === 1;
        $on = fn ($t) => $activos[$t] ?? false;

        $run = function (callable $fn) { try { $fn(); } catch (\Throwable $e) { \Log::warning('ProyeccionSemanal loader: ' . $e->getMessage()); } };

        // Conceptos activos → se muestran como fila aunque den cero (como el Fox: DISTINTOS_TIPOS).
        $conceptos = [];
        if ($on('I')) { $run(fn () => $this->cargarImpuestos()); $conceptos[] = 'I'; }
        if ($on('L')) { $run(fn () => $this->cargarCuotas('L')); $conceptos[] = 'L'; }
        if ($on('C')) { $run(fn () => $this->cargarCuotas('C')); $conceptos[] = 'C'; }
        if ($on('F')) { $run(fn () => $this->cargarFondoFijo()); $conceptos[] = 'F'; }
        if ($on('S')) { $run(fn () => $this->cargarSeguros()); $conceptos[] = 'S'; }
        if ($on('H')) { $run(fn () => $this->cargarHaberes()); $conceptos[] = 'H'; }
        if ($on('D')) { $run(fn () => $this->cargarChequesDiferidos()); $conceptos[] = 'D'; }
        if ($on('T')) { $run(fn () => $this->cargarTransferencias()); $conceptos[] = 'T'; }
        if ($on('P')) { $run(fn () => $this->cargarChequesCorrientes()); $conceptos[] = 'P'; }
        if ($on('E')) { $run(fn () => $this->cargarPedidoAutoelevadores()); $conceptos[] = 'E'; }
        $run(fn () => $this->cargarManuales()); $conceptos[] = 'M';
        if ($on('V')) { $run(fn () => $this->cargarOrdenesComprasVarias()); $conceptos[] = 'V'; }
        // NO cobranzas (O) ni cartera (R): comentados en el Fox semanal.

        // Bancos presentes (con datos), ordenados por nombre.
        $bancos = [];
        foreach ($this->muestra as $porTipo) foreach ($porTipo as $r) if ($r['banco_cod'] > 0) $bancos[$r['banco_cod']] = $r['banco_nom'];
        uasort($bancos, fn ($a, $b) => strcmp($a, $b));
        $bancoCods = array_keys($bancos);

        // Grilla: fila por concepto activo (aunque dé cero), columnas por banco (suma semanal, ABSOLUTO).
        $filas = [];
        foreach ($conceptos as $tipo) {
            $porBanco = array_fill_keys($bancoCods, 0.0);
            foreach ($this->muestra[$tipo] ?? [] as $r) {
                $sem = array_sum($r['d']);
                if (isset($porBanco[$r['banco_cod']])) $porBanco[$r['banco_cod']] += $sem;
            }
            $cols = array_map(fn ($cod) => round(abs($porBanco[$cod]), 2), $bancoCods);
            $filas[] = [
                'tipo'    => $tipo,
                'detalle' => self::LABELS[$tipo] ?? $tipo,
                'bancos'  => $cols,
                'total'   => round(array_sum($cols), 2),
            ];
        }
        usort($filas, fn ($a, $b) => strcmp($a['detalle'], $b['detalle']));

        // Totales por día (para el gráfico), en ABSOLUTO.
        $porDia = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
        foreach ($this->muestra as $porTipo) foreach ($porTipo as $r) foreach ($r['d'] as $i => $v) $porDia[$i] += abs($v);

        $dias = [];
        for ($i = 0; $i < 5; $i++) {
            $dd = $this->f1->copy()->addDays($i);
            $dias[] = ['label' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'][$i] . ' ' . $dd->day, 'total' => round($porDia[$i + 1], 2)];
        }

        // Desglose por día (para el informe PDF): por día, conceptos con datos × banco + total del día.
        $diasDetalle = [];
        for ($col = 1; $col <= 5; $col++) {
            $dd = $this->f1->copy()->addDays($col - 1);
            $rowsDia = [];
            foreach ($conceptos as $tipo) {
                $porBanco = array_fill_keys($bancoCods, 0.0); $hay = false;
                foreach ($this->muestra[$tipo] ?? [] as $r) {
                    $v = $r['d'][$col];
                    if (abs($v) < 0.005) continue;
                    if (isset($porBanco[$r['banco_cod']])) { $porBanco[$r['banco_cod']] += $v; $hay = true; }
                }
                if (!$hay) continue;
                $cols = array_map(fn ($cod) => round(abs($porBanco[$cod]), 2), $bancoCods);
                $rowsDia[] = ['detalle' => self::LABELS[$tipo] ?? $tipo, 'bancos' => $cols, 'total' => round(array_sum($cols), 2)];
            }
            usort($rowsDia, fn ($a, $b) => strcmp($a['detalle'], $b['detalle']));
            $diasDetalle[] = [
                'label' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'][$col - 1] . ' ' . $dd->day,
                'filas' => $rowsDia,
                'total' => round($porDia[$col], 2),
            ];
        }

        return response()->json([
            'titulo'  => 'PROYECCION SEMANA DEL ' . $this->f1->format('d/m/Y') . ' AL ' . $this->f2->format('d/m/Y'),
            'rango'   => ['desde' => $this->f1->format('d/m/Y'), 'hasta' => $this->f2->format('d/m/Y')],
            'bancos'  => array_values($bancos),
            'filas'   => $filas,
            'dias'    => $dias,
            'dias_detalle' => $diasDetalle,
            'total_gral' => round(array_sum(array_column($filas, 'total')), 2),
        ]);
    }

    // ── LOADERS (ventana de 1 semana, bucket por día) ──

    private function cargarImpuestos(): void
    {
        $rows = $this->conn()->table('OBLIGACIONES')
            ->where(fn ($q) => $q->whereBetween('OBL_FEC', [$this->dSab, $this->d2])->orWhereBetween('OBL_FE2', [$this->dSab, $this->d2]))->get();
        foreach ($rows as $o) {
            $det = trim((string) $o->OBL_DES) . ' ( NUEVO BANCO DE SANTA FE S.A. )';
            $f1 = $this->pf($o->OBL_FEC);
            if ($f1) {
                $imp = (float) $o->OBL_IMP > 0 ? (float) $o->OBL_IMP : (float) $o->OBL_EST;
                if ((float) $o->OBL_IMP == 0.0) { $h = $this->impHist($o->OBL_DES, $f1, 'OBL_IMP'); if ($h > 0) $imp = $h; }
                if ($imp > 0) $this->acum('I', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $f1, $imp, -1);
            }
            $f2 = $this->pf($o->OBL_FE2);
            if ($f2) {
                $imp = (float) $o->OBL_IM2;
                if ($imp == 0.0) { $h = $this->impHist($o->OBL_DES, $f1 ?? $f2, 'OBL_IM2'); if ($h > 0) $imp = $h; }
                if ($imp > 0) $this->acum('I', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $f2, $imp, -1);
            }
        }
    }

    private function impHist(string $desc, ?Carbon $ref, string $col): float
    {
        if (!$ref) return 0.0;
        $rows = $this->conn()->select("SELECT TOP 60 $col v, OBL_FEC f FROM OBLIGACIONES WHERE OBL_FEC < ? AND LTRIM(RTRIM(OBL_DES)) = ? AND $col > 0 ORDER BY OBL_FEC DESC", [$this->d1, trim($desc)]);
        $wref = Carbon::parse($ref)->weekOfYear;
        foreach ($rows as $r) { $f = $this->pf($r->f); if ($f && $f->weekOfYear === $wref) return (float) $r->v; }
        return 0.0;
    }

    /** L/C — cuotas de leasing/créditos. Resta. */
    private function cargarCuotas(string $tipo): void
    {
        $es = $tipo === 'L';
        $cuota = $es ? 'LEA_CUOT' : 'CRE_CUOT'; $ctr = $es ? 'LEASING' : 'CREDITOS';
        $fk = $es ? 'LEA_NRO' : 'CCRE_NRO'; $ven = $es ? 'LEA_VEN' : 'CCRE_VEN';
        $ctt = $es ? 'LEA_CTT' : 'CCRE_CTT'; $tot = $es ? 'LEA_TOTAL' : 'CCRE_TOTAL';
        $nroCtr = ($es ? 'LEA' : 'CRE') . '_NRO'; $bot = ($es ? 'LEA' : 'CRE') . '_BOT';
        $ban = ($es ? 'LEA' : 'CRE') . '_BAN'; $bad = ($es ? 'LEA' : 'CRE') . '_BAD';
        $nroC = $es ? 'LEA_NRO' : 'CCRE_NRO';
        $rows = $this->conn()->table($cuota)->join($ctr, "$cuota.$fk", '=', "$ctr.$nroCtr")
            ->whereBetween("$cuota.$ven", [$this->dSab, $this->d2])->get(["$cuota.*", "$ctr.$bot", "$ctr.$ban", "$ctr.$bad"]);
        foreach ($rows as $r) {
            $det = ($es ? 'LEASING ' : 'CREDITO ') . (int) $r->{$nroC} . ' - CONTRATO ' . (int) $r->{$ctt};
            if (trim((string) $r->{$bot}) === 'B') $det .= ' (' . trim((string) $r->{$bad}) . ')';
            $this->acum($tipo, $det, (int) $r->{$ban}, trim((string) $r->{$bad}), $this->pf($r->{$ven}), (float) $r->{$tot}, -1);
        }
    }

    private function cargarChequesDiferidos(): void
    {
        $rows = $this->conn()->table('MOVI_BCO')->whereBetween('MOV_FEC', [$this->dSab, $this->d2])
            ->where('MOV_HAB', '>', 0)->where('MOV_CHE', '>', 0)->get(['MOV_FEC', 'MOV_CTA', 'MOV_HAB']);
        $b = $this->bancos();
        foreach ($rows as $r) {
            $ban = trim((string) ($b[(int) $r->MOV_CTA]['des'] ?? ''));
            $this->acum('D', 'CHEQUES DIFERIDOS ( ' . $ban . ' )', (int) $r->MOV_CTA, $ban, $this->pf($r->MOV_FEC), (float) $r->MOV_HAB, -1);
        }
    }

    private function cargarSeguros(): void
    {
        $rows = $this->conn()->table('MOVI_BCO')->whereBetween('MOV_FEC', [$this->dSab, $this->d2])
            ->where('MOV_HAB', '>', 0)->where('MOV_PVR', 482)->where('MOV_CHE', 0)->get(['MOV_FEC', 'MOV_CTA', 'MOV_PVR', 'MOV_HAB']);
        $p = $this->proveedores();
        foreach ($rows as $r) {
            $nom = trim((string) ($p[(int) $r->MOV_PVR] ?? ''));
            $det = mb_substr('CTA ' . (int) $r->MOV_CTA . ' - PROV. (' . (int) $r->MOV_PVR . ') ' . $nom . ' ( BANCO DE SANTA FE S.A. )', 0, 100);
            $this->acum('S', $det, 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->MOV_FEC), (float) $r->MOV_HAB, -1);
        }
    }

    private function cargarPedidoAutoelevadores(): void
    {
        $rows = $this->conn()->table('AUTOELEV_PEDIDO')->whereBetween('APED_FPA', [$this->dSab, $this->d2])->get(['APED_FPA', 'APED_MONTO']);
        foreach ($rows as $r) $this->acum('E', 'PEDIDO DE AUTOELEVADORES ( NUEVO BANCO DE SANTA FE S.A. )', 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->APED_FPA), (float) $r->APED_MONTO, -1);
    }

    private function cargarManuales(): void
    {
        $rows = $this->conn()->table('PROYECCION_MANUAL')->whereBetween('CMPRO_FEC', [$this->d1, $this->d2])->get();
        foreach ($rows as $r) $this->acum('M', mb_strtoupper(trim((string) $r->CMPRO_DES)) . ' (NUEVO BCO.SANTA FE S.A.)', 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->CMPRO_FEC), (float) $r->CMPRO_IMP, -1);
    }

    private function cargarOrdenesComprasVarias(): void
    {
        // Fox: FECHA_DESDE = FECHA2-7, FECHA_HASTA = FECHA2.
        $desde = $this->f2->copy()->subDays(7)->format('Y-m-d');
        $rows = $this->conn()->table('OCOMPRAS_VARIOS')->whereBetween('COM_FPA', [$desde, $this->d2])->get();
        foreach ($rows as $r) $this->acum('V', mb_strtoupper(trim((string) $r->COM_RUD)) . ' (NUEVO BCO.SANTA FE S.A.)', 2, 'NUEVO BANCO DE SANTA FE S.A.', $this->pf($r->COM_FPA), (float) $r->COM_TOT, -1);
    }

    private function cargarChequesCorrientes(): void
    {
        $c = $this->conn();
        $fdes = $this->f1->copy()->subDays(31)->format('Y-m-d'); $fhas = $this->f1->copy()->subDays(1)->format('Y-m-d');
        $movs = $c->table('MOVI_BCO')->join('O_PAGOS', 'MOVI_BCO.MOV_NRO', '=', 'O_PAGOS.OPA_PAG')
            ->where('MOVI_BCO.MOV_TIP', 'P')->whereBetween('O_PAGOS.OPA_FEC', [$fdes, $fhas])->where('MOVI_BCO.MOV_PVR', '>', 0)
            ->distinct()->get(['MOVI_BCO.MOV_PVR', 'MOVI_BCO.MOV_CTA']);
        $prov = $this->proveedores(); $b = $this->bancos();
        foreach ($movs as $m) {
            $cc = $c->table('CC_PROVEEDOR')->where('CTA_COD', (int) $m->MOV_PVR)->where('CTA_TIP', 'P')->get();
            $sal = 0.0; $ven = null;
            foreach ($cc as $r) {
                $v = $this->pf($r->CTA_VEN); if (!$v) continue;
                if (round((float) $r->CTA_HAB, 2) == round((float) $r->CTA_TOT, 2)) continue;
                $pen = round((float) $r->CTA_HAB, 2) - round((float) $r->CTA_TOT, 2);
                if (substr((string) $r->CTA_DES, 0, 2) === 'NC') $pen *= -1;
                $sal += $pen; $ven = $v;
            }
            if ($sal <= 0 || !$ven || $ven->lt($this->f1) || $ven->gt($this->f2)) continue;
            $nom = trim((string) ($prov[(int) $m->MOV_PVR] ?? ''));
            $ban = trim((string) ($b[(int) $m->MOV_CTA]['nba'] ?? ''));
            $this->acum('P', 'CH.CORRIENTE A ' . $nom . ($ban !== '' ? ' ( ' . $ban . ' )' : ''), (int) ($b[(int) $m->MOV_CTA]['cod'] ?? 0), $ban, $ven, $sal, -1);
        }
    }

    private function cargarFondoFijo(): void
    {
        $c = $this->conn();
        $fdes = $this->f1->copy()->subDays(31)->format('Y-m-d'); $fhas = $this->f1->copy()->subDays(1)->format('Y-m-d');
        $vales = $c->table('VALES_EMPLEADOS')->whereBetween('VLE_FEC', [$fdes, $fhas])->get();
        $per = collect($c->select("SELECT PER_COD, PER_NOM FROM " . self::RRHH_DB . ".dbo.PERSONAL WHERE PER_AOP='A'"))->keyBy(fn ($p) => (int) $p->PER_COD);
        $acc = [];
        foreach ($vales as $v) {
            $p = (int) $v->VLE_PER; if (!$per->has($p)) continue;
            if (!isset($acc[$p])) $acc[$p] = ['imp' => 0.0, 'fec' => $this->pf($v->VLE_FEC)?->copy()->addDays(31)];
            $acc[$p]['imp'] += (float) $v->VLE_IMP;
        }
        foreach ($acc as $p => $d) {
            if ($d['imp'] == 0.0 || !$d['fec']) continue;
            $nom = trim((string) ($per[$p]->PER_NOM ?? ''));
            $this->acum('F', '(' . str_pad((string) $p, 6, ' ', STR_PAD_LEFT) . ' ) ' . $nom . ' ( SANTANDER RIO )', 1, 'SANTANDER RIO', $d['fec'], $d['imp'], -1);
        }
    }

    private function cargarHaberes(): void
    {
        $c = $this->conn(); $db = self::RRHH_DB;
        $desde = $this->f1->copy()->subDays(360)->format('Y-m-d');
        $emp = collect($c->select("SELECT PER_COD, PER_NOM, PER_EMP, Per_BAN, Per_BAD FROM $db.dbo.PERSONAL WHERE PER_AOP='A' AND PER_EMP=2"))->keyBy(fn ($p) => (int) $p->PER_COD);
        if ($emp->isEmpty()) return;
        $liqs = $c->select("SELECT LIQ_COD, LIQ_NRO, LIQ_TIP, LIQ_FEC FROM $db.dbo.LIQUIDAC WHERE LIQ_FEC >= ? AND (LIQ_TIP=1 OR LIQ_TIP=3) ORDER BY LIQ_FEC DESC", [$desde]);
        $ult = [];
        foreach ($liqs as $l) { $cod = (int) $l->LIQ_COD; $tip = (int) $l->LIQ_TIP; if (!$emp->has($cod)) continue; if (!isset($ult[$cod][$tip])) $ult[$cod][$tip] = (int) $l->LIQ_NRO; }
        foreach ($ult as $cod => $tips) foreach ($tips as $tip => $nro) {
            $neto = (float) ($c->select("SELECT SUM(ISNULL(LIT_HAB,0)-ISNULL(LIT_DED,0)) n FROM $db.dbo.LIQ_ITE WHERE LIT_NRO = ?", [$nro])[0]->n ?? 0);
            if ($neto == 0.0) continue;
            $e = $emp[$cod]; $banNom = trim((string) $e->Per_BAD);
            $fec = $this->fechaHaber($tip);
            if (!$fec || $fec->lt($this->f1) || $fec->gt($this->f2)) continue;
            $monto = $neto * (($fec->month === 6 || $fec->month === 12) ? 1.5 : 1.0);
            $this->acum('H', 'HABERES LOGISTICA ( ' . $banNom . ' )', (int) $e->Per_BAN, $banNom, $fec, $monto, -1);
        }
    }

    private function fechaHaber(int $concepto): ?Carbon
    {
        $t = Carbon::today();
        if ($concepto === 1) {
            $mes = $t->month + 1; $anio = $t->year; if ($mes > 12) { $mes = 1; $anio++; }
            $f = Carbon::create($anio, $mes, 1)->subDay();
            if ($f->month === 12 && $f->day === 31) $f->subDay();
        } else {
            $f = Carbon::create($t->year, $t->month, 15);
        }
        while ($f->dayOfWeek === 0 || $f->dayOfWeek === 6) $f->subDay();
        return $f;
    }

    private function cargarTransferencias(): void
    {
        $c = $this->conn();
        $fdesde = $this->f1->copy()->subDays(2); $fhasta = $fdesde->copy()->addDays(6);
        $desdeMesAnt = $fdesde->copy()->subMonthNoOverflow(); $hastaMesAnt = $desdeMesAnt->copy()->addDays(6);
        $mtr = $c->table('cta_forma as A')->join('O_PAGOS as B', 'A.CTA_OPA', '=', 'B.OPA_PAG')
            ->whereBetween('B.OPA_FEC', [$desdeMesAnt->format('Y-m-d'), $hastaMesAnt->format('Y-m-d')])
            ->where(fn ($q) => $q->whereRaw("UPPER(A.CTA_DET) LIKE '%TRANS%'")->orWhereRaw("UPPER(A.CTA_DET) LIKE '%INTERBANKING%'"))
            ->get(['A.CTA_COD', 'B.OPA_FEC', 'A.CTA_DET']);
        $porProv = [];
        foreach ($mtr as $m) { $cod = (int) $m->CTA_COD; $f = $this->pf($m->OPA_FEC); if (!isset($porProv[$cod]) || ($f && $f->gt($porProv[$cod]['fec']))) $porProv[$cod] = ['fec' => $f, 'det' => (string) $m->CTA_DET]; }
        $prov = $this->proveedores(); $b = $this->bancos();
        foreach ($porProv as $cod => $info) {
            $cc = $c->table('CC_PROVEEDOR')->where('CTA_COD', $cod)->where('CTA_TIP', 'P')->whereRaw('(CTA_VEN) <> ?', ['1900-01-01'])->get();
            $sal = 0.0;
            foreach ($cc as $r) {
                $v = $this->pf($r->CTA_VEN); if (!$v) continue;
                $vm30 = $v->copy()->subDays(30);
                if ($vm30->lt(Carbon::create(2000, 1, 1)) || $vm30->gt(Carbon::today()->addDays(90))) continue;
                if (round((float) $r->CTA_HAB, 2) == round((float) $r->CTA_TOT, 2)) continue;
                $pen = round((float) $r->CTA_HAB, 2) - round((float) $r->CTA_TOT, 2);
                if (substr((string) $r->CTA_DES, 0, 2) === 'NC') $pen *= -1;
                $sal += $pen;
            }
            if ($sal <= 0 || !$info['fec']) continue;
            $fec = $info['fec']->copy()->addMonthNoOverflow();
            if ($fec->lt($fdesde) || $fec->gt($fhasta)) continue;
            $bancoCod = 0; $bancoNom = '';
            foreach ($b as $bb) if ($bb['nba'] !== '' && stripos($info['det'], $bb['nba']) !== false) { $bancoCod = $bb['cod']; $bancoNom = $bb['nba']; break; }
            $nom = trim((string) ($prov[$cod] ?? ''));
            $this->acum('T', 'TRANSFERENCIA A ' . $nom . ($bancoNom !== '' ? ' ( ' . $bancoNom . ' )' : ''), $bancoCod, $bancoNom, $fec, $sal, -1);
        }
    }

    private ?array $bc = null;
    private function bancos(): array
    {
        if ($this->bc !== null) return $this->bc;
        $this->bc = [];
        foreach ($this->conn()->table('CTAS_BAN')->get(['CBA_COD', 'CBA_DES', 'CBA_NBA']) as $b) $this->bc[(int) $b->CBA_COD] = ['cod' => (int) $b->CBA_COD, 'des' => trim((string) $b->CBA_DES), 'nba' => trim((string) $b->CBA_NBA)];
        return $this->bc;
    }
    private ?array $pc = null;
    private function proveedores(): array
    {
        if ($this->pc !== null) return $this->pc;
        $this->pc = [];
        foreach ($this->conn()->table('PROVEEDO')->get(['PVR_COD', 'PVR_NOM']) as $p) $this->pc[(int) $p->PVR_COD] = trim((string) $p->PVR_NOM);
        return $this->pc;
    }
}
