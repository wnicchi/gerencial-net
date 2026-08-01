<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TableroController — parte de LOGÍSTICA / STOCK (WMS) del Tablero Gerencial.
 *
 * Lee SOLO en lectura la base del WMS (conexión 'wms' = LOGIST_UNIVERSAL). El WMS es
 * MULTICLIENTE: cada registro de stock/movimiento lleva la unidad de negocio
 * (STA_UNE / STO_UNE / REC_UNE / DEI_UNE = UNIDAD_NEGOCIO.UNE_COD). Por eso todos los
 * datasets se pueden ver por EMPRESA (filtro `empresa`) o GLOBALES (sin filtro) y,
 * además, se devuelve el reparto del stock POR empresa para el gráfico comparativo.
 *
 * La parte de RRHH (sueldos, dotación, HE, ausentismo) y las Alertas de personal se
 * sirven por los endpoints ya existentes /api/estadisticas y /api/alertas (base RRHH).
 */
class TableroController extends Controller
{
    private const CONEXION = 'wms';

    private const MESES = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /** Estado del packing (STA_TIP). 1=disponible; 2=obsoleto/2da; 3=rechazado; 4=bloqueado; 5=muestra/scrap. */
    private const ESTADOS = [1 => '1ra. / Disponible', 2 => 'Obsoleto / 2da.', 3 => 'Rechazado', 4 => 'Bloqueado', 5 => 'Muestra / Scrap'];

    /** @route GET /api/tablero/wms/empresas — clientes/unidades de negocio (para el filtro). */
    public function empresas(): JsonResponse
    {
        try {
            $con = DB::connection(self::CONEXION);
            // Unidades con al menos una posición de stock (las que importan al gerente).
            $conStock = $con->table('STOCK')->distinct()->pluck('STA_UNE')->map(fn ($x) => (int) $x)->all();
            $items = $con->table('UNIDAD_NEGOCIO')->orderBy('UNE_DES')->get(['UNE_COD', 'UNE_DES'])
                ->map(fn ($u) => [
                    'codigo'    => (int) $u->UNE_COD,
                    'nombre'    => trim((string) $u->UNE_DES) ?: ('Empresa ' . (int) $u->UNE_COD),
                    'con_stock' => in_array((int) $u->UNE_COD, $conStock, true),
                ])->values();
            return response()->json($items);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, request(), 'TABLERO-WMS');
            return response()->json(['message' => 'No se pudo leer la lista de empresas del WMS.'], 500);
        }
    }

    /**
     * @route GET /api/tablero/wms?empresa=&fecha1=&fecha2=
     * Todos los datasets de la sección Stock/Logística: KPIs, stock por empresa,
     * stock por estado, movimientos por mes (ingresos/egresos), recepciones y
     * despachos por mes, y alertas operativas (vencidos / por vencer / bloqueado).
     */
    public function wms(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa' => 'nullable|integer',
            'fecha1'  => 'nullable|date',
            'fecha2'  => 'nullable|date',
        ]);
        $emp = (int) ($d['empresa'] ?? 0);   // 0 = global (todas las empresas)
        $f2  = isset($d['fecha2']) ? \Carbon\Carbon::parse($d['fecha2'])->endOfDay()   : \Carbon\Carbon::now()->endOfDay();
        $f1  = isset($d['fecha1']) ? \Carbon\Carbon::parse($d['fecha1'])->startOfDay() : $f2->copy()->subMonths(11)->startOfMonth();
        if ($f1->gt($f2)) return response()->json(['message' => 'La fecha inicial no puede ser mayor que la final.'], 422);

        $meses = $this->rangoMeses($f1, $f2);

        try {
            $nombres = $this->nombresEmpresa();
            return response()->json([
                'empresa'        => $emp,
                'empresaNombre'  => $emp > 0 ? ($nombres[$emp] ?? ('Empresa ' . $emp)) : 'TODAS (Global)',
                'periodo'        => $meses,
                'kpis'           => $this->kpis($emp),
                'stockPorEmpresa'=> $this->stockPorEmpresa($nombres),
                'stockPorEstado' => $this->stockPorEstado($emp),
                'movimientos'    => $this->movimientosPorMes($emp, $f1, $f2, $meses, $nombres),
                'operacion'      => $this->operacionPorMes($emp, $f1, $f2, $meses, $nombres),
                'alertas'        => $this->alertasOperativas($emp, $nombres),
            ]);
        } catch (\Throwable $e) {
            \App\Support\RegistroError::registrar($e, $request, 'TABLERO-WMS');
            return response()->json(['message' => 'No se pudieron calcular las estadísticas de Stock/Logística. El detalle quedó en el Log de Errores.'], 500);
        }
    }

    // ── Catálogo de nombres de empresa (UNE_COD → UNE_DES) ────────────
    private function nombresEmpresa(): array
    {
        $out = [];
        foreach (DB::connection(self::CONEXION)->table('UNIDAD_NEGOCIO')->get(['UNE_COD', 'UNE_DES']) as $u) {
            $out[(int) $u->UNE_COD] = trim((string) $u->UNE_DES) ?: ('Empresa ' . (int) $u->UNE_COD);
        }
        return $out;
    }

    // ── KPIs del stock actual ────────────────────────────────────────
    private function kpis(int $emp): array
    {
        $q = DB::connection(self::CONEXION)->table('STOCK');
        if ($emp > 0) $q->where('STA_UNE', $emp);
        $r = $q->selectRaw('COUNT(*) as posiciones, SUM(STA_STP) as unidades, COUNT(DISTINCT STA_UNE) as empresas, COUNT(DISTINCT STA_PN) as productos')->first();

        $bloq = DB::connection(self::CONEXION)->table('STOCK')->where('STA_TIP', 4);
        if ($emp > 0) $bloq->where('STA_UNE', $emp);

        return [
            'posiciones' => (int) ($r->posiciones ?? 0),
            'unidades'   => round((float) ($r->unidades ?? 0), 2),
            'empresas'   => (int) ($r->empresas ?? 0),
            'productos'  => (int) ($r->productos ?? 0),
            'bloqueadas' => (int) $bloq->count(),
        ];
    }

    // ── Stock repartido por empresa (para el gráfico comparativo) ────
    private function stockPorEmpresa(array $nombres): array
    {
        $rows = DB::connection(self::CONEXION)->table('STOCK')
            ->selectRaw('STA_UNE as une, COUNT(*) as posiciones, SUM(STA_STP) as unidades')
            ->groupBy('STA_UNE')->get();

        $out = [];
        foreach ($rows as $r) {
            $une = (int) $r->une;
            $out[] = [
                'empresa'    => $une,
                'nombre'     => $nombres[$une] ?? ('Empresa ' . $une),
                'posiciones' => (int) $r->posiciones,
                'unidades'   => round((float) $r->unidades, 2),
            ];
        }
        usort($out, fn ($a, $b) => $b['posiciones'] <=> $a['posiciones']);
        return $out;
    }

    // ── Stock por estado (disponible / bloqueado / rechazado / ...) ──
    private function stockPorEstado(int $emp): array
    {
        $q = DB::connection(self::CONEXION)->table('STOCK');
        if ($emp > 0) $q->where('STA_UNE', $emp);
        $rows = $q->selectRaw('STA_TIP as tip, COUNT(*) as posiciones, SUM(STA_STP) as unidades')->groupBy('STA_TIP')->get();

        $out = [];
        foreach ($rows as $r) {
            $tip = (int) $r->tip; if ($tip < 1) $tip = 1;
            $out[] = [
                'estado'     => self::ESTADOS[$tip] ?? ('Estado ' . $tip),
                'tip'        => $tip,
                'posiciones' => (int) $r->posiciones,
                'unidades'   => round((float) $r->unidades, 2),
            ];
        }
        usort($out, fn ($a, $b) => $a['tip'] <=> $b['tip']);
        return $out;
    }

    // ── Movimientos de stock por mes (ingresos I vs egresos E) ───────
    // Además del agregado, adjunta `porEmpresa` por mes (para el modo "por empresas").
    private function movimientosPorMes(int $emp, \Carbon\Carbon $f1, \Carbon\Carbon $f2, array $meses, array $nombres): array
    {
        $rango = [$f1->format('Y-m-d'), $f2->format('Y-m-d')];
        $q = DB::connection(self::CONEXION)->table('MOVI_STOCK')->whereRaw('CAST(STO_FEC AS DATE) BETWEEN ? AND ?', $rango);
        if ($emp > 0) $q->where('STO_UNE', $emp);
        $rows = $q->selectRaw("
                YEAR(STO_FEC) as anio, MONTH(STO_FEC) as mes,
                SUM(CASE WHEN LTRIM(RTRIM(STO_IOE)) = 'E' THEN 1 ELSE 0 END) as egresos,
                SUM(CASE WHEN LTRIM(RTRIM(STO_IOE)) = 'E' THEN 0 ELSE 1 END) as ingresos
            ")
            ->groupByRaw('YEAR(STO_FEC), MONTH(STO_FEC)')->get();
        $porClave = [];
        foreach ($rows as $r) $porClave[sprintf('%04d-%02d', $r->anio, $r->mes)] = $r;

        // Total de movimientos por (mes, empresa).
        $qe = DB::connection(self::CONEXION)->table('MOVI_STOCK')->whereRaw('CAST(STO_FEC AS DATE) BETWEEN ? AND ?', $rango);
        if ($emp > 0) $qe->where('STO_UNE', $emp);
        $porEmp = $this->indexarPorMesEmpresa(
            $qe->selectRaw('YEAR(STO_FEC) as anio, MONTH(STO_FEC) as mes, STO_UNE as une, COUNT(*) as n')
               ->groupByRaw('YEAR(STO_FEC), MONTH(STO_FEC), STO_UNE')->get()
        );

        $out = [];
        foreach ($meses as $clave => $label) {
            $r = $porClave[$clave] ?? null;
            $out[] = [
                'mes'       => $clave, 'label' => $label,
                'ingresos'  => $r ? (int) $r->ingresos : 0,
                'egresos'   => $r ? (int) $r->egresos : 0,
                'porEmpresa'=> $this->porEmpresaDelMes($porEmp[$clave] ?? [], $nombres),
            ];
        }
        return $out;
    }

    // ── Operación por mes: recepciones y despachos ───────────────────
    private function operacionPorMes(int $emp, \Carbon\Carbon $f1, \Carbon\Carbon $f2, array $meses, array $nombres): array
    {
        $con = DB::connection(self::CONEXION);
        $rango = [$f1->format('Y-m-d'), $f2->format('Y-m-d')];

        // Recepciones por (mes) y por (mes, empresa) — RECEPCION.REC_UNE.
        $qr = $con->table('RECEPCION')->whereRaw('CAST(REC_FEC AS DATE) BETWEEN ? AND ?', $rango);
        if ($emp > 0) $qr->where('REC_UNE', $emp);
        $recEmp = $this->indexarPorMesEmpresa(
            $qr->selectRaw('YEAR(REC_FEC) as anio, MONTH(REC_FEC) as mes, REC_UNE as une, COUNT(*) as n')
               ->groupByRaw('YEAR(REC_FEC), MONTH(REC_FEC), REC_UNE')->get()
        );

        // Despachos por (mes, empresa) — la empresa vive en DESPA_ITEM.DEI_UNE.
        $qd = $con->table('DESPACHO as d')->join('DESPA_ITEM as i', 'i.DEI_NRO', '=', 'd.DES_NRO')
            ->whereRaw('CAST(d.DES_FEC AS DATE) BETWEEN ? AND ?', $rango);
        if ($emp > 0) $qd->where('i.DEI_UNE', $emp);
        $despEmp = $this->indexarPorMesEmpresa(
            $qd->selectRaw('YEAR(d.DES_FEC) as anio, MONTH(d.DES_FEC) as mes, i.DEI_UNE as une, COUNT(DISTINCT d.DES_NRO) as n')
               ->groupByRaw('YEAR(d.DES_FEC), MONTH(d.DES_FEC), i.DEI_UNE')->get()
        );

        $out = [];
        foreach ($meses as $clave => $label) {
            $rec = $recEmp[$clave] ?? []; $desp = $despEmp[$clave] ?? [];
            // Total operación (recepciones + despachos) por empresa para el modo apilado.
            $comb = $rec;
            foreach ($desp as $une => $n) $comb[$une] = ($comb[$une] ?? 0) + $n;
            $out[] = [
                'mes'         => $clave, 'label' => $label,
                'recepciones' => array_sum($rec),
                'despachos'   => array_sum($desp),
                'porEmpresa'  => $this->porEmpresaDelMes($comb, $nombres),
            ];
        }
        return $out;
    }

    /** Indexa filas {anio,mes,une,n} en [claveMes => [une => total]]. */
    private function indexarPorMesEmpresa($rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $clave = sprintf('%04d-%02d', $r->anio, $r->mes);
            $out[$clave][(int) $r->une] = (int) $r->n;
        }
        return $out;
    }

    /** Convierte [une => total] en [{empresa, nombre, total}] ordenado desc. */
    private function porEmpresaDelMes(array $mapa, array $nombres): array
    {
        $out = [];
        foreach ($mapa as $une => $total) {
            $out[] = ['empresa' => (int) $une, 'nombre' => $nombres[(int) $une] ?? ('Empresa ' . (int) $une), 'total' => (int) $total];
        }
        usort($out, fn ($a, $b) => $b['total'] <=> $a['total']);
        return $out;
    }

    // ── Alertas operativas del stock (vencidos / por vencer / bloqueado) ──
    private function alertasOperativas(int $emp, array $nombres): array
    {
        $hoy    = \Carbon\Carbon::today();
        $limite = $hoy->copy()->addDays(180);

        $q = DB::connection(self::CONEXION)->table('STOCK')
            ->whereRaw("STA_VEN IS NOT NULL")
            ->whereRaw("CAST(STA_VEN AS DATE) > '1900-01-01'");
        if ($emp > 0) $q->where('STA_UNE', $emp);
        $rows = $q->get(['STA_UNE', 'STA_PN', 'STA_DES', 'STA_VEN', 'STA_STP', 'STA_TIP']);

        $vencidos = []; $porVencer = [];
        foreach ($rows as $r) {
            $ven = $this->fecha($r->STA_VEN);
            if (!$ven) continue;
            $fila = [
                'empresa'  => $nombres[(int) $r->STA_UNE] ?? ('Empresa ' . (int) $r->STA_UNE),
                'pn'       => trim((string) $r->STA_PN),
                'des'      => trim((string) $r->STA_DES),
                'vence'    => $ven->format('Y-m-d'),
                'unidades' => round((float) $r->STA_STP, 2),
            ];
            if ($ven->lt($hoy)) $vencidos[] = $fila + ['dias' => -(int) $hoy->diffInDays($ven)];
            elseif ($ven->lte($limite)) $porVencer[] = $fila + ['dias' => (int) $hoy->diffInDays($ven)];
        }
        usort($vencidos, fn ($a, $b) => $a['vence'] <=> $b['vence']);
        usort($porVencer, fn ($a, $b) => $a['vence'] <=> $b['vence']);

        // Bloqueados agrupados por empresa.
        $qb = DB::connection(self::CONEXION)->table('STOCK')->where('STA_TIP', 4);
        if ($emp > 0) $qb->where('STA_UNE', $emp);
        $bloq = $qb->selectRaw('STA_UNE as une, COUNT(*) as posiciones, SUM(STA_STP) as unidades')->groupBy('STA_UNE')->get()
            ->map(fn ($r) => [
                'empresa'    => $nombres[(int) $r->une] ?? ('Empresa ' . (int) $r->une),
                'posiciones' => (int) $r->posiciones,
                'unidades'   => round((float) $r->unidades, 2),
            ])->values();

        return [
            'vencidos'   => array_slice($vencidos, 0, 100),
            'porVencer'  => array_slice($porVencer, 0, 100),
            'bloqueados' => $bloq,
            'resumen'    => [
                'vencidos'   => count($vencidos),
                'porVencer'  => count($porVencer),
                'bloqueados' => (int) $bloq->sum('posiciones'),
            ],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────
    private function rangoMeses(\Carbon\Carbon $f1, \Carbon\Carbon $f2): array
    {
        $out = [];
        for ($m = $f1->copy()->startOfMonth(); $m->lte($f2); $m->addMonth()) {
            $out[$m->format('Y-m')] = self::MESES[(int) $m->month] . ' ' . $m->year;
        }
        return $out;
    }

    private function fecha($v): ?\Carbon\Carbon
    {
        $s = substr((string) $v, 0, 10);
        if ($s === '' || $s === '1900-01-01') return null;
        try { $c = \Carbon\Carbon::parse($s); return $c->year > 1900 ? $c->startOfDay() : null; }
        catch (\Throwable) { return null; }
    }
}
