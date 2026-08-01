<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ConvenioController — ABM de Convenios (tabla convenio) con dos solapas:
 *  1) Configuración del convenio.
 *  2) Obligaciones de EPP por convenio (tabla convenio_epp + catálogo ropa).
 */
class ConvenioController extends Controller
{
    // ── Solapa 1: Convenio ────────────────────────────────────

    /** @route GET /api/convenios */
    public function index(): JsonResponse
    {
        $items = DB::table('convenio')->orderBy('CON_DES')->get([
            'CON_COD as cod',
            DB::raw('LTRIM(RTRIM(CON_DES)) as descripcion'),
            DB::raw('LTRIM(RTRIM(CON_JOM)) as tipo'),
            'CON_PNE as neto_hs_extras',
        ]);

        return response()->json($items);
    }

    /** @route GET /api/convenios/{cod} */
    public function show(int $cod): JsonResponse
    {
        $c = DB::table('convenio')->where('CON_COD', $cod)->first();
        if (!$c) return response()->json(['error' => 'Convenio no encontrado.'], 404);

        return response()->json([
            'cod'            => (int) $c->CON_COD,
            'descripcion'    => trim((string) $c->CON_DES),
            'tipo'           => trim((string) $c->CON_JOM) ?: 'M',
            'neto_hs_extras' => (float) $c->CON_PNE,
            'minimo_os'      => (int) $c->CON_MIN,
            'aplica_dias'    => (int) $c->CON_DTR === 1,
            'lun' => (int) $c->CON_LUN, 'mar' => (int) $c->CON_MAR, 'mie' => (int) $c->CON_MIE,
            'jue' => (int) $c->CON_JUE, 'vie' => (int) $c->CON_VIE, 'sab' => (int) $c->CON_SAB,
            'dom' => (int) $c->CON_DOM,
        ]);
    }

    /** @route POST /api/convenios */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('convenio')->max('CON_COD') + 1;
        DB::table('convenio')->insert(array_merge($this->mapear($datos), ['CON_COD' => $cod]));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/convenios/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);
        $afectados = DB::table('convenio')->where('CON_COD', $cod)->update($this->mapear($datos));
        if ($afectados === 0 && !DB::table('convenio')->where('CON_COD', $cod)->exists()) {
            return response()->json(['error' => 'Convenio no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/convenios/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('personal')->where('PER_CON', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados con este convenio.'], 422);
        }
        DB::table('convenio_epp')->where('conrop_con', $cod)->delete();
        $borrados = DB::table('convenio')->where('CON_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Convenio no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    // ── Solapa 2: Obligaciones de EPP ─────────────────────────

    /** @route GET /api/convenios/{cod}/epp */
    public function eppList(int $cod): JsonResponse
    {
        $items = DB::table('convenio_epp')->where('conrop_con', $cod)->orderBy('conrop_ropdes')->get([
            'conrop_rop as rop',
            DB::raw('LTRIM(RTRIM(conrop_ropdes)) as detalle'),
            'conrop_dias as dias',
        ]);
        return response()->json($items);
    }

    /** @route GET /api/convenios-ropa  (catálogo de EPP/ropa) */
    public function ropaCatalogo(): JsonResponse
    {
        $items = DB::table('ropa')->orderBy('ROP_DES')->get([
            'ROP_COD as cod',
            DB::raw('LTRIM(RTRIM(ROP_DES)) as detalle'),
        ]);
        return response()->json($items);
    }

    /** @route POST /api/convenios/{cod}/epp   Body: { rop, dias } */
    public function eppAdd(Request $request, int $cod): JsonResponse
    {
        $datos = $request->validate([
            'rop'  => 'required|integer|min:1',
            'dias' => 'required|integer|min:1',
        ]);

        $con = DB::table('convenio')->where('CON_COD', $cod)->first(['CON_DES']);
        if (!$con) return response()->json(['error' => 'Convenio no encontrado.'], 404);

        $rop = DB::table('ropa')->where('ROP_COD', $datos['rop'])->first(['ROP_DES']);
        if (!$rop) return response()->json(['message' => 'Debe seleccionar el EPP/Ropa.'], 422);

        if (DB::table('convenio_epp')->where('conrop_con', $cod)->where('conrop_rop', $datos['rop'])->exists()) {
            return response()->json(['message' => 'Ya existe este E.P.P. en las obligaciones de este convenio.'], 422);
        }

        DB::table('convenio_epp')->insert([
            'conrop_con'    => $cod,
            'conrop_condes' => substr(trim((string) $con->CON_DES), 0, 100),
            'conrop_rop'    => $datos['rop'],
            'conrop_ropdes' => substr(trim((string) $rop->ROP_DES), 0, 100),
            'conrop_dias'   => $datos['dias'],
        ]);

        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/convenios/{cod}/epp   Body: { rops: [..] } */
    public function eppDelete(Request $request, int $cod): JsonResponse
    {
        $datos = $request->validate([
            'rops'   => 'required|array|min:1',
            'rops.*' => 'integer',
        ]);

        DB::table('convenio_epp')->where('conrop_con', $cod)->whereIn('conrop_rop', $datos['rops'])->delete();
        return response()->json(['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function mapear(array $d): array
    {
        return [
            'CON_DES' => trim($d['descripcion']),
            'CON_JOM' => $d['tipo'] === 'J' ? 'J' : 'M',
            'CON_PNE' => $d['neto_hs_extras'] ?? 0,
            'CON_MIN' => (int) ($d['minimo_os'] ?? 0),
            'CON_DTR' => !empty($d['aplica_dias']) ? 1 : 0,
            'CON_LUN' => (int) ($d['lun'] ?? 0), 'CON_MAR' => (int) ($d['mar'] ?? 0),
            'CON_MIE' => (int) ($d['mie'] ?? 0), 'CON_JUE' => (int) ($d['jue'] ?? 0),
            'CON_VIE' => (int) ($d['vie'] ?? 0), 'CON_SAB' => (int) ($d['sab'] ?? 0),
            'CON_DOM' => (int) ($d['dom'] ?? 0),
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion'    => 'required|string|max:20',
            'tipo'           => 'required|in:J,M',
            'neto_hs_extras' => 'nullable|numeric|min:0',
            'minimo_os'      => 'nullable|integer|min:0',
            'aplica_dias'    => 'nullable|boolean',
            'lun' => 'nullable|integer|min:0|max:24', 'mar' => 'nullable|integer|min:0|max:24',
            'mie' => 'nullable|integer|min:0|max:24', 'jue' => 'nullable|integer|min:0|max:24',
            'vie' => 'nullable|integer|min:0|max:24', 'sab' => 'nullable|integer|min:0|max:24',
            'dom' => 'nullable|integer|min:0|max:24',
        ]);
    }
}
