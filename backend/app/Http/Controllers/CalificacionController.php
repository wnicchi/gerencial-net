<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CalificacionController — Calificación del empleado (calificacion.scx).
 *
 * No es un ABM: registra una calificación de desempeño de un empleado en su
 * puesto activo. Muestra (solo lectura) las tareas/educación/cualidades/
 * competencias del puesto, y permite generar necesidades de capacitación
 * (cap_nece) por área temática (tema).
 *
 * calificacion: CAL_FEC, CAL_PUE, CAL_CUIL (cuil empleado), CAL_RES, CAL_NEG,
 *               CAL_POS, CAL_CUIRES (cuil responsable).
 */
class CalificacionController extends Controller
{
    /** @route GET /api/calificaciones/empleado/{cod} — empleado + puestos activos. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_COD', 'PER_NOM', 'PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }
        $cuil = trim((string) $p->PER_CUI);
        $puestos = DB::table('puestoempleado as pe')
            ->join('puestos as pu', DB::raw('UPPER(RTRIM(pu.PUE_COD))'), '=', DB::raw('UPPER(RTRIM(pe.PEM_PUE))'))
            ->whereRaw('UPPER(RTRIM(pe.PEM_CUIL)) = ?', [mb_strtoupper($cuil)])->where('pe.PEM_ACT', 1)
            ->orderBy('pu.PUE_DES')->get(['pu.PUE_COD', 'pu.PUE_DES'])
            ->map(fn ($r) => ['cod' => trim((string) $r->PUE_COD), 'des' => trim((string) $r->PUE_DES)])->values();

        return response()->json([
            'codigo'  => (int) $p->PER_COD,
            'nombre'  => trim((string) $p->PER_NOM),
            'cuit'    => $cuil,
            'puestos' => $puestos,
        ]);
    }

    /** @route GET /api/calificaciones/hoja-puestos/{cod} — empleado + puestos activos (con desde/hasta/reporta). */
    public function hojaPuestos(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado inexistente.'], 404);
        }
        $cuil = trim((string) $p->PER_CUI);
        $puestos = DB::table('puestoempleado as pe')
            ->join('puestos as pu', DB::raw('UPPER(RTRIM(pu.PUE_COD))'), '=', DB::raw('UPPER(RTRIM(pe.PEM_PUE))'))
            ->whereRaw('UPPER(RTRIM(pe.PEM_CUIL)) = ?', [mb_strtoupper($cuil)])->where('pe.PEM_ACT', 1)
            ->orderByDesc('pe.PEM_ACT')->orderByDesc('pe.PEM_FDES')
            ->get(['pu.PUE_COD', 'pu.PUE_DES', 'pu.PUE_REP', 'pe.PEM_FDES', 'pe.PEM_FHAS', 'pe.PEM_ACT'])
            ->map(fn ($r) => [
                'cod'     => trim((string) $r->PUE_COD),
                'des'     => trim((string) $r->PUE_DES),
                'reporta' => trim((string) $r->PUE_REP),
                'desde'   => $r->PEM_FDES && Carbon::parse($r->PEM_FDES)->year > 1900 ? Carbon::parse($r->PEM_FDES)->format('d/m/Y') : '',
                'hasta'   => $r->PEM_FHAS && Carbon::parse($r->PEM_FHAS)->year > 1900 ? Carbon::parse($r->PEM_FHAS)->format('d/m/Y') : '',
                'activo'  => (bool) $r->PEM_ACT,
            ])->values();
        return response()->json(['nombre' => trim((string) $p->PER_NOM), 'puestos' => $puestos]);
    }

    /**
     * Consulta / informe de calificaciones (calificacion_consulta.scx).
     * @route GET /api/calificaciones/consulta
     *   empleado(1/2)+codigo · empresa(1/2)+empresa_id · contratista(1/2)+contratista_id
     *   lugar(1/2)+lugar_id · convenio(1/2)+convenio_id · categoria(1/2)+categoria_id
     *   periodo(1=histórico,2=rango)+fecha1+fecha2 (sobre CAL_FEC)
     */
    public function consulta(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado' => 'required|integer|in:1,2', 'codigo' => 'nullable|integer',
            'empresa' => 'required|integer|in:1,2', 'empresa_id' => 'nullable|integer',
            'contratista' => 'required|integer|in:1,2', 'contratista_id' => 'nullable|integer',
            'lugar' => 'required|integer|in:1,2', 'lugar_id' => 'nullable|integer',
            'convenio' => 'required|integer|in:1,2', 'convenio_id' => 'nullable|integer',
            'categoria' => 'required|integer|in:1,2', 'categoria_id' => 'nullable|integer',
            'periodo' => 'required|integer|in:1,2', 'fecha1' => 'nullable|date', 'fecha2' => 'nullable|date',
        ]);

        $q = DB::table('calificacion as c')
            ->join('personal as a', DB::raw('UPPER(RTRIM(a.PER_CUI))'), '=', DB::raw('UPPER(RTRIM(c.CAL_CUIL))'))
            ->leftJoin('puestos as p', DB::raw('UPPER(RTRIM(p.PUE_COD))'), '=', DB::raw('UPPER(RTRIM(c.CAL_PUE))'));

        if ((int) $d['empleado'] === 2 && (int) ($d['codigo'] ?? 0) > 0) $q->where('a.PER_COD', (int) $d['codigo']);
        if ((int) $d['empresa'] === 2 && (int) ($d['empresa_id'] ?? 0) > 0) $q->where('a.PER_EMP', (int) $d['empresa_id']);
        if ((int) $d['contratista'] === 2 && (int) ($d['contratista_id'] ?? 0) > 0) $q->where('a.PER_CONTRA', (int) $d['contratista_id']);
        if ((int) $d['lugar'] === 2 && (int) ($d['lugar_id'] ?? 0) > 0) $q->where('a.PER_LUGAR', (int) $d['lugar_id']);
        if ((int) $d['convenio'] === 2 && (int) ($d['convenio_id'] ?? 0) > 0) $q->where('a.PER_CON', (int) $d['convenio_id']);
        if ((int) $d['categoria'] === 2 && (int) ($d['categoria_id'] ?? 0) > 0) $q->where('a.PER_CAT', (int) $d['categoria_id']);
        if ((int) $d['periodo'] === 2 && !empty($d['fecha1']) && !empty($d['fecha2'])) {
            $q->whereRaw('CAST(c.CAL_FEC AS DATE) BETWEEN ? AND ?',
                [Carbon::parse($d['fecha1'])->toDateString(), Carbon::parse($d['fecha2'])->toDateString()]);
        }

        $rows = $q->orderBy('a.PER_NOM')->orderByDesc('c.CAL_FEC')
            ->get(['a.PER_COD', 'a.PER_NOM', 'a.PER_CUI', 'c.CAL_FEC', 'c.CAL_PUE', 'p.PUE_DES', 'c.CAL_RES', 'c.CAL_NEG', 'c.CAL_POS', 'c.CAL_CUIRES']);

        $grupos = [];
        foreach ($rows as $r) {
            $cod = (int) $r->PER_COD;
            if (!isset($grupos[$cod])) {
                $grupos[$cod] = ['codigo' => $cod, 'nombre' => trim((string) $r->PER_NOM), 'cuit' => trim((string) $r->PER_CUI), 'items' => []];
            }
            $grupos[$cod]['items'][] = [
                'fecha'      => $r->CAL_FEC ? Carbon::parse($r->CAL_FEC)->format('d/m/Y') : '',
                'puesto'     => trim((string) $r->CAL_PUE),
                'puesto_des' => trim((string) ($r->PUE_DES ?? '')),
                'resultado'  => trim((string) $r->CAL_RES),
                'negativas'  => trim((string) $r->CAL_NEG),
                'positivas'  => trim((string) $r->CAL_POS),
                'cuil_resp'  => trim((string) $r->CAL_CUIRES),
            ];
        }

        return response()->json(['grupos' => array_values($grupos), 'total' => $rows->count()]);
    }

    /** @route GET /api/calificaciones/persona/{cod} — datos del responsable. */
    public function persona(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_CUI']);
        if (!$p) {
            return response()->json(['message' => 'Código inexistente.'], 404);
        }
        return response()->json(['nombre' => trim((string) $p->PER_NOM), 'cuit' => trim((string) $p->PER_CUI)]);
    }

    /** @route GET /api/calificaciones/puesto/{cod} — descripción + 4 solapas del puesto. */
    public function puesto(string $cod): JsonResponse
    {
        $p = DB::table('puestos as pu')->leftJoin('departam as d', 'd.DEP_COD', '=', 'pu.PUE_DEP')
            ->where('pu.PUE_COD', $cod)->first(['pu.PUE_REP', 'pu.PUE_OBJ', 'd.DEP_DES']);
        $tareas = DB::table('tareapue as t')->leftJoin('frecuencia as f', 'f.FRE_COD', '=', 't.TAR_FRE')
            ->where('t.TAR_PUE', $cod)->orderBy('t.TAR_DES')->get(['t.TAR_COD', 't.TAR_DES', 't.TAR_MIN', 'f.FRE_DES'])
            ->map(fn ($r) => ['cod' => (int) $r->TAR_COD, 'des' => trim((string) $r->TAR_DES), 'fre' => trim((string) ($r->FRE_DES ?? '')), 'min' => (int) $r->TAR_MIN])->values();
        $lista = fn ($tabla, $colDes, $colPue) => DB::table($tabla)->where($colPue, $cod)->orderBy($colDes)->pluck($colDes)->map(fn ($x) => trim((string) $x))->values();

        return response()->json([
            'reporta'      => trim((string) ($p->PUE_REP ?? '')),
            'departamento' => trim((string) ($p->DEP_DES ?? '')),
            'objetivos'    => trim((string) ($p->PUE_OBJ ?? '')),
            'tareas'       => $tareas,
            'educacion'    => $lista('educacion', 'EDU_DES', 'EDU_PUE'),
            'cualidades'   => $lista('cualidad', 'CUA_DES', 'CUA_PUE'),
            'competencias' => $lista('competencia', 'COMP_DES', 'COMP_PUE'),
        ]);
    }

    /** @route GET /api/calificaciones/temas — áreas temáticas. */
    public function temas(): JsonResponse
    {
        return response()->json(
            DB::table('tema')->orderBy('TEM_DES')->get(['TEM_COD', 'TEM_DES'])
                ->map(fn ($t) => ['cod' => (int) $t->TEM_COD, 'des' => trim((string) $t->TEM_DES)])->values()
        );
    }

    /** @route GET /api/calificaciones/pendientes/{cod} — necesidades de capacitación pendientes. */
    public function pendientes(int $cod): JsonResponse
    {
        $rows = DB::table('cap_nece as cn')->leftJoin('tema as t', 't.TEM_COD', '=', 'cn.CNE_TEM')
            ->where('cn.CNE_EMP', $cod)->orderByDesc('cn.CNE_FEC')
            ->get(['cn.CNE_TEM', 'cn.CNE_FEC', 't.TEM_DES'])
            ->map(fn ($r) => [
                'tema'    => (int) $r->CNE_TEM,
                'des'     => mb_strtoupper(trim((string) ($r->TEM_DES ?? ''))),
                'fecha'   => $r->CNE_FEC ? Carbon::parse($r->CNE_FEC)->format('d/m/Y') : '',
            ])->values();
        return response()->json($rows);
    }

    /** @route POST /api/calificaciones/necesidad — genera una necesidad de capacitación. */
    public function asignarNecesidad(Request $request): JsonResponse
    {
        $d = $request->validate(['codigo' => 'required|integer', 'tema' => 'required|integer', 'fecha' => 'required|date']);
        $existe = DB::table('cap_nece')->where('CNE_EMP', (int) $d['codigo'])->where('CNE_TEM', (int) $d['tema'])->exists();
        if ($existe) {
            return response()->json(['message' => 'Ya existe generada la necesidad.'], 422);
        }
        DB::table('cap_nece')->insert(Registro::completar('cap_nece', [
            'CNE_EMP' => (int) $d['codigo'], 'CNE_TEM' => (int) $d['tema'], 'CNE_FEC' => Carbon::parse($d['fecha']),
        ]));
        $tem = DB::table('tema')->where('TEM_COD', (int) $d['tema'])->value('TEM_DES');
        return response()->json(['ok' => true, 'mensaje' => 'Se ha generado la necesidad de ' . trim((string) $tem) . '.'], 201);
    }

    /** @route POST /api/calificaciones — graba la calificación. */
    public function guardar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigo'      => 'required|integer',
            'responsable' => 'required|integer',
            'puesto'      => 'required|string|max:15',
            'fecha'       => 'required|date',
            'resultado'   => 'nullable|string|max:200',
            'positivas'   => 'nullable|string|max:200',
            'negativas'   => 'nullable|string|max:200',
        ]);

        $res = trim((string) ($d['resultado'] ?? ''));
        $pos = trim((string) ($d['positivas'] ?? ''));
        $neg = trim((string) ($d['negativas'] ?? ''));
        if ($res === '' && $pos === '' && $neg === '') {
            return response()->json(['message' => 'No ha ingresado ninguna calificación.'], 422);
        }

        $fecha = Carbon::parse($d['fecha'])->startOfDay();
        if ($fecha->gt(Carbon::today()) || $fecha->lt(Carbon::today()->subDays(60))) {
            return response()->json(['message' => 'La fecha de calificación no es válida (debe estar dentro de los últimos 60 días).'], 422);
        }

        $emp = DB::table('personal')->where('PER_COD', (int) $d['codigo'])->first(['PER_CUI']);
        if (!$emp) return response()->json(['message' => 'El código del empleado a calificar no es válido.'], 422);
        if (!\App\Support\Empleado::activo((int) $d['codigo'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }
        $resp = DB::table('personal')->where('PER_COD', (int) $d['responsable'])->first(['PER_CUI']);
        if (!$resp) return response()->json(['message' => 'El código del responsable en la calificación no es válido.'], 422);

        $cuilEmp = trim((string) $emp->PER_CUI);
        $perteneceAlEmpleado = DB::table('puestoempleado')
            ->whereRaw('UPPER(RTRIM(PEM_CUIL)) = ?', [mb_strtoupper($cuilEmp)])
            ->whereRaw('UPPER(RTRIM(PEM_PUE)) = ?', [mb_strtoupper(trim($d['puesto']))])
            ->where('PEM_ACT', 1)->exists();
        if (!$perteneceAlEmpleado) {
            return response()->json(['message' => 'El puesto seleccionado no corresponde al empleado seleccionado.'], 422);
        }

        DB::table('calificacion')->insert(Registro::completar('calificacion', [
            'CAL_FEC'    => $fecha,
            'CAL_PUE'    => trim($d['puesto']),
            'CAL_CUIL'   => $cuilEmp,
            'CAL_RES'    => $res,
            'CAL_NEG'    => $neg,
            'CAL_POS'    => $pos,
            'CAL_CUIRES' => trim((string) $resp->PER_CUI),
        ]));
        return response()->json(['ok' => true]);
    }
}
