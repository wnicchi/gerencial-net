<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ExigenciaController — ABM de Exigencias Legales a Contratados (tabla exigencias).
 *
 * Cada exigencia: descripción (EXI_DET), si es por única vez (EXI_UNI), meses de
 * vigencia (EXI_MES), si es exigencia de A.R.T. (EXI_ART) y notas (EXI_NOT).
 */
class ExigenciaController extends Controller
{
    /** @route GET /api/exigencias */
    public function index(): JsonResponse
    {
        $rows = DB::table('exigencias')->orderBy('EXI_COD')->get()->map(fn ($r) => [
            'cod'         => (int) $r->EXI_COD,
            'descripcion' => trim((string) $r->EXI_DET),
            'unica'       => (int) $r->EXI_UNI === 1,
            'meses'       => (int) $r->EXI_MES,
            'art'         => (int) $r->EXI_ART === 1,
            'notas'       => trim((string) $r->EXI_NOT),
        ]);
        return response()->json($rows);
    }

    /** @route POST /api/exigencias */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = ((int) DB::table('exigencias')->max('EXI_COD')) + 1;
        DB::table('exigencias')->insert(Registro::completar('exigencias', array_merge($this->vals($d), ['EXI_COD' => $cod])));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/exigencias/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $this->validar($request);
        $afect = DB::table('exigencias')->where('EXI_COD', $cod)->update($this->vals($d));
        if (!$afect) return response()->json(['message' => 'Exigencia no encontrada.'], 404);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/exigencias/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('contexi')->where('CEX_EXI', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay contratistas con esta exigencia asignada.'], 422);
        }
        DB::table('exigencias')->where('EXI_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion' => 'required|string|max:150',
            'unica'       => 'required|boolean',
            'meses'       => 'nullable|integer|min:0',
            'art'         => 'required|boolean',
            'notas'       => 'nullable|string|max:200',
        ]);
    }

    private function vals(array $d): array
    {
        return [
            'EXI_DET' => $d['descripcion'],
            'EXI_UNI' => $d['unica'] ? 1 : 0,
            'EXI_MES' => $d['unica'] ? 0 : (int) ($d['meses'] ?? 0),   // si es por única vez, no hay meses de vigencia
            'EXI_ART' => $d['art'] ? 1 : 0,
            'EXI_NOT' => trim((string) ($d['notas'] ?? '')),
        ];
    }
}
