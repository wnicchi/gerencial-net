<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * InvitadoController — ABM de Invitados (tabla invitados).
 *
 * Campos: INV_COD (código incremental), INV_NOM (nombre), INV_DOM (domicilio),
 * INV_TEL (teléfono), INV_CEL (celular), INV_NOT (notas).
 */
class InvitadoController extends Controller
{
    /** @route GET /api/invitados */
    public function index(): JsonResponse
    {
        $items = DB::table('invitados')->orderBy('INV_NOM')->get([
            'INV_COD as cod',
            DB::raw('LTRIM(RTRIM(INV_NOM)) as nombre'),
            DB::raw('LTRIM(RTRIM(INV_DOM)) as domicilio'),
            DB::raw('LTRIM(RTRIM(INV_TEL)) as telefono'),
            DB::raw('LTRIM(RTRIM(INV_CEL)) as celular'),
            DB::raw('LTRIM(RTRIM(INV_NOT)) as notas'),
        ]);

        return response()->json($items);
    }

    /** @route POST /api/invitados */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $cod = (int) DB::table('invitados')->max('INV_COD') + 1;

        DB::table('invitados')->insert([
            'INV_COD' => $cod,
            'INV_NOM' => $datos['nombre'],
            'INV_DOM' => $datos['domicilio'] ?? '',
            'INV_TEL' => $datos['telefono'] ?? '',
            'INV_CEL' => $datos['celular'] ?? '',
            'INV_NOT' => $datos['notas'] ?? '',
        ]);

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/invitados/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);

        $afectados = DB::table('invitados')->where('INV_COD', $cod)->update([
            'INV_NOM' => $datos['nombre'],
            'INV_DOM' => $datos['domicilio'] ?? '',
            'INV_TEL' => $datos['telefono'] ?? '',
            'INV_CEL' => $datos['celular'] ?? '',
            'INV_NOT' => $datos['notas'] ?? '',
        ]);

        if ($afectados === 0 && !DB::table('invitados')->where('INV_COD', $cod)->exists()) {
            return response()->json(['error' => 'Invitado no encontrado.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/invitados/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $borrados = DB::table('invitados')->where('INV_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Invitado no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** Validación de los campos del invitado. */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:50',
            'domicilio' => 'nullable|string|max:50',
            'telefono'  => 'nullable|string|max:20',
            'celular'   => 'nullable|string|max:20',
            'notas'     => 'nullable|string|max:2000',
        ]);
    }
}
