<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PuestoController — Puestos de Trabajo (ABM 7 solapas).
 *
 * Réplica de puestos.scx (FoxPro RRHHWINSQL). El puesto (tabla `puestos`,
 * PUE_COD = código alfanumérico manual) tiene 6 colecciones hijas:
 *   - tareapue   (Tareas a Realizar)        — UPPER
 *   - educacion  (Requerimiento Educacional)— UPPER  (EDU_ACA/EDU_COM bits)
 *   - cualidad   (Cualidades Necesarias)    — UPPER
 *   - competencia(Competencias)             — UPPER
 *   - elementos  (Elementos de Trabajo)     — lower
 *   - puestosrev (Revisiones)               — lower
 *
 * Las sub-solapas se graban estilo "reemplazar todo" (igual que el botón
 * Confirmar del FoxPro: borra las del puesto y reinserta la lista enviada).
 */
class PuestoController extends Controller
{
    /** @route GET /api/puestos?buscar= — lista para el panel izquierdo. */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('puestos as p')->leftJoin('departam as d', 'd.DEP_COD', '=', 'p.PUE_DEP');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where(function ($w) use ($b) {
                $w->where('p.PUE_COD', 'like', "%{$b}%")->orWhere('p.PUE_DES', 'like', "%{$b}%");
            });
        }
        $rows = $q->orderBy('p.PUE_DES')->get(['p.PUE_COD', 'p.PUE_DES', 'p.PUE_DEP', 'd.DEP_DES'])
            ->map(fn ($r) => [
                'codigo'      => trim((string) $r->PUE_COD),
                'descripcion' => trim((string) $r->PUE_DES),
                'depto'       => trim((string) ($r->DEP_DES ?? '')),
            ])->values();
        return response()->json($rows);
    }

    /** @route GET /api/puestos/catalogos — departamentos + frecuencias. */
    public function catalogos(): JsonResponse
    {
        return response()->json([
            'departamentos' => DB::table('departam')->orderBy('DEP_DES')->get(['DEP_COD', 'DEP_DES'])
                ->map(fn ($d) => ['cod' => (int) $d->DEP_COD, 'des' => trim((string) $d->DEP_DES)])->values(),
            'frecuencias'   => DB::table('frecuencia')->orderBy('FRE_DES')->get(['FRE_COD', 'FRE_DES'])
                ->map(fn ($f) => ['cod' => (int) $f->FRE_COD, 'des' => trim((string) $f->FRE_DES)])->values(),
        ]);
    }

    /** @route GET /api/puestos/{cod} — puesto + todas sus colecciones. */
    public function show(string $cod): JsonResponse
    {
        $p = DB::table('puestos as p')->leftJoin('departam as d', 'd.DEP_COD', '=', 'p.PUE_DEP')
            ->where('p.PUE_COD', $cod)->first(['p.*', 'd.DEP_DES']);
        if (!$p) {
            return response()->json(['message' => 'Puesto no encontrado.'], 404);
        }

        return response()->json([
            'puesto'        => [
                'codigo'        => trim((string) $p->PUE_COD),
                'descripcion'   => trim((string) $p->PUE_DES),
                'departamento'  => (int) $p->PUE_DEP,
                'depto_nombre'  => trim((string) ($p->DEP_DES ?? '')),
                'reporta'       => trim((string) $p->PUE_REP),
                'objetivo'      => trim((string) $p->PUE_OBJ),
                'modificaciones' => trim((string) $p->PUE_MOD),
                'requisitos'    => trim((string) $p->PUE_REQ),
                'fecha'         => $this->fechaIso($p->PUE_FEC),
            ],
            'tareas'        => $this->tareas($cod),
            'educacion'     => $this->educacion($cod),
            'cualidades'    => $this->lista('cualidad', 'CUA_DES', 'CUA_PUE', $cod),
            'competencias'  => $this->lista('competencia', 'COMP_DES', 'COMP_PUE', $cod),
            'elementos'     => $this->lista('elementos', 'ele_des', 'ele_pue', $cod),
            'revisiones'    => $this->revisiones($cod),
        ]);
    }

    /**
     * Informe de puestos (Listados Puestos — puestos_consulta.scx).
     * @route GET /api/puestos/informe?modo=&puesto=&revisiones=&solo_con_empleados=
     *   modo: 1=todos, 2=individual (requiere puesto)
     *   revisiones: 1=con revisiones, 2=sin revisiones
     *   solo_con_empleados: 1 → solo puestos con empleados asignados (puestoempleado)
     */
    public function informe(Request $request): JsonResponse
    {
        $d = $request->validate([
            'modo'      => 'required|integer|in:1,2',
            'puesto'    => 'nullable|string|max:15',
            'revisiones' => 'nullable|integer|in:1,2',
            'solo_con_empleados' => 'nullable|integer|in:0,1',
        ]);
        $conRev = (int) ($d['revisiones'] ?? 1) === 1;

        $q = DB::table('puestos as p')->leftJoin('departam as dd', 'dd.DEP_COD', '=', 'p.PUE_DEP');
        if ((int) $d['modo'] === 2 && !empty($d['puesto'])) {
            $q->where('p.PUE_COD', trim($d['puesto']));
        }
        if ((int) ($d['solo_con_empleados'] ?? 0) === 1) {
            $ocupados = DB::table('puestoempleado')->select(DB::raw('UPPER(RTRIM(PEM_PUE)) as pue'))->distinct()->pluck('pue')->all();
            $q->whereIn(DB::raw('UPPER(RTRIM(p.PUE_COD))'), $ocupados ?: ['__none__']);
        }
        $puestos = $q->orderBy('p.PUE_DES')->get(['p.*', 'dd.DEP_DES']);

        $out = $puestos->map(function ($p) use ($conRev) {
            $cod = trim((string) $p->PUE_COD);
            return [
                'codigo'        => $cod,
                'descripcion'   => trim((string) $p->PUE_DES),
                'depto_nombre'  => trim((string) ($p->DEP_DES ?? '')),
                'reporta'       => trim((string) $p->PUE_REP),
                'objetivo'      => trim((string) $p->PUE_OBJ),
                'fecha'         => $this->fechaIso($p->PUE_FEC),
                'tareas'        => $this->tareas($cod),
                'educacion'     => $this->educacion($cod),
                'cualidades'    => $this->lista('cualidad', 'CUA_DES', 'CUA_PUE', $cod),
                'competencias'  => $this->lista('competencia', 'COMP_DES', 'COMP_PUE', $cod),
                'revisiones'    => $conRev ? $this->revisiones($cod) : [],
            ];
        })->values();

        return response()->json(['puestos' => $out]);
    }

    /**
     * Empleados por Puesto (empleados_puestos.scx).
     * @route GET /api/puestos/empleados-por-puesto?empresa=&empresa_id=&modo=&puesto=&estado=
     *   empresa: 1=todas, 2=una (empresa_id) · modo: 1=todos, 2=un puesto (puesto)
     *   estado: 1=todos, 2=activos (PER_AOP=A), 3=pasivos (PER_AOP=P)
     */
    public function empleadosPorPuesto(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa'    => 'required|integer|in:1,2',
            'empresa_id' => 'nullable|integer',
            'modo'       => 'required|integer|in:1,2',
            'puesto'     => 'nullable|string|max:15',
            'estado'     => 'required|integer|in:1,2,3',
        ]);

        $q = DB::table('puestoempleado as pe')
            ->join('puestos as p', DB::raw('UPPER(RTRIM(p.PUE_COD))'), '=', DB::raw('UPPER(RTRIM(pe.PEM_PUE))'))
            ->join('personal as e', DB::raw('UPPER(RTRIM(e.PER_CUI))'), '=', DB::raw('UPPER(RTRIM(pe.PEM_CUIL))'))
            ->where('pe.PEM_ACT', 1);

        $titulo = '';
        if ((int) $d['empresa'] === 2 && (int) ($d['empresa_id'] ?? 0) > 0) {
            $q->where('e.PER_EMP', (int) $d['empresa_id']);
            $emp = DB::table('empresas')->where('EMP_COD', (int) $d['empresa_id'])->value('EMP_NOM');
            $titulo .= ' Empresa ' . trim((string) $emp);
        }
        if ((int) $d['modo'] === 2 && !empty($d['puesto'])) {
            $q->whereRaw('UPPER(RTRIM(p.PUE_COD)) = ?', [mb_strtoupper(trim($d['puesto']))]);
            $pd = DB::table('puestos')->where('PUE_COD', trim($d['puesto']))->value('PUE_DES');
            $titulo .= ' PUESTO ' . trim((string) $pd);
        }
        if ((int) $d['estado'] === 2) { $q->whereRaw("UPPER(RTRIM(e.PER_AOP)) = 'A'"); $titulo .= ' Empleados ACTIVOS'; }
        elseif ((int) $d['estado'] === 3) { $q->whereRaw("UPPER(RTRIM(e.PER_AOP)) = 'P'"); $titulo .= ' Empleados PASIVOS'; }
        else { $titulo .= ' TODOS los Empleados'; }

        $rows = $q->orderBy('p.PUE_DES')->orderBy('e.PER_NOM')
            ->get(['p.PUE_COD', 'p.PUE_DES', 'e.PER_COD', 'e.PER_LEG', 'e.PER_NOM', 'e.PER_CUI', 'e.PER_AOP']);

        $grupos = [];
        foreach ($rows as $r) {
            $k = trim((string) $r->PUE_COD);
            if (!isset($grupos[$k])) {
                $grupos[$k] = ['puesto' => $k, 'puesto_des' => trim((string) $r->PUE_DES), 'empleados' => []];
            }
            $grupos[$k]['empleados'][] = [
                'codigo' => (int) $r->PER_COD,
                'legajo' => trim((string) $r->PER_LEG),
                'nombre' => trim((string) $r->PER_NOM),
                'cuit'   => trim((string) $r->PER_CUI),
                'estado' => trim((string) $r->PER_AOP) === 'A' ? 'Activo' : 'Pasivo',
            ];
        }

        return response()->json([
            'titulo' => trim('INFORME DE EMPLEADOS POR PUESTO — ' . trim($titulo), ' —'),
            'grupos' => array_values($grupos),
            'total'  => $rows->count(),
        ]);
    }

    /**
     * Hojas de Evaluación de desempeño (puestos_hoja_evaluacion.scx).
     * Devuelve los empleados activos (con puesto activo) que cumplen los filtros;
     * el front genera una hoja de evaluación por empleado.
     * @route GET /api/puestos/hojas-evaluacion
     *   empleado(1/2)+codigo · empresa(1/2)+empresa_id · lugar(1/2)+lugar_id
     *   convenio(1/2)+convenio_id · sector(1/2)+sector_id · subsector(1/2)+subsector_id
     */
    public function hojasEvaluacion(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado' => 'required|integer|in:1,2', 'codigo' => 'nullable|integer',
            'empresa' => 'required|integer|in:1,2', 'empresa_id' => 'nullable|integer',
            'lugar' => 'required|integer|in:1,2', 'lugar_id' => 'nullable|integer',
            'convenio' => 'required|integer|in:1,2', 'convenio_id' => 'nullable|integer',
            'sector' => 'required|integer|in:1,2', 'sector_id' => 'nullable|integer',
            'subsector' => 'required|integer|in:1,2', 'subsector_id' => 'nullable|integer',
        ]);

        $q = DB::table('personal as a')
            ->join('puestoempleado as b', DB::raw('UPPER(RTRIM(a.PER_CUI))'), '=', DB::raw('UPPER(RTRIM(b.PEM_CUIL))'))
            ->join('puestos as c', DB::raw('UPPER(RTRIM(b.PEM_PUE))'), '=', DB::raw('UPPER(RTRIM(c.PUE_COD))'))
            ->whereRaw("UPPER(RTRIM(a.PER_AOP)) = 'A'")->where('b.PEM_ACT', 1);

        if ((int) $d['empleado'] === 2 && (int) ($d['codigo'] ?? 0) > 0) $q->where('a.PER_COD', (int) $d['codigo']);
        if ((int) $d['empresa'] === 2 && (int) ($d['empresa_id'] ?? 0) > 0) $q->where('a.PER_EMP', (int) $d['empresa_id']);
        if ((int) $d['lugar'] === 2 && (int) ($d['lugar_id'] ?? 0) > 0) $q->where('a.PER_LUGAR', (int) $d['lugar_id']);
        if ((int) $d['convenio'] === 2 && (int) ($d['convenio_id'] ?? 0) > 0) $q->where('a.PER_CON', (int) $d['convenio_id']);
        if ((int) $d['sector'] === 2 && (int) ($d['sector_id'] ?? 0) > 0) $q->where('a.PER_SEC', (int) $d['sector_id']);
        if ((int) $d['subsector'] === 2 && (int) ($d['subsector_id'] ?? 0) > 0) $q->where('a.PER_SUC', (int) $d['subsector_id']);

        $rows = $q->distinct()->orderBy('a.PER_NOM')
            ->get(['a.PER_COD', 'a.PER_NOM', 'c.PUE_DES'])
            ->map(fn ($r) => [
                'codigo' => (int) $r->PER_COD,
                'nombre' => trim((string) $r->PER_NOM),
                'puesto' => trim((string) $r->PUE_DES),
            ])->values();

        return response()->json(['empleados' => $rows]);
    }

    /** @route POST /api/puestos — alta del puesto (solapa Detalles). */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validarDetalle($request, true);
        if (DB::table('puestos')->where('PUE_COD', $d['codigo'])->exists()) {
            return response()->json(['message' => 'Ya existe un puesto con ese código.'], 422);
        }
        DB::table('puestos')->insert(Registro::completar('puestos', $this->mapDetalle($d)));
        return response()->json(['ok' => true, 'codigo' => $d['codigo']], 201);
    }

    /** @route PUT /api/puestos/{cod} — modifica la solapa Detalles. */
    public function update(Request $request, string $cod): JsonResponse
    {
        if (!DB::table('puestos')->where('PUE_COD', $cod)->exists()) {
            return response()->json(['message' => 'Puesto no encontrado.'], 404);
        }
        $d = $this->validarDetalle($request, false);
        $map = $this->mapDetalle($d);
        unset($map['PUE_COD']);
        DB::table('puestos')->where('PUE_COD', $cod)->update($map);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/puestos/{cod} — borra el puesto y sus colecciones. */
    public function destroy(string $cod): JsonResponse
    {
        DB::transaction(function () use ($cod) {
            DB::table('tareapue')->where('TAR_PUE', $cod)->delete();
            DB::table('educacion')->where('EDU_PUE', $cod)->delete();
            DB::table('cualidad')->where('CUA_PUE', $cod)->delete();
            DB::table('competencia')->where('COMP_PUE', $cod)->delete();
            DB::table('elementos')->where('ele_pue', $cod)->delete();
            DB::table('puestosrev')->where('pur_cod', $cod)->delete();
            DB::table('puestos')->where('PUE_COD', $cod)->delete();
        });
        return response()->json(['ok' => true]);
    }

    // ── Sub-solapas (reemplazar todo) ──────────────────────────

    /** @route PUT /api/puestos/{cod}/tareas  Body: { items:[{des,fre,min}] } */
    public function guardarTareas(Request $request, string $cod): JsonResponse
    {
        $d = $request->validate([
            'items' => 'present|array', 'items.*.des' => 'required|string|max:100',
            'items.*.fre' => 'nullable|integer', 'items.*.min' => 'nullable|integer|min:0',
        ]);
        DB::transaction(function () use ($cod, $d) {
            DB::table('tareapue')->where('TAR_PUE', $cod)->delete();
            $i = 0;
            foreach ($d['items'] as $it) {
                $i++;
                DB::table('tareapue')->insert(Registro::completar('tareapue', [
                    'TAR_COD' => $i, 'TAR_DES' => mb_strtoupper(trim($it['des'])), 'TAR_PUE' => $cod,
                    'TAR_FRE' => (int) ($it['fre'] ?? 0), 'TAR_MIN' => (int) ($it['min'] ?? 0),
                ]));
            }
        });
        return response()->json(['ok' => true]);
    }

    /** @route PUT /api/puestos/{cod}/educacion  Body: { items:[{des,aca,com}] } */
    public function guardarEducacion(Request $request, string $cod): JsonResponse
    {
        $d = $request->validate([
            'items' => 'present|array', 'items.*.des' => 'required|string|max:50',
            'items.*.aca' => 'nullable|boolean', 'items.*.com' => 'nullable|boolean',
        ]);
        DB::transaction(function () use ($cod, $d) {
            DB::table('educacion')->where('EDU_PUE', $cod)->delete();
            $i = 0;
            foreach ($d['items'] as $it) {
                $i++;
                DB::table('educacion')->insert(Registro::completar('educacion', [
                    'EDU_COD' => $i, 'EDU_DES' => mb_strtoupper(trim($it['des'])), 'EDU_PUE' => $cod,
                    'EDU_ACA' => (int) ($it['aca'] ?? false), 'EDU_COM' => (int) ($it['com'] ?? false),
                ]));
            }
        });
        return response()->json(['ok' => true]);
    }

    /** @route PUT /api/puestos/{cod}/cualidades  Body: { items:["..."] } */
    public function guardarCualidades(Request $request, string $cod): JsonResponse
    {
        return $this->guardarListaSimple($request, $cod, 'cualidad', 'CUA_COD', 'CUA_DES', 'CUA_PUE');
    }

    /** @route PUT /api/puestos/{cod}/competencias  Body: { items:["..."] } */
    public function guardarCompetencias(Request $request, string $cod): JsonResponse
    {
        return $this->guardarListaSimple($request, $cod, 'competencia', 'COMP_COD', 'COMP_DES', 'COMP_PUE');
    }

    /** @route PUT /api/puestos/{cod}/elementos  Body: { items:["..."] } */
    public function guardarElementos(Request $request, string $cod): JsonResponse
    {
        return $this->guardarListaSimple($request, $cod, 'elementos', 'ele_cod', 'ele_des', 'ele_pue');
    }

    /** @route PUT /api/puestos/{cod}/revisiones  Body: { items:[{fre,res,ob1,ob2,ob3,ob4}] } */
    public function guardarRevisiones(Request $request, string $cod): JsonResponse
    {
        $d = $request->validate([
            'items' => 'present|array',
            'items.*.fre' => 'required|date',
            'items.*.res' => 'required|string|max:100',
            'items.*.ob1' => 'nullable|string|max:100', 'items.*.ob2' => 'nullable|string|max:100',
            'items.*.ob3' => 'nullable|string|max:100', 'items.*.ob4' => 'nullable|string|max:100',
        ]);
        DB::transaction(function () use ($cod, $d) {
            DB::table('puestosrev')->where('pur_cod', $cod)->delete();
            foreach ($d['items'] as $it) {
                DB::table('puestosrev')->insert(Registro::completar('puestosrev', [
                    'pur_cod' => $cod, 'pur_fre' => Carbon::parse($it['fre']),
                    'pur_res' => trim($it['res']),
                    'pur_ob1' => trim((string) ($it['ob1'] ?? '')), 'pur_ob2' => trim((string) ($it['ob2'] ?? '')),
                    'pur_ob3' => trim((string) ($it['ob3'] ?? '')), 'pur_ob4' => trim((string) ($it['ob4'] ?? '')),
                ]));
            }
        });
        return response()->json(['ok' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function guardarListaSimple(Request $request, string $cod, string $tabla, string $colCod, string $colDes, string $colPue): JsonResponse
    {
        $d = $request->validate(['items' => 'present|array', 'items.*' => 'string|max:100']);
        DB::transaction(function () use ($cod, $d, $tabla, $colCod, $colDes, $colPue) {
            DB::table($tabla)->where($colPue, $cod)->delete();
            $i = 0;
            foreach ($d['items'] as $txt) {
                $txt = trim((string) $txt);
                if ($txt === '') continue;
                $i++;
                DB::table($tabla)->insert(Registro::completar($tabla, [
                    $colCod => $i, $colDes => mb_strtoupper($txt), $colPue => $cod,
                ]));
            }
        });
        return response()->json(['ok' => true]);
    }

    private function lista(string $tabla, string $colDes, string $colPue, string $cod): array
    {
        return DB::table($tabla)->where($colPue, $cod)->orderBy($colDes)->get()
            ->map(fn ($r) => trim((string) $r->$colDes))->values()->all();
    }

    private function tareas(string $cod): array
    {
        return DB::table('tareapue as t')->leftJoin('frecuencia as f', 'f.FRE_COD', '=', 't.TAR_FRE')
            ->where('t.TAR_PUE', $cod)->orderBy('t.TAR_DES')->get(['t.TAR_DES', 't.TAR_FRE', 't.TAR_MIN', 'f.FRE_DES'])
            ->map(fn ($r) => [
                'des' => trim((string) $r->TAR_DES), 'fre' => (int) $r->TAR_FRE,
                'fre_des' => trim((string) ($r->FRE_DES ?? '')), 'min' => (int) $r->TAR_MIN,
            ])->values()->all();
    }

    private function educacion(string $cod): array
    {
        return DB::table('educacion')->where('EDU_PUE', $cod)->orderBy('EDU_DES')->get()
            ->map(fn ($r) => [
                'des' => trim((string) $r->EDU_DES), 'aca' => (bool) $r->EDU_ACA, 'com' => (bool) $r->EDU_COM,
            ])->values()->all();
    }

    private function revisiones(string $cod): array
    {
        return DB::table('puestosrev')->where('pur_cod', $cod)->orderByDesc('pur_fre')->get()
            ->map(fn ($r) => [
                'fre' => $this->fechaIso($r->pur_fre), 'res' => trim((string) $r->pur_res),
                'ob1' => trim((string) $r->pur_ob1), 'ob2' => trim((string) $r->pur_ob2),
                'ob3' => trim((string) $r->pur_ob3), 'ob4' => trim((string) $r->pur_ob4),
            ])->values()->all();
    }

    private function validarDetalle(Request $request, bool $alta): array
    {
        // Límites según el tamaño real de las columnas de `puestos`:
        // PUE_DES char(50), PUE_REP char(50), PUE_OBJ char(2000), PUE_MOD/PUE_REQ char(100).
        $reglas = [
            'descripcion'    => 'required|string|max:50',
            'departamento'   => 'nullable|integer',
            'reporta'        => 'nullable|string|max:50',
            'objetivo'       => 'nullable|string|max:2000',
            'modificaciones' => 'nullable|string|max:100',
            'requisitos'     => 'nullable|string|max:100',
            'fecha'          => 'nullable|date',
        ];
        if ($alta) $reglas['codigo'] = 'required|string|max:15';
        return $request->validate($reglas);
    }

    private function mapDetalle(array $d): array
    {
        return [
            'PUE_COD' => $d['codigo'] ?? null,
            'PUE_DES' => mb_strtoupper(trim($d['descripcion'])),
            'PUE_DEP' => (int) ($d['departamento'] ?? 0),
            'PUE_REP' => mb_strtoupper(trim((string) ($d['reporta'] ?? ''))),
            'PUE_OBJ' => trim((string) ($d['objetivo'] ?? '')),
            'PUE_MOD' => trim((string) ($d['modificaciones'] ?? '')),
            'PUE_REQ' => trim((string) ($d['requisitos'] ?? '')),
            'PUE_FEC' => !empty($d['fecha']) ? Carbon::parse($d['fecha']) : Carbon::parse('1900-01-01'),
        ];
    }

    private function fechaIso($v): string
    {
        if (!$v) return '';
        $c = Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->toDateString();
    }
}
