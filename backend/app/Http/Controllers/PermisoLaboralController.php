<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PermisoLaboralController — Permisos Laborales (permisos_laborales.scx).
 *
 * Los permisos viven en la base de gestión (conexión 'gestion' = sqlSILCAR/sqlLOGIST),
 * tabla PERMISOS_LABORALES. Este módulo lista los permisos pendientes de procesar
 * (PLA_EST=0) y el histórico completo, y permite confirmar el procesado de los
 * permisos marcados (PLA_EST=1 + fecha/responsable de confirmación).
 */
class PermisoLaboralController extends Controller
{
    /** @route GET /api/permisos-laborales — pendientes (PLA_EST=0) + histórico. */
    public function index(): JsonResponse
    {
        $rows = DB::connection('gestion')->table('PERMISOS_LABORALES')->orderByDesc('pla_cod')->get();
        $hist = $rows->map(fn ($r) => $this->mapear($r))->values();
        return response()->json([
            'pendientes' => $hist->where('procesado', false)->values(),
            'historico'  => $hist,
        ]);
    }

    /** @route POST /api/permisos-laborales/confirmar — confirma los permisos marcados. */
    public function confirmar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigos'   => 'required|array|min:1',
            'codigos.*' => 'integer',
        ], ['codigos.required' => 'Debe seleccionar el permiso a confirmar.', 'codigos.min' => 'Debe seleccionar el permiso a confirmar.']);

        $usuario = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 50);
        $ahora   = now()->format('Y-m-d H:i:s');
        $codigos = array_map('intval', $d['codigos']);

        $n = DB::connection('gestion')->table('PERMISOS_LABORALES')
            ->whereIn('pla_cod', $codigos)
            ->update(['PLA_EST' => 1, 'pla_fconfirma' => $ahora, 'pla_rconfirma' => $usuario]);

        return response()->json(['ok' => true, 'confirmados' => $n]);
    }

    // ═══════════════ Portal del Encargado ═══════════════
    // El encargado (usuario con LEGAJO = PER_COD de su empleado) carga permisos
    // para su personal a cargo (per_sub). Los permisos quedan pendientes (PLA_EST=0)
    // hasta que RRHH los procesa desde el módulo de gestión.

    /** @route GET /api/mis-permisos/equipo — empleados a cargo del usuario logueado. */
    public function miEquipo(Request $request): JsonResponse
    {
        $legajo = (int) ($request->user()->LEGAJO ?? 0);
        if ($legajo <= 0) {
            return response()->json(['sin_legajo' => true, 'encargado' => null, 'empleados' => []]);
        }

        $jefe = DB::table('personal')->where('PER_COD', $legajo)
            ->first(['PER_COD', 'PER_NOM', 'PER_LEG']);

        $subs = DB::table('per_sub')->where('PSU_COD', $legajo)
            ->pluck('PSU_SUB')->map(fn ($v) => (int) $v)->all();

        $empleados = empty($subs) ? collect() : DB::table('personal')
            ->whereIn('PER_COD', $subs)
            ->where('PER_AOP', 'A')
            ->orderBy('PER_NOM')
            ->get(['PER_COD as cod', DB::raw('LTRIM(RTRIM(PER_NOM)) as nombre'),
                   'PER_LEG as legajo', DB::raw('LTRIM(RTRIM(PER_SED)) as sector')]);

        return response()->json([
            'sin_legajo' => false,
            'encargado'  => $jefe ? [
                'cod'    => (int) $jefe->PER_COD,
                'nombre' => trim((string) $jefe->PER_NOM),
                'legajo' => trim((string) $jefe->PER_LEG),
            ] : null,
            'empleados'  => $empleados->values(),
        ]);
    }

    /** @route GET /api/mis-permisos/tipos — catálogo de tipos de permiso (licencias). */
    public function tipos(): JsonResponse
    {
        $filas = DB::table('licencias')->orderBy('lic_det')->get(['lic_cod', 'lic_det'])
            ->map(fn ($l) => ['cod' => (int) $l->lic_cod, 'detalle' => trim((string) $l->lic_det)])->values();
        return response()->json(['tipos' => $filas]);
    }

    /** @route GET /api/mis-permisos — permisos cargados por el usuario logueado. */
    public function mias(Request $request): JsonResponse
    {
        $cod = (int) ($request->user()->CODIGO ?? 0);
        $rows = DB::connection('gestion')->table('PERMISOS_LABORALES')
            ->where('pla_respocod', $cod)->orderByDesc('pla_cod')->get();
        return response()->json(['permisos' => $rows->map(fn ($r) => $this->mapear($r))->values()]);
    }

    /**
     * @route GET /api/permisos-laborales/pendientes/{empleado}
     * Permisos pendientes (PLA_EST=0) de un empleado. Se usa en Faltas/Vacaciones:
     * al elegir al empleado, si tiene permisos pendientes se ofrece "usarlos".
     */
    public function pendientesEmpleado(int $empleado): JsonResponse
    {
        $rows = DB::connection('gestion')->table('PERMISOS_LABORALES')
            ->where('pla_emp', $empleado)->where('pla_est', 0)
            ->orderBy('pla_cod')->get();
        return response()->json(['permisos' => $rows->map(fn ($r) => $this->mapear($r))->values()]);
    }

    /** @route POST /api/mis-permisos — el encargado solicita un permiso para su empleado. */
    public function solicitar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado'      => 'required|integer',
            'tipo'          => 'required|integer',
            'fecha_desde'   => 'required|date',
            'fecha_hasta'   => 'required|date',
            'hora_inicio'   => 'nullable|string',
            'hora_fin'      => 'nullable|string',
            'observaciones' => 'nullable|string',
        ], [
            'empleado.required'    => 'Falta el empleado que solicita el permiso.',
            'tipo.required'        => 'Debe seleccionar el tipo de permiso.',
            'fecha_desde.required' => 'Debe indicar la fecha del permiso.',
        ]);

        $u      = $request->user();
        $legajo = (int) ($u->LEGAJO ?? 0);
        if ($legajo <= 0) {
            abort(403, 'Su usuario no tiene un legajo de empleado asociado.');
        }

        // Seguridad: el empleado debe ser personal a cargo del encargado.
        $aCargo = DB::table('per_sub')
            ->where('PSU_COD', $legajo)->where('PSU_SUB', $d['empleado'])->exists();
        if (! $aCargo) {
            abort(403, 'El empleado no pertenece a su personal a cargo.');
        }

        $emp = DB::table('personal')->where('PER_COD', $d['empleado'])
            ->first(['PER_NOM', 'PER_SED']);
        if (! $emp) {
            abort(422, 'Empleado inexistente.');
        }

        $tipoDet = DB::table('licencias')->where('lic_cod', $d['tipo'])->value('lic_det');
        if ($tipoDet === null) {
            abort(422, 'Tipo de permiso inexistente.');
        }

        $desde = substr((string) $d['fecha_desde'], 0, 10);
        $hasta = substr((string) $d['fecha_hasta'], 0, 10);
        if ($hasta < $desde) {
            $hasta = $desde;
        }
        if (strtotime($desde) < strtotime('-365 days')) {
            abort(422, 'La fecha del permiso es demasiado antigua.');
        }

        $hIni = $this->horaAInt($d['hora_inicio'] ?? '');
        $hFin = $this->horaAInt($d['hora_fin'] ?? '');
        if ($hFin > 0 && $hFin < $hIni) {
            $hFin = $hIni;
        }

        $nombreResp = mb_substr(trim((string) ($u->NOMBRE ?? '')), 0, 40);

        $g = DB::connection('gestion');
        $nro = $g->transaction(function () use ($g, $u, $d, $emp, $tipoDet, $desde, $hasta, $hIni, $hFin, $nombreResp) {
            $nuevo = (int) $g->table('PERMISOS_LABORALES')->max('pla_cod') + 1;
            $g->table('PERMISOS_LABORALES')->insert([
                'pla_cod'          => $nuevo,
                'pla_fec_solicitud' => now()->format('Y-m-d'),
                'pla_responsable'  => $nombreResp,
                'pla_respocod'     => (int) $u->CODIGO,
                'pla_emp'          => (int) $d['empleado'],
                'pla_emd'          => mb_substr(trim((string) $emp->PER_NOM), 0, 30),
                'pla_sector'       => mb_substr(trim((string) $emp->PER_SED), 0, 80),
                'pla_fec_permiso'  => $desde,
                'pla_fec_hasta'    => $hasta,
                'pla_hora_inicio'  => $hIni,
                'pla_hora_fin'     => $hFin,
                'pla_falta'        => mb_substr(trim((string) $tipoDet), 0, 50),
                'pla_obs'          => mb_strtoupper(trim((string) ($d['observaciones'] ?? ''))),
                'pla_est'          => 0,
                'pla_fconfirma'    => '1900-01-01',
                'pla_rconfirma'    => '',
            ]);
            return $nuevo;
        });

        return response()->json(['ok' => true, 'nro' => $nro]);
    }

    /** Convierte "HH:MM" (o "830") a entero HHMM. "08:30" → 830, "17:05" → 1705. */
    private function horaAInt(string $v): int
    {
        $s = preg_replace('/\D/', '', $v);
        return $s === '' ? 0 : (int) $s;
    }

    private function mapear(object $r): array
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $fechaHora = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 16) : '';
        $hora  = function ($v) {
            $s = preg_replace('/\D/', '', (string) $v);
            if ($s === '' || (int) $s === 0) {
                return '';
            }
            $s = str_pad($s, 4, '0', STR_PAD_LEFT);
            return substr($s, 0, 2) . ':' . substr($s, 2, 2);
        };
        return [
            'cod'           => (int) $r->pla_cod,
            'fecha_desde'   => $fecha($r->pla_fec_permiso),
            'fecha_hasta'   => $fecha($r->pla_fec_hasta),
            'hora_inicio'   => $hora($r->pla_hora_inicio),
            'hora_fin'      => $hora($r->pla_hora_fin),
            'empleado'      => trim((string) $r->pla_emd),
            'sector'        => trim((string) $r->pla_sector),
            'fecha_carga'   => $fecha($r->pla_fec_solicitud),
            'falta'         => trim((string) $r->pla_falta),
            'observaciones' => trim((string) ($r->pla_obs ?? '')),
            'responsable'   => trim((string) $r->pla_responsable),
            'fecha_confirma' => $fechaHora($r->pla_fconfirma ?? null),
            'resp_confirma' => trim((string) ($r->pla_rconfirma ?? '')),
            'procesado'     => (bool) $r->pla_est,
        ];
    }
}
