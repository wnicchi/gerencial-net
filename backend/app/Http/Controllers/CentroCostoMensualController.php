<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CentroCostoMensualController — Centros Costos › Estadística Compras Centro Costo Mensual.
 *
 * Migra estadistica_compras_ccostos_mensual.scx. Matriz (CENTRO DE COSTO, tipo) × 12 MESES
 * para una ventana móvil de 12 meses que termina en el mes/año elegido, tomando los ítems
 * de CCOSTO_ITEM por su fecha (CCO_FEC). Cada centro puede aparecer en dos filas:
 *   • CPRAS  = ítems CCO_TIP ∈ ('C','G')   (compras + pagos varios)
 *   • VENTAS = ítems CCO_TIP = 'V'
 * El importe es CCO_IMP (en pesos) o, en dólares, CCO_IMP/cotización (CCO_COTDOL si >1, si
 * no la cotización del DÓLAR de la fecha más cercana hacia adelante).
 *
 * Filtros: QUE_CCOSTO (Todos / Uno), MONEDA (Peso/Dólar), TIPO (Todos=C+G+V / Compras=C+G /
 * Ventas=V). Total Anual + % sobre el total general (orden desc). Fila TOTAL sólo cuando es
 * "Todos" los centros y el tipo NO es "Todos" (igual que el Fox). SOLO LECTURA.
 *
 * Validado (mes 7/2026, ventana AGO 25..JUL 26, pesos, Todos):
 *   FRIMETAL PT CPRAS 1.276.282.317 (13,08%), FLETE 3° VENTAS 989.481.799 (10,14%),
 *   CORTEVA/DUPONT VENTAS 962.890.085 (9,87%); total general 9.754.483.281.
 */
class CentroCostoMensualController extends Controller
{
    private const MES3 = ['', 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    private function conn() { return DB::connection('gestion'); }

    /** GET /api/estadisticas/ccosto-mensual?mes=&anio=&ccosto=&moneda=P|D&tipo=T|C|V */
    public function index(Request $request): JsonResponse
    {
        $hoy = Carbon::today();
        $mes = (int) $request->query('mes', $hoy->copy()->subMonthNoOverflow()->month);
        $anio = (int) $request->query('anio', $hoy->copy()->subMonthNoOverflow()->year);
        if ($mes < 1 || $mes > 12) $mes = $hoy->month;
        $ccosto = (int) $request->query('ccosto', 0);          // 0 = Todos
        $moneda = strtoupper((string) $request->query('moneda', 'P')) === 'D' ? 'D' : 'P';
        $tipo = strtoupper((string) $request->query('tipo', 'T'));
        if (!in_array($tipo, ['T', 'C', 'V'], true)) $tipo = 'T';

        // Ventana de 12 meses [inicio 1° día .. fin último día] terminando en (anio, mes).
        $fin = $anio * 12 + ($mes - 1);
        $ini = $fin - 11;
        $meses = [];
        for ($idx = $ini; $idx <= $fin; $idx++) {
            $y = intdiv($idx, 12); $m = ($idx % 12) + 1;
            $meses[] = ['key' => $y * 100 + $m, 'label' => self::MES3[$m] . ' ' . substr((string) $y, 2)];
        }
        $keys = array_column($meses, 'key');
        $posKey = array_flip($keys);
        $fDes = Carbon::create(intdiv($ini, 12), ($ini % 12) + 1, 1)->startOfDay();
        $fHas = Carbon::create($anio, $mes, 1)->endOfMonth()->endOfDay();

        // Tipos de ítem según filtro.
        $tipos = $tipo === 'C' ? ['C', 'G'] : ($tipo === 'V' ? ['V'] : ['C', 'G', 'V']);

        // Nombres de centro (catálogo).
        $nombres = [0 => 'GENERALES'];
        foreach ($this->conn()->table('CCOSTO')->get(['CCO_COD', 'CCO_DES']) as $r) {
            $nombres[(int) $r->CCO_COD] = trim((string) $r->CCO_DES);
        }

        // Cotizaciones del dólar (para moneda D), ordenadas por fecha.
        $dolar = [];
        if ($moneda === 'D') {
            foreach ($this->conn()->table('DOLAR')->orderBy('DOL_FEC')->get(['DOL_FEC', 'DOL_COT']) as $r) {
                $dolar[] = ['f' => Carbon::parse($r->DOL_FEC)->timestamp, 'c' => (float) $r->DOL_COT];
            }
        }

        // Ítems de la ventana.
        $q = $this->conn()->table('CCOSTO_ITEM')
            ->whereIn('CCO_TIP', $tipos)
            ->whereBetween('CCO_FEC', [$fDes->format('Y-m-d H:i:s'), $fHas->format('Y-m-d H:i:s')]);
        if ($ccosto !== 0) $q->where('CCO_COD', $ccosto);
        $items = $q->get(['CCO_COD', 'CCO_TIP', 'CCO_FEC', 'CCO_IMP', 'CCO_COTDOL', 'CCO_DES']);

        // Matriz [claveFila] => ['codigo','grp','nombre','montos'[12]].
        $mat = [];
        foreach ($items as $it) {
            $y = (int) substr((string) $it->CCO_FEC, 0, 4);
            $m = (int) substr((string) $it->CCO_FEC, 5, 2);
            $mk = $y * 100 + $m;
            if (!isset($posKey[$mk])) continue;

            $grp = trim((string) $it->CCO_TIP) === 'V' ? 'V' : 'C';
            $imp = (float) $it->CCO_IMP;
            if ($moneda === 'D') {
                $cot = (float) $it->CCO_COTDOL;
                if ($cot <= 1) $cot = $this->cotDolar($dolar, Carbon::parse($it->CCO_FEC)->timestamp);
                $imp = $cot > 0 ? $imp / $cot : 0.0;
            }

            $cod = (int) $it->CCO_COD;
            $fila = $cod . '|' . $grp;
            if (!isset($mat[$fila])) {
                $nom = $nombres[$cod] ?? (trim((string) $it->CCO_DES) ?: ('CCosto ' . $cod));
                $mat[$fila] = ['codigo' => $cod, 'grp' => $grp, 'nombre' => $nom, 'montos' => array_fill(0, 12, 0.0)];
            }
            $mat[$fila]['montos'][$posKey[$mk]] += $imp;
        }

        // Filas con total != 0.
        $filas = [];
        $totalGeneral = 0.0;
        foreach ($mat as $f) {
            $montos = array_map(fn ($v) => round($v, 2), $f['montos']);
            $total = round(array_sum($montos), 2);
            if ($total == 0.0) continue;
            $filas[] = [
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'tipo' => $f['grp'] === 'V' ? 'VENTAS' : 'CPRAS',
                'montos' => $montos,
                'total' => $total,
            ];
            $totalGeneral += $total;
        }
        usort($filas, fn ($a, $b) => $b['total'] <=> $a['total']);
        foreach ($filas as &$f) $f['porcentaje'] = $totalGeneral != 0.0 ? round($f['total'] / $totalGeneral * 100, 2) : 0.0;
        unset($f);

        // Fila TOTAL sólo si Todos los centros y el tipo NO es Todos.
        $mostrarTotal = ($ccosto === 0 && $tipo !== 'T');
        $totMes = array_fill(0, 12, 0.0);
        foreach ($filas as $f) foreach ($f['montos'] as $i => $v) $totMes[$i] += $v;

        return response()->json([
            'mes' => $mes, 'anio' => $anio, 'moneda' => $moneda, 'tipo' => $tipo,
            'meses' => array_column($meses, 'label'),
            'filas' => $filas,
            'mostrar_total' => $mostrarTotal,
            'total' => [
                'montos' => array_map(fn ($v) => round($v, 2), $totMes),
                'total' => round($totalGeneral, 2),
            ],
        ]);
    }

    /** Cotización del dólar de la fecha más cercana hacia adelante (>= ts); si no hay, la última. */
    private function cotDolar(array $dolar, int $ts): float
    {
        foreach ($dolar as $d) if ($d['f'] >= $ts) return $d['c'];
        return $dolar ? end($dolar)['c'] : 0.0;
    }
}
