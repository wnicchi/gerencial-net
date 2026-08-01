<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * TipoDocController — ABM de Tipos de Documentación (tabla tipo_doc).
 *
 * Campos: TDO_COD (código alfanumérico ingresado por el usuario, clave),
 * TDO_DES (detalle), TDO_TIP (tipo: C/E/K/L/M/X).
 */
class TipoDocController extends Controller
{
    /** Tipos válidos (el char interno no se expone al operador, se ve la etiqueta en el front). */
    private const TIPOS = ['C', 'E', 'K', 'L', 'M', 'X'];

    /** @route GET /api/tipo-doc */
    public function index(): JsonResponse
    {
        $items = DB::table('tipo_doc')->orderBy('TDO_DES')->get([
            DB::raw('LTRIM(RTRIM(TDO_COD)) as cod'),
            DB::raw('LTRIM(RTRIM(TDO_DES)) as detalle'),
            DB::raw('LTRIM(RTRIM(TDO_TIP)) as tipo'),
        ]);

        return response()->json($items);
    }

    /** @route POST /api/tipo-doc */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request, true);
        $cod = strtoupper(trim($datos['cod']));

        if (DB::table('tipo_doc')->whereRaw('UPPER(LTRIM(RTRIM(TDO_COD))) = ?', [$cod])->exists()) {
            return response()->json(['message' => "Ya existe un tipo de documentación con el código \"{$cod}\"."], 422);
        }

        DB::table('tipo_doc')->insert([
            'TDO_COD' => $cod,
            'TDO_DES' => trim($datos['detalle']),
            'TDO_TIP' => $datos['tipo'],
        ]);

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/tipo-doc/{cod} */
    public function update(Request $request, string $cod): JsonResponse
    {
        $datos = $this->validar($request, false);

        $afectados = DB::table('tipo_doc')
            ->whereRaw('UPPER(LTRIM(RTRIM(TDO_COD))) = ?', [strtoupper(trim($cod))])
            ->update([
                'TDO_DES' => trim($datos['detalle']),
                'TDO_TIP' => $datos['tipo'],
            ]);

        if ($afectados === 0 &&
            !DB::table('tipo_doc')->whereRaw('UPPER(LTRIM(RTRIM(TDO_COD))) = ?', [strtoupper(trim($cod))])->exists()) {
            return response()->json(['error' => 'Tipo de documentación no encontrado.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/tipo-doc/{cod} */
    public function destroy(string $cod): JsonResponse
    {
        $borrados = DB::table('tipo_doc')
            ->whereRaw('UPPER(LTRIM(RTRIM(TDO_COD))) = ?', [strtoupper(trim($cod))])
            ->delete();

        if ($borrados === 0) {
            return response()->json(['error' => 'Tipo de documentación no encontrado.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** Validación de los campos. En alta exige el código. */
    private function validar(Request $request, bool $alta): array
    {
        $reglas = [
            'detalle' => 'required|string|max:50',
            'tipo'    => 'required|string|in:' . implode(',', self::TIPOS),
        ];
        if ($alta) {
            $reglas['cod'] = 'required|string|max:2';
        }
        return $request->validate($reglas);
    }
}
