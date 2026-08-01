<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CtaBancariaController — ABM de Cuentas Bancarias (tabla ctas_ban).
 * Campos: CBA_COD (código incremental), CBA_DES (descripción),
 * CBA_CSIL (cód. prov. banco en SILCAR), CBA_CLOG (cód. prov. banco en Logística).
 */
class CtaBancariaController extends Controller
{
    /** @route GET /api/ctas-bancarias */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('ctas_ban')->orderBy('CBA_DES')->get([
                'CBA_COD as cod',
                DB::raw('LTRIM(RTRIM(CBA_DES)) as descripcion'),
                'CBA_CSIL as cod_silcar',
                'CBA_CLOG as cod_logistica',
            ])
        );
    }

    /** @route POST /api/ctas-bancarias */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('ctas_ban')->max('CBA_COD') + 1;
        DB::table('ctas_ban')->insert([
            'CBA_COD'  => $cod,
            'CBA_DES'  => trim($d['descripcion']),
            'CBA_CSIL' => (int) ($d['cod_silcar'] ?? 0),
            'CBA_CLOG' => (int) ($d['cod_logistica'] ?? 0),
        ]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/ctas-bancarias/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $this->validar($request);
        $afectados = DB::table('ctas_ban')->where('CBA_COD', $cod)->update([
            'CBA_DES'  => trim($d['descripcion']),
            'CBA_CSIL' => (int) ($d['cod_silcar'] ?? 0),
            'CBA_CLOG' => (int) ($d['cod_logistica'] ?? 0),
        ]);
        if ($afectados === 0 && !DB::table('ctas_ban')->where('CBA_COD', $cod)->exists()) {
            return response()->json(['error' => 'Cuenta bancaria no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/ctas-bancarias/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $borrados = DB::table('ctas_ban')->where('CBA_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Cuenta bancaria no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion'   => 'required|string|max:50',
            'cod_silcar'    => 'nullable|integer|min:0',
            'cod_logistica' => 'nullable|integer|min:0',
        ]);
    }
}
