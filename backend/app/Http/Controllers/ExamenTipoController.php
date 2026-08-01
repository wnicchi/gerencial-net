<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ExamenTipoController — ABM de Tipos de Exámenes Médicos (tabla examtipo).
 * Campos: EXT_COD (incremental), EXT_DET (descripción, obligatorio).
 */
class ExamenTipoController extends Controller
{
    /** @route GET /api/examenes-tipo?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('examtipo');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where('EXT_DET', 'like', "%{$b}%");
        }
        return response()->json($q->orderBy('EXT_DET')->get()->map(fn ($r) => [
            'cod'         => (int) $r->EXT_COD,
            'descripcion' => trim((string) $r->EXT_DET),
        ])->values());
    }

    /** @route POST /api/examenes-tipo */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('examtipo')->max('EXT_COD') + 1;
        DB::table('examtipo')->insert(['EXT_COD' => $cod, 'EXT_DET' => $d['descripcion']]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/examenes-tipo/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('examtipo')->where('EXT_COD', $cod)->exists()) {
            return response()->json(['message' => 'Tipo de examen no encontrado.'], 404);
        }
        DB::table('examtipo')->where('EXT_COD', $cod)->update(['EXT_DET' => $this->validar($request)['descripcion']]);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/examenes-tipo/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('examenes')->where('EXA_TIP', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el tipo está usado en exámenes registrados.'], 422);
        }
        $b = DB::table('examtipo')->where('EXT_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Tipo de examen no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'descripcion' => 'required|string|max:50',
        ]);
        $d['descripcion'] = trim(mb_strtoupper($d['descripcion']));
        return $d;
    }
}
