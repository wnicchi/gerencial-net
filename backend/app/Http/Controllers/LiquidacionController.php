<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LiquidacionController — Consultar y Borrar liquidaciones de sueldos.
 *
 * Una liquidación (LIQUIDAC) se identifica por empleado (LIQ_COD), tipo (LIQ_TIP),
 * mes (LIQ_MES) y año (LIQ_ANO). Sus ítems están en LIQ_ITE (LIT_NRO = LIQ_NRO).
 * El catálogo de tipos es sueldo_tip (STI_COD, STI_DES). Todo en la base RRHH.
 */
class LiquidacionController extends Controller
{
    /** Catálogo de tipos de liquidación. @route GET /api/liquidaciones/tipos */
    public function tipos(): JsonResponse
    {
        $rows = DB::table('sueldo_tip')->orderBy('STI_DES')->get(['STI_COD', 'STI_DES'])
            ->map(fn ($t) => ['cod' => (int) $t->STI_COD, 'desc' => trim((string) $t->STI_DES)]);
        return response()->json($rows);
    }

    /** Catálogo de conceptos de liquidación. @route GET /api/liquidaciones/conceptos */
    public function conceptos(): JsonResponse
    {
        $rows = DB::table('sueldo_con')->orderBy('SCO_DES')->get(['SCO_COD', 'SCO_DES'])
            ->map(fn ($t) => ['cod' => (int) $t->SCO_COD, 'desc' => trim((string) $t->SCO_DES)]);
        return response()->json($rows);
    }

    /**
     * Consulta una liquidación y sus ítems.
     * @route GET /api/liquidaciones/consultar?codigo=&tipo=&mes=&anio=
     */
    public function consultar(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $liq = $this->buscar($d);
        if (!$liq) {
            return response()->json(['message' => 'No se encontró la liquidación para los datos ingresados.'], 404);
        }

        $items = DB::table('LIQ_ITE')->where('LIT_NRO', $liq->LIQ_NRO)->orderBy('LIT_COD')
            ->get(['LIT_COD', 'LIT_DES', 'LIT_CAN', 'LIT_HAB', 'LIT_DED'])
            ->map(fn ($i) => [
                'codigo'   => (int) $i->LIT_COD,
                'detalle'  => trim((string) $i->LIT_DES),
                'cantidad' => (float) $i->LIT_CAN,
                'haber'    => (float) $i->LIT_HAB,
                'deduccion' => (float) $i->LIT_DED,
            ]);
        $total = round($items->sum('haber') - $items->sum('deduccion'), 2);

        return response()->json([
            'liquidacion' => [
                'nro'    => (int) $liq->LIQ_NRO,
                'empleado_cod' => (int) $liq->LIQ_COD,
                'nombre' => trim((string) ($liq->LIQ_DEP ?? '')),
                'cuil'   => trim((string) ($liq->LIQ_CUI ?? '')),
                'tipo'   => trim((string) ($liq->LIQ_TID ?? '')),
                'mes'    => (int) $liq->LIQ_MES,
                'anio'   => (int) $liq->LIQ_ANO,
            ],
            'items' => $items,
            'total' => $total,
        ]);
    }

    /**
     * Borra una liquidación completa (sus ítems y la cabecera).
     * @route DELETE /api/liquidaciones?codigo=&tipo=&mes=&anio=
     */
    public function borrar(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $liq = $this->buscar($d);
        if (!$liq) {
            return response()->json(['message' => 'No se encontró la liquidación para los datos ingresados.'], 404);
        }

        DB::transaction(function () use ($liq) {
            DB::table('LIQ_ITE')->where('LIT_NRO', $liq->LIQ_NRO)->delete();
            DB::table('LIQUIDAC')->where('LIQ_NRO', $liq->LIQ_NRO)->delete();
        });

        return response()->json(['ok' => true, 'nro' => (int) $liq->LIQ_NRO]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'codigo' => 'required|integer|min:1',
            'tipo'   => 'required|integer|min:1',
            'mes'    => 'required|integer|min:1|max:12',
            'anio'   => 'required|integer',
        ], ['tipo.required' => 'Seleccionar el tipo de liquidación.']);
    }

    private function buscar(array $d)
    {
        return DB::table('LIQUIDAC')
            ->where('LIQ_COD', (int) $d['codigo'])->where('LIQ_TIP', (int) $d['tipo'])
            ->where('LIQ_MES', (int) $d['mes'])->where('LIQ_ANO', (int) $d['anio'])
            ->first();
    }
}
