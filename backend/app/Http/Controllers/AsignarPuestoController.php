<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AsignarPuestoController — Asignar Puesto de Trabajo a un empleado.
 *
 * Réplica de puestos_asignar.scx (FoxPro RRHHWINSQL). La relación empleado↔puesto
 * se guarda en `puestoempleado` y se vincula por el **CUIL** (PEM_CUIL = personal.PER_CUI),
 * no por el código de empleado. ASIGNAR agrega un registro activo con la fecha de hoy;
 * DAR BAJA elimina la asignación de ese puesto para el empleado.
 */
class AsignarPuestoController extends Controller
{
    /** @route GET /api/asignar-puesto/empleado/{cod} — datos del empleado + puestos asignados. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_COD', 'PER_NOM', 'PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        $cuil = trim((string) $p->PER_CUI);
        return response()->json([
            'codigo'    => (int) $p->PER_COD,
            'nombre'    => trim((string) $p->PER_NOM),
            'cuil'      => $cuil,
            'asignados' => $this->asignados($cuil),
        ]);
    }

    /** @route POST /api/asignar-puesto — asigna un puesto al empleado (a la fecha). */
    public function asignar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigo'               => 'required|integer',
            'puesto'               => 'required|string|max:15',
            'inhabilitar_actuales' => 'sometimes|boolean',
        ]);
        $p = DB::table('personal')->where('PER_COD', (int) $d['codigo'])->first(['PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        $cuil = trim((string) $p->PER_CUI);
        $pue = trim($d['puesto']);

        $yaActivo = DB::table('puestoempleado')
            ->whereRaw('UPPER(RTRIM(PEM_CUIL)) = ?', [mb_strtoupper($cuil)])
            ->whereRaw('UPPER(RTRIM(PEM_PUE)) = ?', [mb_strtoupper($pue)])
            ->where('PEM_ACT', 1)->exists();
        if ($yaActivo) {
            return response()->json(['message' => 'Ese puesto ya está asignado a este empleado.'], 422);
        }

        // Si el usuario lo pidió, pasar TODOS los puestos actuales del empleado a inactivo
        // antes de agregar el nuevo (que siempre entra activo).
        if (!empty($d['inhabilitar_actuales'])) {
            DB::table('puestoempleado')
                ->whereRaw('UPPER(RTRIM(PEM_CUIL)) = ?', [mb_strtoupper($cuil)])
                ->where('PEM_ACT', 1)
                ->update(['PEM_ACT' => 0, 'PEM_FHAS' => Carbon::today()]);
        }

        DB::table('puestoempleado')->insert(Registro::completar('puestoempleado', [
            'PEM_CUIL' => $cuil,
            'PEM_PUE'  => $pue,
            'PEM_FDES' => Carbon::today(),
            'PEM_ACT'  => 1,
        ]));
        return response()->json(['ok' => true]);
    }

    /** @route POST /api/asignar-puesto/baja — da de baja un puesto del empleado. */
    public function darBaja(Request $request): JsonResponse
    {
        $d = $request->validate(['codigo' => 'required|integer', 'puesto' => 'required|string|max:15']);
        $p = DB::table('personal')->where('PER_COD', (int) $d['codigo'])->first(['PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        $cuil = mb_strtoupper(trim((string) $p->PER_CUI));
        $pue = mb_strtoupper(trim($d['puesto']));

        $borrados = DB::table('puestoempleado')
            ->whereRaw('UPPER(RTRIM(PEM_CUIL)) = ?', [$cuil])
            ->whereRaw('UPPER(RTRIM(PEM_PUE)) = ?', [$pue])->delete();
        if (!$borrados) {
            return response()->json(['message' => 'El puesto que pretende dar de baja no está asignado a este empleado.'], 422);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * @route POST /api/asignar-puesto/estado — activa o da de baja un puesto del empleado
     * cambiando PEM_ACT (sin borrar el registro). Al dar de baja se sella PEM_FHAS con hoy;
     * al reactivar se limpia (1900-01-01, sin nulos).
     */
    public function estado(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigo' => 'required|integer',
            'puesto' => 'required|string|max:15',
            'activo' => 'required|boolean',
        ]);
        $p = DB::table('personal')->where('PER_COD', (int) $d['codigo'])->first(['PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        $cuil = mb_strtoupper(trim((string) $p->PER_CUI));
        $pue = mb_strtoupper(trim($d['puesto']));
        $activo = $d['activo'] ? 1 : 0;

        $n = DB::table('puestoempleado')
            ->whereRaw('UPPER(RTRIM(PEM_CUIL)) = ?', [$cuil])
            ->whereRaw('UPPER(RTRIM(PEM_PUE)) = ?', [$pue])
            ->update([
                'PEM_ACT'  => $activo,
                'PEM_FHAS' => $activo ? '1900-01-01' : Carbon::today(),
            ]);
        if (!$n) {
            return response()->json(['message' => 'El puesto no está asignado a este empleado.'], 422);
        }
        return response()->json(['ok' => true]);
    }

    /** Puestos activos asignados a un CUIL, con su descripción y fecha de alta. */
    private function asignados(string $cuil): array
    {
        return DB::table('puestoempleado as pe')
            ->leftJoin('puestos as p', DB::raw('UPPER(RTRIM(p.PUE_COD))'), '=', DB::raw('UPPER(RTRIM(pe.PEM_PUE))'))
            ->whereRaw('UPPER(RTRIM(pe.PEM_CUIL)) = ?', [mb_strtoupper($cuil)])
            ->where('pe.PEM_ACT', 1)
            ->orderByDesc('pe.PEM_FDES')
            ->get(['pe.PEM_PUE', 'pe.PEM_FDES', 'p.PUE_DES'])
            ->map(fn ($r) => [
                'puesto'     => trim((string) $r->PEM_PUE),
                'puesto_des' => trim((string) ($r->PUE_DES ?? '')),
                'desde'      => $r->PEM_FDES && Carbon::parse($r->PEM_FDES)->year > 1900 ? Carbon::parse($r->PEM_FDES)->format('d/m/Y') : '',
            ])->values()->all();
    }
}
