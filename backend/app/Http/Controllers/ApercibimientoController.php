<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ApercibimientoController — Apercibimientos: Agregar y Consultar
 * (apercibimiento_agregar.scx / apercibimiento_consultar.scx).
 *
 * Tabla per_apercibimientos (ape_cod=código de empleado, ape_nom, ape_fec,
 * ape_obs=texto, ape_usu). No tiene PK propia: un apercibimiento se identifica
 * por empleado + fecha.
 */
class ApercibimientoController extends Controller
{
    /** @route GET /api/apercibimientos/empleado/{emp} — datos del empleado y su empresa (para display e impresión). */
    public function empleado(int $emp): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $emp)->first(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_EMP']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }
        $e = DB::table('empresas')->where('EMP_COD', $p->PER_EMP)->first(['EMP_NOM', 'EMP_DOM', 'EMP_CUI']);

        return response()->json([
            'codigo' => (int) $p->PER_COD,
            'nombre' => trim((string) $p->PER_NOM),
            'cuit'   => trim((string) ($p->PER_CUI ?? '')),
            'empresa' => [
                'nombre'    => trim((string) ($e->EMP_NOM ?? '')),
                'domicilio' => trim((string) ($e->EMP_DOM ?? '')),
                'cuit'      => trim((string) ($e->EMP_CUI ?? '')),
            ],
        ]);
    }

    /** @route GET /api/apercibimientos/empleado/{emp}/lista — apercibimientos del empleado. */
    public function lista(int $emp): JsonResponse
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $rows = DB::table('per_apercibimientos')->where('ape_cod', $emp)->orderBy('ape_fec')->get()
            ->map(fn ($a0) => (function ($a) use ($fecha) {
                return [
                    'fecha'       => $fecha($a['ape_fec']),
                    'observacion' => trim((string) $a['ape_obs']),
                    'usuario'     => trim((string) $a['ape_usu']),
                ];
            })(array_change_key_case((array) $a0, CASE_LOWER)))->values();
        return response()->json($rows);
    }

    /**
     * @route GET /api/apercibimientos/grilla?emp= — todos los apercibimientos para el ABM unificado.
     * emp opcional: acota a un empleado (uso futuro desde la ficha del empleado).
     */
    public function grilla(Request $request): JsonResponse
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $q = DB::table('per_apercibimientos');
        if ($emp = (int) $request->input('emp', 0)) {
            $q->where('ape_cod', $emp);
        }
        $rows = $q->orderByDesc('ape_fec')->get()
            ->map(fn ($a0) => (function ($a) use ($fecha) {
                return [
                    'empleado'        => (int) $a['ape_cod'],
                    'empleado_nombre' => trim((string) $a['ape_nom']),
                    'fecha'           => $fecha($a['ape_fec']),
                    'observacion'     => trim((string) $a['ape_obs']),
                    'usuario'         => trim((string) $a['ape_usu']),
                ];
            })(array_change_key_case((array) $a0, CASE_LOWER)))->values();
        return response()->json($rows);
    }

    /** @route POST /api/apercibimientos — alta de un apercibimiento (CONFIRMAR). */
    public function agregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado' => 'required|integer|min:1',
            'fecha'    => 'required|date',
            'razon'    => 'required|string',
        ], [
            'fecha.required' => 'Debe ingresar la fecha del apercibimiento.',
            'razon.required' => 'Debe ingresar la razón del apercibimiento.',
        ]);

        if (substr((string) $d['fecha'], 0, 10) > now()->format('Y-m-d')) {
            return response()->json(['message' => 'No puede ingresar un apercibimiento a futuro.'], 422);
        }

        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM']);
        if (!$per) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 422);
        }
        if (!\App\Support\Empleado::activo((int) $d['empleado'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }

        DB::table('per_apercibimientos')->insert([
            'ape_cod' => (int) $d['empleado'],
            'ape_nom' => mb_substr(trim((string) $per->PER_NOM), 0, 50),
            'ape_fec' => substr((string) $d['fecha'], 0, 10),
            'ape_obs' => trim($d['razon']),
            'ape_usu' => mb_substr(strtoupper((string) (auth()->user()->DATO1 ?? 'RRHH.NET')), 0, 30),
        ]);

        return response()->json(['ok' => true], 201);
    }

    /** @route DELETE /api/apercibimientos — elimina un apercibimiento (empleado + fecha). */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado' => 'required|integer|min:1',
            'fecha'    => 'required|date',
        ]);
        $borrados = DB::table('per_apercibimientos')
            ->where('ape_cod', $d['empleado'])
            ->whereRaw('CONVERT(date, ape_fec) = ?', [substr((string) $d['fecha'], 0, 10)])
            ->delete();
        return response()->json(['ok' => true, 'borrados' => $borrados]);
    }
}
