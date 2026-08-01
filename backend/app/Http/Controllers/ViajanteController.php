<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ViajanteController — Planilla Libro de Viajantes (planilla_libro_viajantes).
 *
 * Calcula las comisiones por VENTAS y por COBROS de un viajante (vendedor) en un rango
 * de fechas. Lee de la base de GESTIÓN (conexión 'gestion' = sqlSILCAR): USUARIOS, RUB_OPER,
 * VEN_COMI, VENTAS, CTA_COBR, RECIBOS. Los datos del empleado salen de la base de RRHH.
 *
 * Reglas de comisión replicadas del FoxPro:
 *  - Vendedor = USUARIOS.USU_VEN donde LEGAJO = código de empleado y USU_VSN='S'.
 *  - Rubros que comisiona según el vendedor (Bonfigli=6, Alan=14, resto).
 *  - Comisión venta = monto * VCO_COV/100. Factura "B": monto = VEN_SUB / (1+VEN_PII/100). NC: monto negativo.
 *    Se saltean las ND del rubro 11.
 *  - Comisión cobro = (CTA_IMP/(1+VEN_PII/100)) * VCO_COC/100, matcheando CTA_DET (tipo/suc/nro) con la venta.
 */
class ViajanteController extends Controller
{
    /**
     * Buscador de vendedores: solo empleados habilitados como vendedor (USUARIOS.USU_VSN='S').
     * El LEGAJO de USUARIOS (en GESTIÓN) es el PER_COD del empleado.
     * @route GET /api/viajantes/vendedores?buscar=
     */
    public function vendedores(Request $request): JsonResponse
    {
        $b = trim((string) $request->input('buscar', ''));

        // Códigos (=PER_COD) de los usuarios que son vendedores
        $legajos = DB::connection('gestion')->table('USUARIOS')
            ->where('USU_VSN', 'S')->where('LEGAJO', '>', 0)
            ->pluck('LEGAJO')->map(fn ($v) => (int) $v)->unique()->all();
        if (!$legajos) return response()->json([]);

        $q = DB::table('personal')->whereIn('PER_COD', $legajos);
        if ($b !== '') {
            $q->where(fn ($w) => $w->where('PER_NOM', 'like', "%$b%")->orWhereRaw('CAST(PER_COD AS VARCHAR(20)) LIKE ?', ["%$b%"]));
        }
        $rows = $q->orderBy('PER_NOM')->limit(20)->get(['PER_COD', 'PER_NOM'])
            ->map(fn ($p) => ['cod' => (int) $p->PER_COD, 'nombre' => trim((string) $p->PER_NOM)]);

        return response()->json($rows);
    }

    /** @route GET /api/viajantes/planilla?legajo=&fecha1=&fecha2= */
    public function planilla(Request $request): JsonResponse
    {
        $d = $request->validate([
            'legajo'  => 'required|integer',
            'fecha1'  => 'required|date',
            'fecha2'  => 'required|date',
        ]);
        $legajo = (int) $d['legajo'];
        $f1 = $d['fecha1']; $f2 = $d['fecha2'];
        $g = DB::connection('gestion');

        // 1) Vendedor
        $usuario = $g->table('USUARIOS')->where('LEGAJO', $legajo)->where('USU_VSN', 'S')->first(['USU_VEN']);
        if (!$usuario) {
            return response()->json(['message' => 'El empleado no es un vendedor.'], 422);
        }
        $qvende = (int) $usuario->USU_VEN;

        // 2) Rubros que comisiona
        $platafs = [14, 16, 17, 19, 20, 26, 6];
        $rubQ = $g->table('RUB_OPER');
        if ($qvende === 6) {
            $rubQ->whereIn('RUB_COD', $platafs);
        } elseif ($qvende === 14) {
            $rubQ->where('RUB_COD', '!=', 6);
        } else {
            $rubQ->whereNotIn('RUB_COD', $platafs);
        }
        $rubros = $rubQ->get(['RUB_COD', 'RUB_DES'])->keyBy('RUB_COD');
        $rubCods = $rubros->keys()->map(fn ($v) => (int) $v)->all();

        // 3) Comisiones del vendedor por rubro
        $comision = $g->table('VEN_COMI')->where('VCO_VEN', $qvende)->get(['VCO_RUB', 'VCO_COV', 'VCO_COC'])
            ->keyBy(fn ($r) => (int) $r->VCO_RUB);

        $filas = [];

        // 4) COMISIONES POR VENTAS
        $ventas = $g->table('VENTAS')
            ->whereBetween('VEN_FEC', [$f1 . ' 00:00:00', $f2 . ' 23:59:59'])
            ->where('VEN_VDC', $qvende)->whereIn('VEN_RUB', $rubCods ?: [-1])
            ->orderBy('VEN_FEC')
            ->get(['VEN_FEC', 'VEN_RUB', 'VEN_TFA', 'VEN_SUC', 'VEN_NFA', 'VEN_CLI', 'VEN_DEC', 'VEN_SUB', 'VEN_PII']);

        foreach ($ventas as $v) {
            $rub = (int) $v->VEN_RUB;
            $tfa = trim((string) $v->VEN_TFA);
            // ND del rubro 11 no comisiona (las demás ND sí). La exclusión FoxPro de Bonfigli rubro 6
            // (VEN_VDC<>6) no aplica acá porque la consulta ya filtra VEN_VDC=qvende.
            if (strncasecmp($tfa, 'ND', 2) === 0 && $rub === 11) continue;

            $valor = (float) $v->VEN_SUB;
            if (strtoupper(substr((string) $v->VEN_TFA, 3, 1)) === 'B') {  // factura "B" (4ta posición)
                $valor = $valor / (1 + ((float) $v->VEN_PII / 100));
            }
            if (strncasecmp($tfa, 'NC', 2) === 0) $valor = -$valor;

            $porVta = (float) ($comision[$rub]->VCO_COV ?? 0);
            $comprob = $this->comprob($tfa, $v->VEN_SUC, $v->VEN_NFA);
            $filas[$comprob . '|' . (int) $v->VEN_CLI] = [
                'fecha'    => $v->VEN_FEC ? \Carbon\Carbon::parse($v->VEN_FEC)->format('Y-m-d') : '',
                'cli_cod'  => (int) $v->VEN_CLI,
                'cli_nom'  => trim((string) $v->VEN_DEC),
                'comprob'  => $comprob,
                'rubro'    => trim((string) ($rubros[$rub]->RUB_DES ?? '')),
                'rubcod'   => $rub,
                'monto'    => round($valor, 2),
                'por_vta'  => $porVta,
                'mon_vta'  => round($valor * $porVta / 100, 2),
                'por_cob'  => 0.0,
                'mon_cob'  => 0.0,
            ];
        }

        // 5) COMISIONES POR COBROS
        // Ventas válidas (todas, no-ND, del vendedor o Bonfigli) indexadas por tipo|suc|nro
        $ventasValidas = [];
        $g->table('VENTAS')->whereIn('VEN_RUB', $rubCods ?: [-1])
            ->where(fn ($w) => $qvende === 6 ? $w : $w->where('VEN_VDC', $qvende))
            ->whereRaw("LEFT(VEN_TFA,2) <> 'ND'")
            ->orderBy('VEN_FEC')
            ->get(['VEN_TFA', 'VEN_SUC', 'VEN_NFA', 'VEN_RUB', 'VEN_CLI', 'VEN_DEC', 'VEN_FEC', 'VEN_PII'])
            ->each(function ($v) use (&$ventasValidas) {
                $k = strtoupper(trim((string) $v->VEN_TFA)) . '|' . (int) $v->VEN_SUC . '|' . (int) $v->VEN_NFA;
                if (!isset($ventasValidas[$k])) $ventasValidas[$k] = $v;
            });

        // Cobros (cta_cobr join recibos en rango)
        $cobros = $g->table('CTA_COBR as c')->join('RECIBOS as r', 'c.CTA_REC', '=', 'r.REC_ORD')
            ->whereBetween('r.REC_FEC', [$f1 . ' 00:00:00', $f2 . ' 23:59:59'])
            ->orderBy('r.REC_FEC')
            ->get(['c.CTA_DET', 'c.CTA_IMP', 'r.REC_FEC']);

        foreach ($cobros as $c) {
            $det = trim((string) $c->CTA_DET);
            $tipo = strtoupper(trim(substr($det, 0, 5)));
            $suc  = (int) substr($det, 5, 4);
            $nro  = (int) substr($det, 10, 8);
            $k = $tipo . '|' . $suc . '|' . $nro;
            if (!isset($ventasValidas[$k])) continue;
            $v = $ventasValidas[$k];
            $rub = (int) $v->VEN_RUB;
            $tfa = trim((string) $v->VEN_TFA);

            $valor = (float) $c->CTA_IMP / (1 + ((float) $v->VEN_PII / 100));
            if (strncasecmp($tfa, 'NC', 2) === 0) $valor = -$valor;
            $porCob = (float) ($comision[$rub]->VCO_COC ?? 0);
            $comprob = $this->comprob($tfa, $v->VEN_SUC, $v->VEN_NFA);
            $key = $comprob . '|' . (int) $v->VEN_CLI;

            if (!isset($filas[$key])) {
                $filas[$key] = [
                    'fecha'   => $c->REC_FEC ? \Carbon\Carbon::parse($c->REC_FEC)->format('Y-m-d') : '',
                    'cli_cod' => (int) $v->VEN_CLI, 'cli_nom' => trim((string) $v->VEN_DEC),
                    'comprob' => $comprob, 'rubro' => trim((string) ($rubros[$rub]->RUB_DES ?? '')), 'rubcod' => $rub,
                    'monto' => round($valor, 2), 'por_vta' => 0.0, 'mon_vta' => 0.0, 'por_cob' => 0.0, 'mon_cob' => 0.0,
                ];
            }
            $filas[$key]['por_cob'] = $porCob;
            $filas[$key]['mon_cob'] = round($filas[$key]['mon_cob'] + $valor * $porCob / 100, 2);
        }

        // Ordenar por cli_cod, comprob
        $filas = array_values($filas);
        usort($filas, fn ($a, $b) => [$a['cli_cod'], $a['comprob']] <=> [$b['cli_cod'], $b['comprob']]);

        $comVentas = round(array_sum(array_column($filas, 'mon_vta')), 2);
        $comCobros = round(array_sum(array_column($filas, 'mon_cob')), 2);

        // Rubros distintos para el filtro
        $rubrosDistintos = collect($filas)->groupBy('rubcod')->map(fn ($g, $cod) => [
            'cod' => (int) $cod, 'desc' => $g[0]['rubro'],
        ])->values()->sortBy('desc')->values();

        return response()->json([
            'filas'          => $filas,
            'comision_ventas' => $comVentas,
            'comision_cobros' => $comCobros,
            'rubros'         => $rubrosDistintos,
            'vendedor'       => $this->datosVendedor($legajo),
        ]);
    }

    /** Comprobante con formato TFA SUC-NNNNNNNN (8 díg). */
    private function comprob(string $tfa, $suc, $nfa): string
    {
        return trim($tfa) . ' ' . (int) $suc . '-' . str_pad((string) (int) $nfa, 8, '0', STR_PAD_LEFT);
    }

    /** Datos del viajante para el encabezado del libro (ley 14546). */
    private function datosVendedor(int $cod): array
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first();
        if (!$p) return [];
        $zona = DB::table('lugar')->where('LUG_COD', $p->PER_LUGAR)->value('LUG_NOM');
        $emp = DB::table('empresas')->where('EMP_COD', $p->PER_EMP)->first();
        return [
            'nombre'  => mb_strtoupper(trim((string) $p->PER_NOM)),
            'cuil'    => trim((string) $p->PER_CUI),
            'legajo'  => trim((string) $p->PER_LEG),
            'zona'    => mb_strtoupper(trim((string) $zona)),
            'emp_nom' => $emp ? trim((string) $emp->EMP_NOM) : '',
            'emp_dom' => $emp ? trim((string) $emp->EMP_DOM) : '',
            'emp_cuit' => $emp ? trim((string) $emp->EMP_CUI) : '',
        ];
    }
}
