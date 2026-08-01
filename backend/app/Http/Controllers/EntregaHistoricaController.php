<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EntregaHistoricaController — Entregas históricas de EPP por empleado
 * (ropa_entrega_historica.scx).
 *
 * Lista todas las entregas (entregaropa) de un empleado y permite reimprimir el
 * recibo Resolución 299/2011 con los ítems seleccionados (incluye puestos y
 * elementos de protección necesarios según el puesto del empleado).
 */
class EntregaHistoricaController extends Controller
{
    /** @route GET /api/entrega-historica/empleado/{cod} — empleado + entregas. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_CUI', 'PER_NDO']);
        if (!$p) {
            return response()->json(['message' => 'El código del empleado no es válido.'], 404);
        }
        $cuil = trim((string) $p->PER_CUI);
        $items = DB::table('entregaropa as a')->join('ropa as b', 'a.ENR_ROP', '=', 'b.ROP_COD')
            ->where(DB::raw('LTRIM(RTRIM(a.ENR_CUIL))'), $cuil)
            ->orderByDesc('a.ENR_FEC')
            ->get(['a.ENR_ROP', 'b.ROP_DES', 'a.ENR_CUIL', 'a.ENR_FEC', 'a.ENR_CAN', 'a.ENR_MOT', 'a.ENR_MAD', 'a.ENR_TAD', 'a.ENR_CER'])
            ->map(fn ($r) => [
                'codigo'    => (int) $r->ENR_ROP,
                'detalle'   => trim((string) $r->ROP_DES),
                'cuil'      => trim((string) $r->ENR_CUIL),
                'fecha'     => $r->ENR_FEC ? substr((string) $r->ENR_FEC, 0, 10) : '',
                'cantidad'  => (int) $r->ENR_CAN,
                'motivo'    => trim((string) $r->ENR_MOT),
                'marca'     => trim((string) $r->ENR_MAD),
                'talle'     => trim((string) $r->ENR_TAD),
                'certifica' => trim((string) $r->ENR_CER) === 'S',
            ])->values();
        return response()->json(['nombre' => trim((string) $p->PER_NOM), 'cuil' => $cuil, 'dni' => trim((string) $p->PER_NDO), 'items' => $items]);
    }

    /** Convierte 'aaaa-mm-dd' (o cualquier fecha) a 'dd/mm/aaaa'; vacío si no hay. */
    private function fmtFecha(string $f): string
    {
        $f = trim($f);
        if ($f === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $f, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        return $f;
    }

    /** @route POST /api/entrega-historica/recibo/{cod} — datos del recibo Res. 299/2011. */
    public function recibo(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.codigo'  => 'nullable',
            'items.*.detalle' => 'nullable|string',
            'items.*.marca'   => 'nullable|string',
            'items.*.talle'   => 'nullable|string',
            'items.*.cantidad' => 'nullable|integer',
            'items.*.certifica' => 'boolean',
            'items.*.fecha'   => 'nullable|string',
            'motivo'          => 'nullable|string',
        ], ['items.min' => 'Seleccionar qué E.P.P. imprime.']);

        $per = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_CUI', 'PER_NDO']);
        if (!$per) {
            return response()->json(['message' => 'El código del empleado no es válido.'], 422);
        }
        $cuil = trim((string) $per->PER_CUI);
        $emp = DB::table('empresas')->orderBy('EMP_COD')->first();

        $puestos = DB::table('puestoempleado as pe')
            ->join('puestos as p', DB::raw('LTRIM(RTRIM(p.PUE_COD))'), '=', DB::raw('LTRIM(RTRIM(pe.PEM_PUE))'))
            ->where(DB::raw('LTRIM(RTRIM(pe.PEM_CUIL))'), $cuil)
            ->orderByDesc('pe.PEM_ACT')->orderByDesc('pe.PEM_FDES')
            ->pluck('p.PUE_DES')->map(fn ($x) => trim((string) $x))->filter()->values()->all();

        $elementos = DB::table('puestoempleado as pe')
            ->join('puestos as p', DB::raw('LTRIM(RTRIM(p.PUE_COD))'), '=', DB::raw('LTRIM(RTRIM(pe.PEM_PUE))'))
            ->join('elementos as el', DB::raw('LTRIM(RTRIM(el.ELE_PUE))'), '=', DB::raw('LTRIM(RTRIM(p.PUE_COD))'))
            ->where(DB::raw('LTRIM(RTRIM(pe.PEM_CUIL))'), $cuil)
            ->orderBy('el.ELE_DES')
            ->distinct()->pluck('el.ELE_DES')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();

        return response()->json([
            'empresa' => [
                'razon' => trim((string) ($emp->EMP_NOM ?? '')),
                'cuit'  => trim((string) ($emp->EMP_CUI ?? '')),
                'dom'   => trim((string) ($emp->EMP_DOM ?? '')),
                'loc'   => trim((string) ($emp->EMP_LOC ?? '')),
                'cpo'   => trim((string) ($emp->EMP_CPO ?? '')),
                'prv'   => trim((string) ($emp->EMP_PRV ?? '')),
            ],
            'empleado' => ['nombre' => trim((string) $per->PER_NOM), 'dni' => trim((string) $per->PER_NDO)],
            'items'    => array_map(fn ($it) => [
                'codigo'    => $it['codigo'] ?? '',
                'detalle'   => trim((string) ($it['detalle'] ?? '')),
                'marca'     => trim((string) ($it['marca'] ?? '')),
                'talle'     => trim((string) ($it['talle'] ?? '')),
                'cantidad'  => (int) ($it['cantidad'] ?? 0),
                'certifica' => (bool) ($it['certifica'] ?? false),
                'fecha'     => $this->fmtFecha($it['fecha'] ?? ''),
            ], $d['items']),
            'puestos'   => $puestos,
            'elementos' => $elementos,
            'motivo'    => trim((string) ($d['motivo'] ?? '')),
        ]);
    }
}
