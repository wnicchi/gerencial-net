<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CategoriaController — ABM de Categorías (tabla categori).
 *
 * Campos: CAT_COD (código incremental), CAT_DES (descripción),
 * CAT_CON/CAT_CDE (convenio cód/desc), CAT_BAS (sueldo básico).
 * CAT_JOM (jornal/mensual) se toma del convenio elegido.
 */
class CategoriaController extends Controller
{
    /** @route GET /api/categorias */
    public function index(): JsonResponse
    {
        $items = DB::table('categori')->orderBy('CAT_DES')->get([
            'CAT_COD as cod',
            DB::raw('LTRIM(RTRIM(CAT_DES)) as descripcion'),
            'CAT_CON as convenio',
            DB::raw('LTRIM(RTRIM(CAT_CDE)) as convenio_desc'),
            'CAT_BAS as basico',
        ]);

        return response()->json($items);
    }

    /** @route POST /api/categorias */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('categori')->max('CAT_COD') + 1;

        DB::table('categori')->insert(array_merge($this->mapear($datos), [
            'CAT_COD' => $cod,
            'CAT_HOR' => 0,
        ]));

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/categorias/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);

        $afectados = DB::table('categori')->where('CAT_COD', $cod)->update($this->mapear($datos));

        if ($afectados === 0 && !DB::table('categori')->where('CAT_COD', $cod)->exists()) {
            return response()->json(['error' => 'Categoría no encontrada.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/categorias/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_CAT', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados con esta categoría.'], 422);
        }

        $borrados = DB::table('categori')->where('CAT_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Categoría no encontrada.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** Mapea datos validados a columnas; resuelve la descripción/tipo del convenio. */
    private function mapear(array $d): array
    {
        $con = DB::table('convenio')->where('CON_COD', $d['convenio'])->first(['CON_DES', 'CON_JOM']);

        return [
            'CAT_DES' => trim($d['descripcion']),
            'CAT_CON' => (int) $d['convenio'],
            'CAT_CDE' => $con ? substr(trim((string) $con->CON_DES), 0, 50) : '',
            'CAT_JOM' => $con ? (trim((string) $con->CON_JOM) ?: 'M') : 'M',
            'CAT_BAS' => $d['basico'] ?? 0,
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion' => 'required|string|max:20',
            'convenio'    => 'required|integer',
            'basico'      => 'nullable|numeric|min:0',
        ]);
    }
}
