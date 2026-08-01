<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ContratistaExternoController — ABM de Contratistas Externos (tabla contnom).
 *
 * Tiene 3 solapas:
 *  - Datos Generales: datos del contratista (estado, tipo, nombre, domicilio, etc.).
 *  - Exigencias en Obras: exigencias legales asignadas al contratista (tabla contexi).
 *  - Empleados Externos Asociados: empleados del contratista (tabla contemp).
 */
class ContratistaExternoController extends Controller
{
    // ── Datos Generales (contnom) ──────────────────────────────

    /** @route GET /api/contratistas-externos?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('contnom as c')->leftJoin('contipo as t', 't.cot_con', '=', 'c.CTR_TIP');
        if ($b = trim((string) $request->input('buscar', ''))) {
            $q->where('c.CTR_NOM', 'like', "%$b%");
        }
        $rows = $q->orderBy('c.CTR_NOM')->get([
            'c.CTR_COD', 'c.CTR_NOM', 'c.CTR_AOP', 'c.CTR_TIP', 'c.CTR_TEL', 'c.CTR_EMA',
            DB::raw('LTRIM(RTRIM(t.cot_det)) as tipo_nombre'),
        ])->map(fn ($c) => [
            'cod'    => (int) $c->CTR_COD,
            'nombre' => trim((string) $c->CTR_NOM),
            'estado' => trim((string) $c->CTR_AOP) === 'A' ? 'A' : 'P',
            'tipo'   => (int) $c->CTR_TIP,
            'tipo_nombre' => trim((string) $c->tipo_nombre),
            'telefono' => trim((string) $c->CTR_TEL),
            'email'  => trim((string) $c->CTR_EMA),
        ]);
        return response()->json($rows);
    }

    /** @route GET /api/contratistas-externos/{cod} */
    public function show(int $cod): JsonResponse
    {
        $c = DB::table('contnom')->where('CTR_COD', $cod)->first();
        if (!$c) return response()->json(['message' => 'Contratista no encontrado.'], 404);
        return response()->json([
            'cod'       => (int) $c->CTR_COD,
            'nombre'    => trim((string) $c->CTR_NOM),
            'domicilio' => trim((string) $c->CTR_DOM),
            'telefono'  => trim((string) $c->CTR_TEL),
            'email'     => trim((string) $c->CTR_EMA),
            'estado'    => trim((string) $c->CTR_AOP) === 'A' ? 'A' : 'P',
            'art'       => trim((string) $c->CTR_ART),
            'tipo'      => (int) $c->CTR_TIP,
        ]);
    }

    /** @route POST /api/contratistas-externos */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = ((int) DB::table('contnom')->max('CTR_COD')) + 1;
        DB::table('contnom')->insert(Registro::completar('contnom', array_merge($this->vals($d), ['CTR_COD' => $cod])));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/contratistas-externos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $this->validar($request);
        $afect = DB::table('contnom')->where('CTR_COD', $cod)->update($this->vals($d));
        if (!$afect) return response()->json(['message' => 'Contratista no encontrado.'], 404);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/contratistas-externos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('contemp')->where('CEM_CON', $cod)->exists() || DB::table('contexi')->where('CEX_CON', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: el contratista tiene empleados o exigencias asociadas.'], 422);
        }
        DB::table('contnom')->where('CTR_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:100',
            'domicilio' => 'nullable|string|max:100',
            'telefono'  => 'nullable|string|max:50',
            'email'     => 'nullable|string|max:100',
            'estado'    => 'required|in:A,P',
            'art'       => 'nullable|string|max:100',
            'tipo'      => 'nullable|integer',
        ]);
    }

    private function vals(array $d): array
    {
        return [
            'CTR_NOM' => mb_strtoupper(trim($d['nombre'])),
            'CTR_DOM' => trim((string) ($d['domicilio'] ?? '')),
            'CTR_TEL' => trim((string) ($d['telefono'] ?? '')),
            'CTR_EMA' => trim((string) ($d['email'] ?? '')),
            'CTR_AOP' => $d['estado'],
            'CTR_ART' => trim((string) ($d['art'] ?? '')),
            'CTR_TIP' => (int) ($d['tipo'] ?? 0),
        ];
    }

    // ── Exigencias asignadas (contexi) ─────────────────────────

    /** @route GET /api/contratistas-externos/{cod}/exigencias */
    public function exigencias(int $cod): JsonResponse
    {
        $asignadas = DB::table('contexi as x')->join('exigencias as e', 'x.CEX_EXI', '=', 'e.EXI_COD')
            ->where('x.CEX_CON', $cod)->orderBy('e.EXI_DET')
            ->get(['e.EXI_COD', DB::raw('LTRIM(RTRIM(e.EXI_DET)) as det'), 'x.CEX_TIP', 'x.CEX_PRE', 'x.CEX_OBS'])
            ->map(fn ($x) => [
                'cod'        => (int) $x->EXI_COD,
                'detalle'    => trim((string) $x->det),
                'tipo'       => trim((string) $x->CEX_TIP) ?: 'O',
                'presentada' => trim((string) $x->CEX_PRE) === 'S',
                'obs'        => trim((string) $x->CEX_OBS),
            ]);
        $catalogo = DB::table('exigencias')->orderBy('EXI_DET')->get(['EXI_COD', 'EXI_DET'])
            ->map(fn ($e) => ['cod' => (int) $e->EXI_COD, 'detalle' => trim((string) $e->EXI_DET)]);
        return response()->json(['asignadas' => $asignadas, 'catalogo' => $catalogo]);
    }

    /** @route POST /api/contratistas-externos/{cod}/exigencias  body: { ids: [exiCod,...] } */
    public function guardarExigencias(Request $request, int $cod): JsonResponse
    {
        $datos = $request->validate(['ids' => 'present|array', 'ids.*' => 'integer']);
        $ids = array_map('intval', $datos['ids']);

        DB::transaction(function () use ($cod, $ids) {
            $actuales = DB::table('contexi')->where('CEX_CON', $cod)->pluck('CEX_EXI')->map(fn ($v) => (int) $v)->all();
            // Quitar las que ya no están
            foreach (array_diff($actuales, $ids) as $quitar) {
                DB::table('contexi')->where('CEX_CON', $cod)->where('CEX_EXI', $quitar)->delete();
            }
            // Agregar las nuevas (default obligatoria, no presentada)
            foreach (array_diff($ids, $actuales) as $nueva) {
                DB::table('contexi')->insert(Registro::completar('contexi', [
                    'CEX_CON' => $cod, 'CEX_EXI' => $nueva, 'CEX_TIP' => 'O', 'CEX_PRE' => 'N', 'CEX_REL' => 0,
                ]));
            }
        });
        return response()->json(['ok' => true]);
    }

    // ── Empleados asociados (contemp) ──────────────────────────

    /** @route GET /api/contratistas-externos/{cod}/empleados */
    public function empleados(int $cod): JsonResponse
    {
        $hoy = now()->toDateString();

        // Empleados (por `unico`) ocupados en una obra sin finalizar (OBR_FCI vacía).
        // Se vincula por contemp.unico porque CEM_COD no es único (ver ObraController).
        $ocupados = DB::table('obras_emp as oe')->join('obras as o', 'o.OBR_COD', '=', 'oe.OEM_OBR')
            ->whereDate('o.OBR_FCI', '<=', '1900-01-01')
            ->pluck('oe.OEM_EMP')->map(fn ($v) => (int) $v)->unique()->flip();

        $rows = DB::table('contemp')->where('CEM_CON', $cod)->orderBy('CEM_NOM')
            ->get()->map(fn ($r) => [
                'unico'   => (int) $r->unico,
                'cod'     => (int) $r->CEM_COD,
                'nombre'  => trim((string) $r->CEM_NOM),
                'dni'     => trim((string) $r->CEM_DNI),
                'cuil'    => (string) (int) $r->CEM_CUI,
                'telefono' => trim((string) $r->CEM_TEL),
                'celular' => trim((string) $r->CEM_CEL),
                'venc_art' => $r->CEM_VART ? \Carbon\Carbon::parse($r->CEM_VART)->format('Y-m-d') : '',
                'art_vencida' => $r->CEM_VART ? \Carbon\Carbon::parse($r->CEM_VART)->lt($hoy) : true,
                'ocupado' => isset($ocupados[(int) $r->unico]),
                'obs'     => trim((string) $r->CEM_OBS),
            ]);
        return response()->json($rows);
    }

    /** @route POST /api/contratistas-externos/{cod}/empleados  body: { rows:[...] } */
    public function guardarEmpleados(Request $request, int $cod): JsonResponse
    {
        $request->validate(['rows' => 'present|array', 'rows.*.nombre' => 'nullable|string']);
        $rows = $request->input('rows');

        DB::transaction(function () use ($cod, $rows) {
            $enviadosUnico = collect($rows)->pluck('unico')->filter()->map(fn ($v) => (int) $v)->all();
            // Borrar los que ya no están
            DB::table('contemp')->where('CEM_CON', $cod)
                ->when(!empty($enviadosUnico), fn ($q) => $q->whereNotIn('unico', $enviadosUnico))->delete();

            $maxCod = (int) DB::table('contemp')->max('CEM_COD');
            foreach ($rows as $r) {
                $vals = [
                    'CEM_NOM' => mb_strtoupper(trim((string) ($r['nombre'] ?? ''))),
                    'CEM_DNI' => trim((string) ($r['dni'] ?? '')),
                    'CEM_CUI' => (int) preg_replace('/\D/', '', (string) ($r['cuil'] ?? 0)),
                    'CEM_TEL' => trim((string) ($r['telefono'] ?? '')),
                    'CEM_CEL' => trim((string) ($r['celular'] ?? '')),
                    'CEM_VART' => !empty($r['venc_art']) ? \Carbon\Carbon::parse($r['venc_art']) : '1900-01-01',
                ];
                if (!empty($r['unico'])) {
                    DB::table('contemp')->where('unico', (int) $r['unico'])->update($vals);
                } else {
                    DB::table('contemp')->insert(Registro::completar('contemp', array_merge($vals, [
                        'CEM_COD' => ++$maxCod, 'CEM_CON' => $cod,
                    ])));
                }
            }
        });
        return response()->json(['ok' => true, 'total' => count($rows)]);
    }

    /**
     * Importa empleados desde Excel a un contratista (contra_emplea_importar).
     * Solo da de ALTA los que no existan ya por CUIL (no actualiza los existentes).
     * @route POST /api/contratistas-externos/{cod}/empleados/importar  body: { rows:[{nombre,dni,cuil,telefono,celular}] }
     */
    public function importarEmpleados(Request $request, int $cod): JsonResponse
    {
        $request->validate(['rows' => 'present|array']);
        $rows = $request->input('rows');

        $insertados = 0; $omitidos = 0;
        DB::transaction(function () use ($cod, $rows, &$insertados, &$omitidos) {
            $maxCod = (int) DB::table('contemp')->max('CEM_COD');
            foreach ($rows as $r) {
                $cuit = (int) preg_replace('/\D/', '', (string) ($r['cuil'] ?? ''));
                // Si ya existe un empleado con ese CUIL, se omite (igual que el FoxPro)
                if ($cuit > 0 && DB::table('contemp')->where('CEM_CUI', $cuit)->exists()) {
                    $omitidos++;
                    continue;
                }
                DB::table('contemp')->insert(Registro::completar('contemp', [
                    'CEM_COD' => ++$maxCod,
                    'CEM_NOM' => mb_strtoupper(trim((string) ($r['nombre'] ?? ''))),
                    'CEM_DNI' => trim((string) ($r['dni'] ?? '')),
                    'CEM_CUI' => $cuit,
                    'CEM_TEL' => mb_strtoupper(trim((string) ($r['telefono'] ?? ''))),
                    'CEM_CEL' => mb_strtoupper(trim((string) ($r['celular'] ?? ''))),
                    'CEM_CON' => $cod,
                ]));
                $insertados++;
            }
        });

        return response()->json(['ok' => true, 'insertados' => $insertados, 'omitidos' => $omitidos]);
    }
}
