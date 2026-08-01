<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VacacionesController — Vacaciones / Agregar (vacaciones_agregar.scx).
 *
 * Alta de un período de vacaciones de un empleado. Calcula los días que le CORRESPONDEN por antigüedad
 * (LCT art. 150), cuántos lleva TOMADOS y LIQUIDADOS del año, y muestra las vacaciones ya cargadas de ese
 * año (para evitar superposiciones). Al confirmar inserta en VACACIONES y, si hay observación, registra en
 * el historial del legajo (per_hist). Devuelve los datos para emitir la notificación/solicitud (PDF).
 *
 * VAC_NRO no es identity: el número nuevo es max(VAC_NRO)+1.
 */
class VacacionesController extends Controller
{
    private const MESES = [1 => 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

    /** @route GET /api/vacaciones/empleado?cod=&anio= */
    public function empleado(Request $request): JsonResponse
    {
        $cod = (int) $request->validate(['cod' => 'required|integer'])['cod'];
        $anio = (int) ($request->input('anio') ?: (now()->month > 9 ? now()->year : now()->year - 1));

        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_COD', 'PER_NOM', 'PER_ING', 'PER_NDO', 'PER_TDO', 'PER_LEG', 'PER_CUI', 'PER_EMP']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 404);

        $emp = DB::table('empresas')->where('EMP_COD', (int) $p->PER_EMP)->first(['EMP_NOM', 'EMP_DOM', 'EMP_CUI']);

        $corresponden = $this->corresponden($p->PER_ING, $anio);
        $tomados = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_ANO', $anio)->sum('VAC_DIA'));
        $liquidadas = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_ANO', $anio)->sum('VAC_LCAN'));

        // Vacaciones del año (por fecha desde) — para el recuadro de análisis.
        $periodos = []; $meses = [];
        foreach (DB::table('vacaciones')->where('VAC_PER', $cod)->whereRaw('YEAR(VAC_FDE) = ?', [$anio])->orderBy('VAC_FDE')->get(['VAC_FDE', 'VAC_FHA']) as $v) {
            $fde = \Carbon\Carbon::parse($v->VAC_FDE); $fha = \Carbon\Carbon::parse($v->VAC_FHA);
            $periodos[] = $fde->format('d/m/Y') . ' al ' . $fha->format('d/m/Y');
            $meses[(int) $fde->month] = self::MESES[(int) $fde->month];
        }
        ksort($meses);

        return response()->json([
            'cod' => $cod, 'nombre' => trim((string) $p->PER_NOM), 'ingreso' => $this->fecha($p->PER_ING),
            'tdoc' => trim((string) $p->PER_TDO), 'ndoc' => trim((string) $p->PER_NDO), 'legajo' => (int) $p->PER_LEG,
            'cuil' => trim((string) $p->PER_CUI),
            'empresa' => ['nombre' => trim((string) ($emp->EMP_NOM ?? '')), 'domicilio' => trim((string) ($emp->EMP_DOM ?? '')), 'cuit' => trim((string) ($emp->EMP_CUI ?? ''))],
            'anio' => $anio, 'corresponden' => $corresponden, 'tomados' => $tomados, 'liquidadas' => $liquidadas,
            'periodos' => array_values($periodos), 'meses' => array_values($meses),
        ]);
    }

    /** @route POST /api/vacaciones */
    public function agregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cod'            => 'required|integer',
            'anio'           => 'required|integer',
            'fechaDesde'     => 'required|date',
            'fechaHasta'     => 'required|date',
            'dias'           => 'required|integer|min:0',
            'fechaPago'      => 'nullable|date',
            'presenta'       => 'nullable|date',
            'liquidada'      => 'nullable|boolean',
            'gozada'         => 'nullable|boolean',
            'cantLiquidada'  => 'nullable|integer|min:0',
            'observaciones'  => 'nullable|string',
            'vt'             => 'nullable|boolean',
            'forzar'         => 'nullable|boolean',
            'permiso_cod'    => 'nullable|integer',  // permiso laboral que se está "usando"
        ]);

        $cod = (int) $d['cod']; $anio = (int) $d['anio'];
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_ING']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 422);
        if (!\App\Support\Empleado::activo($cod)) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar vacaciones.'], 422);
        }

        // Validaciones de tope por antigüedad
        $corresponden = $this->corresponden($p->PER_ING, $anio);
        $tomados = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_ANO', $anio)->sum('VAC_DIA'));
        $liquidadas = (int) round((float) DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_ANO', $anio)->sum('VAC_LCAN'));
        if ((int) $d['dias'] > $corresponden - $tomados) {
            return response()->json(['message' => "No puede tomarse {$d['dias']} días de vacaciones: solo le corresponden " . ($corresponden - $tomados) . " días."], 422);
        }
        $cantLiq = (int) ($d['cantLiquidada'] ?? 0);
        if ($cantLiq > $corresponden - $liquidadas) {
            return response()->json(['message' => "No puede liquidar $cantLiq días: solo le corresponden " . ($corresponden - $liquidadas) . " días."], 422);
        }

        // Aviso: otros empleados en vacaciones en el período (no bloquea si forzar=true)
        if (!($d['forzar'] ?? false)) {
            $solap = DB::table('vacaciones')
                ->where(function ($q) use ($d) {
                    $q->where(fn ($w) => $w->whereDate('VAC_FDE', '>=', $d['fechaDesde'])->whereDate('VAC_FDE', '<=', $d['fechaHasta']))
                      ->orWhere(fn ($w) => $w->whereDate('VAC_FHA', '>=', $d['fechaDesde'])->whereDate('VAC_FHA', '<=', $d['fechaHasta']));
                })
                ->orderBy('VAC_FDE')->get(['VAC_NOM', 'VAC_FDE', 'VAC_FHA']);
            if ($solap->isNotEmpty()) {
                return response()->json([
                    'requiere_confirmacion' => true,
                    'message' => 'Existen empleados en vacaciones en dicho período.',
                    'solapados' => $solap->map(fn ($s) => trim((string) $s->VAC_NOM) . ' vacaciones del ' . \Carbon\Carbon::parse($s->VAC_FDE)->format('d/m/Y') . ' hasta ' . \Carbon\Carbon::parse($s->VAC_FHA)->format('d/m/Y'))->values(),
                ], 409);
            }
        }

        $usuario = trim((string) (optional($request->user())->name ?? 'RRHH.NET'));
        $nro = 0;
        DB::transaction(function () use ($d, $cod, $anio, $p, $cantLiq, $usuario, &$nro) {
            $nro = (int) DB::table('vacaciones')->max('VAC_NRO') + 1;
            DB::table('vacaciones')->insert(Registro::completar('vacaciones', [
                'VAC_NRO' => $nro, 'VAC_PER' => $cod, 'VAC_NOM' => trim((string) $p->PER_NOM),
                'VAC_FEC' => !empty($d['fechaPago']) ? \Carbon\Carbon::parse($d['fechaPago'])->format('Y-m-d') : '1900-01-01',
                'VAC_FDE' => \Carbon\Carbon::parse($d['fechaDesde'])->format('Y-m-d'), 'VAC_FHA' => \Carbon\Carbon::parse($d['fechaHasta'])->format('Y-m-d'),
                'VAC_ANO' => $anio, 'VAC_DIA' => (int) $d['dias'], 'VAC_ING' => \Carbon\Carbon::parse($p->PER_ING)->format('Y-m-d'), 'VAC_ANT' => 0,
                'VAC_PRE' => !empty($d['presenta']) ? \Carbon\Carbon::parse($d['presenta'])->format('Y-m-d') : '1900-01-01',
                'VAC_LIQ' => ($d['liquidada'] ?? false) ? 1 : 0, 'VAC_GOZ' => ($d['gozada'] ?? false) ? 1 : 0,
                'VAC_LCAN' => $cantLiq, 'VAC_OBS' => substr(trim((string) ($d['observaciones'] ?? '')), 0, 200),
                'VAC_VTR' => ($d['vt'] ?? false) ? 1 : 0, 'VAC_FCARGA' => now()->format('Y-m-d'),
            ]));

            $obs = trim((string) ($d['observaciones'] ?? ''));
            if ($obs !== '') {
                DB::table('per_hist')->insert(Registro::completar('per_hist', [
                    'hla_cod' => $cod, 'hla_fec' => now(), 'hla_usu' => mb_strtoupper($usuario),
                    'hla_ter' => 'RRHH.NET', 'hla_cam' => 'Vacaciones: ' . $obs,
                ]));
            }
        });

        // Si las vacaciones se cargaron desde un permiso pendiente, marcarlo como usado.
        $mensaje = 'Vacaciones agregadas correctamente.';
        if (!empty($d['permiso_cod'])) {
            $nomUsuario = mb_substr(trim((string) (optional($request->user())->NOMBRE ?? 'RRHH.NET')), 0, 50);
            DB::connection('gestion')->table('PERMISOS_LABORALES')
                ->where('pla_cod', (int) $d['permiso_cod'])->where('pla_est', 0)
                ->update(['PLA_EST' => 1, 'pla_fconfirma' => now()->format('Y-m-d H:i:s'), 'pla_rconfirma' => $nomUsuario]);
            $mensaje = 'Vacaciones agregadas y permiso marcado como usado.';
        }

        return response()->json(['message' => $mensaje, 'vac_nro' => $nro, 'corresponden' => $corresponden]);
    }

    /** Días de vacaciones por antigüedad (LCT art. 150), calculados al 31/12 del año. */
    private function corresponden($ingreso, int $anio): int
    {
        if (!$ingreso) return 14;
        $anios = \Carbon\Carbon::parse($ingreso)->diffInYears(\Carbon\Carbon::create($anio, 12, 31));
        if ($anios <= 5) return 14;
        if ($anios <= 10) return 21;
        if ($anios <= 20) return 28;
        return 35;
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y');
    }
}
