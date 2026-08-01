<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LicenciaController — Reloj / Licencias (licencias-laborales).
 *
 * ABM de los tipos de licencia (tabla licencias: LIC_COD, LIC_DET descripción, y tres marcas según el
 * formulario: LIC_ENF = "Licencia CON GOCE", LIC_NOA = "Licencia SIN GOCE", LIC_PLA = "No aplicar en la
 * planilla de novedades"). LIC_COD no es identity.
 */
class LicenciaController extends Controller
{
    /** @route GET /api/licencias */
    public function index(): JsonResponse
    {
        $filas = DB::table('licencias')->orderBy('lic_cod')->get(['lic_cod', 'lic_det', 'lic_noa', 'lic_enf', 'lic_pla'])
            ->map(fn ($l) => [
                'cod' => (int) $l->lic_cod, 'detalle' => trim((string) $l->lic_det),
                'conGoce' => (bool) $l->lic_enf, 'sinGoce' => (bool) $l->lic_noa, 'noPlanilla' => (bool) $l->lic_pla,
            ])->values();
        return response()->json(['licencias' => $filas]);
    }

    /** @route POST /api/licencias */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('licencias')->max('lic_cod') + 1;
        DB::table('licencias')->insert(Registro::completar('licencias', $this->columnas($d) + ['LIC_COD' => $cod]));
        return response()->json(['message' => 'Licencia creada correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/licencias/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('licencias')->where('lic_cod', $cod)->exists()) return response()->json(['message' => 'Licencia inexistente.'], 404);
        $d = $this->validar($request);
        DB::table('licencias')->where('lic_cod', $cod)->update($this->columnas($d));
        return response()->json(['message' => 'Licencia actualizada correctamente.']);
    }

    /** @route DELETE /api/licencias/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        // No permitir borrar si hay faltas que la usan.
        if (DB::table('reloj_faltas_diarias')->where('AFD_LIC', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay faltas cargadas con esta licencia.'], 422);
        }
        $n = DB::table('licencias')->where('lic_cod', $cod)->delete();
        if (!$n) return response()->json(['message' => 'Licencia inexistente.'], 404);
        return response()->json(['message' => 'Licencia eliminada correctamente.']);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'detalle'    => 'required|string|max:100',
            'conGoce'    => 'nullable|boolean',
            'sinGoce'    => 'nullable|boolean',
            'noPlanilla' => 'nullable|boolean',
        ]);
    }

    private function columnas(array $d): array
    {
        return [
            'LIC_DET' => mb_strtoupper(trim((string) $d['detalle'])),
            'LIC_ENF' => ($d['conGoce'] ?? false) ? 1 : 0,
            'LIC_NOA' => ($d['sinGoce'] ?? false) ? 1 : 0,
            'LIC_PLA' => ($d['noPlanilla'] ?? false) ? 1 : 0,
        ];
    }
}
