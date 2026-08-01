<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VacacionesConsultaController — agrupa los módulos de gestión/consulta de Vacaciones:
 *
 *   • Acciones Varias  (vacaciones_acciones_varias.scx) — lista las vacaciones de UN empleado,
 *     permite modificar el período seleccionado, eliminarlo e imprimir el acuerdo.
 *   • Vacaciones Programadas (vacaciones_programadas.scx) — todas las vacaciones cuyo inicio o fin
 *     es de mañana en adelante, con los días que corresponden por antigüedad.
 *   • Planilla General (vacaciones_planilla.scx) — por empresa y año, todo el personal activo con
 *     días que corresponden, liquidados, gozados y los que faltan (sin liquidar / sin gozar).
 *
 * Los días que corresponden se calculan con la tabla de definición de vacaciones por antigüedad
 * (igual que el FoxPro), tomando como fecha de cómputo el 31/12 del año.
 *
 * VAC_NRO no es identity.
 */
class VacacionesConsultaController extends Controller
{
    // ── Acciones Varias ──────────────────────────────────────────────

    /** @route GET /api/vacaciones/acciones?cod= — vacaciones de un empleado. */
    public function acciones(Request $request): JsonResponse
    {
        $cod = (int) $request->validate(['cod' => 'required|integer'])['cod'];
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_COD', 'PER_NOM', 'PER_ING', 'PER_LEG']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 404);

        $corresponden = $this->corresponden($p->PER_ING, (int) now()->year);

        $filas = DB::table('vacaciones')->where('VAC_PER', $cod)->orderByDesc('VAC_FEC')->orderByDesc('VAC_FDE')
            ->get()->map(fn ($v) => $this->fila($v))->values();

        return response()->json([
            'cod' => $cod, 'nombre' => trim((string) $p->PER_NOM), 'legajo' => (int) $p->PER_LEG,
            'ingreso' => $this->fecha($p->PER_ING), 'corresponden' => $corresponden,
            'vacaciones' => $filas,
        ]);
    }

    /** @route POST /api/vacaciones/modificar — modifica un período (CONFIRMAR CAMBIOS). */
    public function modificar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'nro'           => 'required|integer',
            'anio'          => 'required|integer',
            'fechaPago'     => 'nullable|date',
            'fechaDesde'    => 'required|date',
            'fechaHasta'    => 'required|date',
            'dias'          => 'required|integer|min:0',
            'presenta'      => 'nullable|date',
            'liquidada'     => 'nullable|boolean',
            'gozada'        => 'nullable|boolean',
            'cantLiquidada' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string',
            'vt'            => 'nullable|boolean',
        ]);

        $v = DB::table('vacaciones')->where('VAC_NRO', (int) $d['nro'])->first(['VAC_PER']);
        if (!$v) return response()->json(['message' => 'El período de vacaciones no existe.'], 404);

        $usuario = trim((string) (optional($request->user())->name ?? 'RRHH.NET'));
        DB::transaction(function () use ($d, $v, $usuario) {
            DB::table('vacaciones')->where('VAC_NRO', (int) $d['nro'])->update([
                'VAC_ANO' => (int) $d['anio'],
                'VAC_FEC' => !empty($d['fechaPago']) ? \Carbon\Carbon::parse($d['fechaPago'])->format('Y-m-d') : '1900-01-01',
                'VAC_FDE' => \Carbon\Carbon::parse($d['fechaDesde'])->format('Y-m-d'),
                'VAC_FHA' => \Carbon\Carbon::parse($d['fechaHasta'])->format('Y-m-d'),
                'VAC_DIA' => (int) $d['dias'],
                'VAC_PRE' => !empty($d['presenta']) ? \Carbon\Carbon::parse($d['presenta'])->format('Y-m-d') : '1900-01-01',
                'VAC_LIQ' => ($d['liquidada'] ?? false) ? 1 : 0,
                'VAC_GOZ' => ($d['gozada'] ?? false) ? 1 : 0,
                'VAC_LCAN' => (int) ($d['cantLiquidada'] ?? 0),
                'VAC_OBS' => substr(trim((string) ($d['observaciones'] ?? '')), 0, 200),
                'VAC_VTR' => ($d['vt'] ?? false) ? 1 : 0,
            ]);

            $obs = trim((string) ($d['observaciones'] ?? ''));
            if ($obs !== '') {
                DB::table('per_hist')->insert(Registro::completar('per_hist', [
                    'hla_cod' => (int) $v->VAC_PER, 'hla_fec' => now(), 'hla_usu' => mb_strtoupper($usuario),
                    'hla_ter' => 'RRHH.NET', 'hla_cam' => 'Vacaciones: ' . $obs,
                ]));
            }
        });

        return response()->json(['message' => 'Modificaciones guardadas correctamente.']);
    }

    /** @route POST /api/vacaciones/eliminar — borra los períodos seleccionados. */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['nros' => 'required|array|min:1', 'nros.*' => 'integer']);
        $n = DB::table('vacaciones')->whereIn('VAC_NRO', $d['nros'])->delete();
        return response()->json(['message' => "Vacaciones borradas correctamente ($n).", 'eliminados' => $n]);
    }

    // ── Vacaciones Programadas ───────────────────────────────────────

    /** @route GET /api/vacaciones/programadas — vacaciones de mañana en adelante. */
    public function programadas(Request $request): JsonResponse
    {
        $manana = now()->addDay()->format('Y-m-d');
        $defs = $this->definiciones();
        $ingresos = []; $legajos = [];

        $filas = DB::table('vacaciones')
            ->where(fn ($q) => $q->whereDate('VAC_FDE', '>=', $manana)->orWhereDate('VAC_FHA', '>=', $manana))
            ->orderByDesc('VAC_FDE')->orderBy('VAC_PER')->orderBy('VAC_NRO')->get()
            ->map(function ($v) use ($defs, &$ingresos, &$legajos) {
                $per = (int) $v->VAC_PER;
                if (!array_key_exists($per, $ingresos)) {
                    $p = DB::table('personal')->where('PER_COD', $per)->first(['PER_ING', 'PER_LEG']);
                    $ingresos[$per] = $p->PER_ING ?? null;
                    $legajos[$per] = (int) ($p->PER_LEG ?? 0);
                }
                $f = $this->fila($v);
                $f['legajo'] = $legajos[$per];
                $f['corresponden'] = $this->correspondenDefs($defs, $ingresos[$per], (int) now()->year - 1);
                return $f;
            })->values();

        return response()->json(['vacaciones' => $filas]);
    }

    // ── Planilla General ─────────────────────────────────────────────

    /** @route GET /api/vacaciones/planilla?emp=&anio= — planilla por empresa y año. */
    public function planilla(Request $request): JsonResponse
    {
        $d = $request->validate(['emp' => 'required|integer', 'anio' => 'required|integer']);
        $emp = (int) $d['emp']; $anio = (int) $d['anio'];
        $defs = $this->definiciones();

        $personal = DB::table('personal')->where('PER_EMP', $emp)->where('PER_AOP', 'A')
            ->orderBy('PER_SED')->orderBy('PER_NOM')->get(['PER_COD', 'PER_NOM', 'PER_LEG', 'PER_ING', 'PER_SED']);

        $filas = $personal->map(function ($p) use ($defs, $anio) {
            $cod = (int) $p->PER_COD;
            $corresponden = $this->correspondenDefs($defs, $p->PER_ING, $anio);
            $liquidadas = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_ANO', $anio)->where('VAC_LIQ', 1)->sum('VAC_LCAN'));
            $gozadas = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_ANO', $anio)->where('VAC_GOZ', 1)->sum('VAC_DIA'));
            return [
                'cod' => $cod, 'nombre' => trim((string) $p->PER_NOM), 'legajo' => (int) $p->PER_LEG,
                'ingreso' => $this->fecha($p->PER_ING), 'puesto' => trim((string) $p->PER_SED),
                'corresponden' => $corresponden, 'liquidadas' => $liquidadas, 'gozadas' => $gozadas,
                'noLiq' => $corresponden - $liquidadas, 'noGoz' => $corresponden - $gozadas,
            ];
        })->values();

        return response()->json(['anio' => $anio, 'empresa' => $emp, 'personal' => $filas]);
    }

    // ── Informe General ──────────────────────────────────────────────

    /** @route GET /api/vacaciones/informe?desde=&hasta= — vacaciones cuyo inicio o fin cae en el rango. */
    public function informe(Request $request): JsonResponse
    {
        $d = $request->validate(['desde' => 'required|date', 'hasta' => 'required|date']);
        $desde = \Carbon\Carbon::parse($d['desde'])->format('Y-m-d');
        $hasta = \Carbon\Carbon::parse($d['hasta'])->format('Y-m-d');
        $defs = $this->definiciones();
        $ingresos = []; $legajos = [];

        $filas = DB::table('vacaciones')
            ->where(function ($q) use ($desde, $hasta) {
                $q->where(fn ($w) => $w->whereDate('VAC_FDE', '>=', $desde)->whereDate('VAC_FDE', '<=', $hasta))
                  ->orWhere(fn ($w) => $w->whereDate('VAC_FHA', '>=', $desde)->whereDate('VAC_FHA', '<=', $hasta));
            })
            ->orderByDesc('VAC_FDE')->orderBy('VAC_PER')->orderBy('VAC_NRO')->get()
            ->map(function ($v) use ($defs, &$ingresos, &$legajos) {
                $per = (int) $v->VAC_PER;
                if (!array_key_exists($per, $ingresos)) {
                    $p = DB::table('personal')->where('PER_COD', $per)->first(['PER_ING', 'PER_LEG']);
                    $ingresos[$per] = $p->PER_ING ?? null;
                    $legajos[$per] = (int) ($p->PER_LEG ?? 0);
                }
                $f = $this->fila($v);
                $f['legajo'] = $legajos[$per];
                $f['corresponden'] = $this->correspondenDefs($defs, $ingresos[$per], (int) now()->year - 1);
                return $f;
            })->values();

        return response()->json(['vacaciones' => $filas]);
    }

    // ── Vacaciones Pendientes ────────────────────────────────────────

    /** @route GET /api/vacaciones/pendientes?anio1=&anio2=&conPasivos=&cod= */
    public function pendientes(Request $request): JsonResponse
    {
        $d = $request->validate([
            'anio1' => 'required|integer', 'anio2' => 'required|integer',
            'conPasivos' => 'nullable|boolean', 'cod' => 'nullable|integer',
        ]);
        $a1 = (int) $d['anio1']; $a2 = (int) $d['anio2'];
        $conPasivos = (bool) ($d['conPasivos'] ?? false);
        $cod = (int) ($d['cod'] ?? 0);
        $defs = $this->definiciones();

        $q = DB::table('personal')->where('PER_CAT', '<>', 55);
        if (!$conPasivos) $q->where('PER_AOP', 'A');
        if ($cod > 0) $q->where('PER_COD', $cod);
        $personal = $q->orderBy('PER_NOM')->get(['PER_COD', 'PER_LEG', 'PER_NOM', 'PER_ING', 'PER_AOP']);

        $filas = []; $total = 0;
        foreach ($personal as $p) {
            $cper = (int) $p->PER_COD;
            $ingAnio = $p->PER_ING ? (int) \Carbon\Carbon::parse($p->PER_ING)->year : 9999;
            for ($anio = max($a1, 2023); $anio <= $a2; $anio++) {
                if ($anio < $ingAnio) continue;
                $corresponden = $this->correspondenDefs($defs, $p->PER_ING, $anio);
                $tomados = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cper)->where('VAC_ANO', $anio)->sum('VAC_DIA'));
                $liquidadas = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cper)->where('VAC_ANO', $anio - 1)->sum('VAC_LCAN'));
                $pendientes = $corresponden - $tomados;
                if ($pendientes <= 0) continue;
                $filas[] = [
                    'cod' => $cper, 'legajo' => (int) $p->PER_LEG, 'nombre' => trim((string) $p->PER_NOM),
                    'ingreso' => $this->fecha($p->PER_ING), 'anio' => $anio, 'dias' => $corresponden,
                    'tomados' => $tomados, 'liquidadas' => $liquidadas, 'pendientes' => $pendientes,
                    'estado' => trim((string) $p->PER_AOP),
                ];
                $total += $pendientes;
            }
        }
        usort($filas, fn ($a, $b) => [$a['nombre'], $a['anio']] <=> [$b['nombre'], $b['anio']]);

        return response()->json(['pendientes' => $filas, 'totalPendiente' => $total]);
    }

    // ── Vacaciones Definición (ABM de la tabla VACADIAS) ─────────────

    /** @route GET /api/vacaciones/definicion */
    public function definicionList(): JsonResponse
    {
        $filas = DB::table('vacadias')->orderBy('vre_dias')->get()->map(fn ($r) => [
            'anioIni' => (int) round((float) $r->vre_aini), 'mesIni' => (int) round((float) $r->vre_mini),
            'anioFin' => (int) round((float) $r->vre_afin), 'mesFin' => (int) round((float) $r->vre_mfin),
            'dias' => (int) round((float) $r->vre_dias),
        ])->values();
        return response()->json(['definiciones' => $filas]);
    }

    /** @route POST /api/vacaciones/definicion — agrega o actualiza una relación de antigüedad. */
    public function definicionAgregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'anioIni' => 'required|integer|min:0', 'mesIni' => 'required|integer|min:0',
            'anioFin' => 'required|integer|min:0', 'mesFin' => 'required|integer|min:0',
            'dias' => 'required|integer|min:1',
        ]);
        $match = ['vre_aini' => $d['anioIni'], 'vre_mini' => $d['mesIni'], 'vre_afin' => $d['anioFin'], 'vre_mfin' => $d['mesFin']];
        $existe = DB::table('vacadias')->where($match)->exists();
        if ($existe) {
            DB::table('vacadias')->where($match)->update(['vre_dias' => $d['dias']]);
        } else {
            DB::table('vacadias')->insert(Registro::completar('vacadias', $match + ['vre_dias' => $d['dias']]));
        }
        return response()->json(['message' => $existe ? 'Relación actualizada.' : 'Relación agregada.']);
    }

    /** @route POST /api/vacaciones/definicion/eliminar — borra relaciones seleccionadas. */
    public function definicionEliminar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'filas' => 'required|array|min:1',
            'filas.*.anioIni' => 'required|integer', 'filas.*.mesIni' => 'required|integer',
            'filas.*.anioFin' => 'required|integer', 'filas.*.mesFin' => 'required|integer',
        ]);
        $n = 0;
        foreach ($d['filas'] as $f) {
            $n += DB::table('vacadias')->where([
                'vre_aini' => $f['anioIni'], 'vre_mini' => $f['mesIni'],
                'vre_afin' => $f['anioFin'], 'vre_mfin' => $f['mesFin'],
            ])->delete();
        }
        return response()->json(['message' => "Relaciones eliminadas ($n).", 'eliminados' => $n]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /** Arma una fila estándar de un período de vacaciones. */
    private function fila($v): array
    {
        return [
            'nro' => (int) $v->VAC_NRO, 'cod' => (int) $v->VAC_PER, 'nombre' => trim((string) $v->VAC_NOM),
            'anio' => (int) $v->VAC_ANO, 'fechaPago' => $this->iso($v->VAC_FEC),
            'fechaDesde' => $this->iso($v->VAC_FDE), 'fechaHasta' => $this->iso($v->VAC_FHA),
            'dias' => (int) round((float) $v->VAC_DIA), 'presenta' => $this->iso($v->VAC_PRE),
            'liquidada' => (bool) $v->VAC_LIQ, 'gozada' => (bool) $v->VAC_GOZ,
            'cantLiquidada' => (int) round((float) $v->VAC_LCAN), 'observaciones' => trim((string) $v->VAC_OBS),
            'vt' => (bool) $v->VAC_VTR,
        ];
    }

    /** Definiciones de días por antigüedad (tabla VACADIAS). */
    private function definiciones(): array
    {
        return DB::table('vacadias')->get()->map(fn ($r) => [
            'ini' => (float) $r->vre_aini * 365 + (float) $r->vre_mini * 30,
            'fin' => (float) $r->vre_afin * 365 + (float) $r->vre_mfin * 30,
            'dias' => (int) round((float) $r->vre_dias),
        ])->all();
    }

    /** Días que corresponden por antigüedad al 31/12 del año, según las definiciones. */
    private function correspondenDefs(array $defs, $ingreso, int $anio): int
    {
        if (!$ingreso) return 0;
        $dias = \Carbon\Carbon::parse($ingreso)->diffInDays(\Carbon\Carbon::create($anio, 12, 31));
        $r = 0;
        foreach ($defs as $def) {
            if ($dias >= $def['ini'] && $dias <= $def['fin'] && $def['dias'] > $r) $r = $def['dias'];
        }
        return $r;
    }

    private function corresponden($ingreso, int $anio): int
    {
        return $this->correspondenDefs($this->definiciones(), $ingreso, $anio);
    }

    private function iso($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('Y-m-d');
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y');
    }
}
