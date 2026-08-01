<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MedicoController — ABM de Médicos (tabla medicos).
 * Campos: MED_COD (incremental), MED_NOM (nombre, obligatorio), MED_DOM
 * (domicilio), MED_TEL (teléfonos), MED_NOT (notas).
 */
class MedicoController extends Controller
{
    /** @route GET /api/medicos?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('medicos');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where('MED_NOM', 'like', "%{$b}%");
        }
        return response()->json($q->orderBy('MED_NOM')->get()->map(fn ($r) => [
            'cod'       => (int) $r->MED_COD,
            'nombre'    => trim((string) $r->MED_NOM),
            'domicilio' => trim((string) $r->MED_DOM),
            'telefono'  => trim((string) $r->MED_TEL),
            'notas'     => trim((string) $r->MED_NOT),
        ])->values());
    }

    /** @route POST /api/medicos */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('medicos')->max('MED_COD') + 1;
        DB::table('medicos')->insert($this->datos($d) + ['MED_COD' => $cod]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/medicos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('medicos')->where('MED_COD', $cod)->exists()) {
            return response()->json(['message' => 'Médico no encontrado.'], 404);
        }
        DB::table('medicos')->where('MED_COD', $cod)->update($this->datos($this->validar($request)));
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/medicos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('examenes')->where('EXA_MED', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el médico tiene exámenes registrados.'], 422);
        }
        $b = DB::table('medicos')->where('MED_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Médico no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:50',
            'domicilio' => 'nullable|string|max:30',
            'telefono'  => 'nullable|string|max:30',
            'notas'     => 'nullable|string|max:2000',
        ]);
    }

    private function datos(array $d): array
    {
        return [
            'MED_NOM' => trim(mb_strtoupper($d['nombre'])),
            'MED_DOM' => trim((string) ($d['domicilio'] ?? '')),
            'MED_TEL' => trim((string) ($d['telefono'] ?? '')),
            'MED_NOT' => trim((string) ($d['notas'] ?? '')),
        ];
    }
}
