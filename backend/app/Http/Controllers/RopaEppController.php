<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RopaEppController — ABM del catálogo de Uniforme/EPP (tabla ropa).
 *
 * Réplica de ropa_agregar.scx. Campos: ROP_COD (incremental), ROP_DES (descripción,
 * en mayúsculas), ROP_RUC (rubro → roparubro), ROP_RUD (descripción del rubro,
 * desnormalizada), ROP_COM (notas), ROP_INA (EPP inactivo), ROP_OBSEQUIO (es obsequio).
 */
class RopaEppController extends Controller
{
    /** @route GET /api/ropa-epp?buscar= */
    public function index(Request $request): JsonResponse
    {
        // Subconsulta para el rubro (evita que un leftJoin con RRU_COD duplicados multiplique filas).
        $q = DB::table('ropa as r');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where(function ($w) use ($b) {
                $w->where('r.ROP_DES', 'like', "%{$b}%");
                if (ctype_digit($b)) {
                    $w->orWhere('r.ROP_COD', (int) $b);
                }
            });
        }
        $rows = $q->orderBy('r.ROP_DES')->get([
            'r.ROP_COD', 'r.ROP_DES', 'r.ROP_RUC', 'r.ROP_COM', 'r.ROP_INA', 'r.ROP_OBSEQUIO',
            DB::raw('(SELECT TOP 1 LTRIM(RTRIM(ru.RRU_DES)) FROM roparubro ru WHERE ru.RRU_COD = r.ROP_RUC) as rubro_des'),
        ])->map(fn ($r) => [
            'cod'         => (int) $r->ROP_COD,
            'descripcion' => trim((string) $r->ROP_DES),
            'rubro'       => (int) $r->ROP_RUC,
            'rubro_des'   => trim((string) ($r->rubro_des ?? '')),
            'notas'       => trim((string) $r->ROP_COM),
            'inactivo'    => (bool) $r->ROP_INA,
            'obsequio'    => (bool) $r->ROP_OBSEQUIO,
        ])->values();
        return response()->json($rows);
    }

    /** @route POST /api/ropa-epp */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('ropa')->max('ROP_COD') + 1;
        DB::table('ropa')->insert(Registro::completar('ropa', array_merge(['ROP_COD' => $cod], $this->map($d))));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/ropa-epp/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('ropa')->where('ROP_COD', $cod)->exists()) {
            return response()->json(['message' => 'Prenda/EPP no encontrada.'], 404);
        }
        DB::table('ropa')->where('ROP_COD', $cod)->update($this->map($this->validar($request)));
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/ropa-epp/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $b = DB::table('ropa')->where('ROP_COD', $cod)->delete();
        if ($b === 0) {
            return response()->json(['message' => 'Prenda/EPP no encontrada.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion' => 'required|string|max:100',
            'rubro'       => 'nullable|integer',
            'notas'       => 'nullable|string|max:255',
            'inactivo'    => 'nullable|boolean',
            'obsequio'    => 'nullable|boolean',
        ]);
    }

    private function map(array $d): array
    {
        $rubroDes = '';
        if ((int) ($d['rubro'] ?? 0) > 0) {
            $rubroDes = trim((string) DB::table('roparubro')->where('RRU_COD', (int) $d['rubro'])->value('RRU_DES'));
        }
        return [
            'ROP_DES'       => mb_strtoupper(trim($d['descripcion'])),
            'ROP_RUC'       => (int) ($d['rubro'] ?? 0),
            'ROP_RUD'       => $rubroDes,
            'ROP_COM'       => trim((string) ($d['notas'] ?? '')),
            'ROP_INA'       => (int) ($d['inactivo'] ?? false),
            'ROP_OBSEQUIO'  => (int) ($d['obsequio'] ?? false),
        ];
    }
}
