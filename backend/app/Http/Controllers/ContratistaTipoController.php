<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ContratistaTipoController — ABM de Tipos de Contratista (tabla contipo).
 *
 * Cada tipo tiene código (cot_con), nombre (cot_det) y clase (cot_foe): F=Fijo, E=Eventual.
 * Alimenta el combo "Tipo" del ABM de Contratistas Externos.
 */
class ContratistaTipoController extends Controller
{
    /** @route GET /api/contratista-tipos */
    public function index(): JsonResponse
    {
        $rows = DB::table('contipo')->orderBy('cot_det')->get()->map(fn ($r) => [
            'cod'    => (int) $r->cot_con,
            'nombre' => trim((string) $r->cot_det),
            'clase'  => trim((string) $r->cot_foe),
        ]);
        return response()->json($rows);
    }

    /** @route POST /api/contratista-tipos */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = ((int) DB::table('contipo')->max('cot_con')) + 1;
        DB::table('contipo')->insert(Registro::completar('contipo', [
            'cot_con' => $cod,
            'cot_det' => $datos['nombre'],
            'cot_foe' => $datos['clase'],
        ]));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/contratista-tipos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);
        $afect = DB::table('contipo')->where('cot_con', $cod)->update([
            'cot_det' => $datos['nombre'],
            'cot_foe' => $datos['clase'],
        ]);
        if (!$afect) return response()->json(['message' => 'Tipo no encontrado.'], 404);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/contratista-tipos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('contnom')->where('CTR_TIP', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay contratistas con este tipo.'], 422);
        }
        DB::table('contipo')->where('cot_con', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:100',
            'clase'  => 'required|in:F,E',
        ]);
    }
}
