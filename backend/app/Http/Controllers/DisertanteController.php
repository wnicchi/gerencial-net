<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DisertanteController — ABM de Disertantes (tabla disertantes).
 * Campos: dis_cod (incremental), dis_nom (nombre, obligatorio), dis_dom
 * (domicilio), dis_tel (teléfonos), dis_ema (email).
 */
class DisertanteController extends Controller
{
    /** @route GET /api/disertantes?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('disertantes');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where(function ($w) use ($b) {
                $w->where('dis_nom', 'like', "%{$b}%")->orWhere('dis_ema', 'like', "%{$b}%");
            });
        }
        return response()->json($q->orderBy('dis_nom')->get()->map(fn ($r) => [
            'cod'        => (int) $r->dis_cod,
            'nombre'     => trim((string) $r->dis_nom),
            'domicilio'  => trim((string) $r->dis_dom),
            'telefono'   => trim((string) $r->dis_tel),
            'email'      => trim((string) $r->dis_ema),
        ])->values());
    }

    /** @route POST /api/disertantes */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('disertantes')->max('dis_cod') + 1;
        DB::table('disertantes')->insert($this->datos($d) + ['dis_cod' => $cod]);
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/disertantes/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('disertantes')->where('dis_cod', $cod)->exists()) {
            return response()->json(['message' => 'Disertante no encontrado.'], 404);
        }
        DB::table('disertantes')->where('dis_cod', $cod)->update($this->datos($this->validar($request)));
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/disertantes/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('capacitacion')->where('CAP_DISER_COD', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el disertante está usado en jornadas de capacitación.'], 422);
        }
        $b = DB::table('disertantes')->where('dis_cod', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Disertante no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:50',
            'domicilio' => 'nullable|string|max:50',
            'telefono'  => 'nullable|string|max:50',
            'email'     => 'nullable|string|max:50',
        ]);
    }

    private function datos(array $d): array
    {
        return [
            'dis_nom' => trim($d['nombre']),
            'dis_dom' => trim((string) ($d['domicilio'] ?? '')),
            'dis_tel' => trim((string) ($d['telefono'] ?? '')),
            'dis_ema' => trim((string) ($d['email'] ?? '')),
        ];
    }
}
