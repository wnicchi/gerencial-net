<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ObraController — Obras de contratistas externos (tabla obras + obras_emp).
 *
 * Obras - Habilitar (obras_iniciar): da de alta una obra para un contratista y
 * vincula los empleados que trabajarán en ella, habilitando su entrada a la empresa.
 *
 * Notas de esquema:
 *  - obras: OBR_COD (numeric, NO identity → max+1), OBR_DET (descripción),
 *    OBR_NOT (notas), OBR_FIN (fecha de INICIO), OBR_FCI (fecha de FINALIZACIÓN,
 *    vacía = obra en curso), OBR_CON (cód contratista), OBR_COND (nombre contratista),
 *    OBR_USU (usuario), OBR_TER (terminal).
 *  - obras_emp: OEM_OBR (cód obra), OEM_EMP (id del empleado).
 *
 * NOTA "id del empleado": el FoxPro guarda CEM_COD en OEM_EMP, pero en los datos reales
 * CEM_COD NO es único (quedó igual al código del contratista por un insert del módulo de
 * Obligaciones), así que linkear por CEM_COD afectaría a TODA la cuadrilla. La clave real
 * del empleado es `unico`. Como obras_emp está vacía (sin datos legados), se vincula por
 * `unico` (contemp.unico). Si en el futuro hay datos viejos con CEM_COD, revisar esto.
 *
 * NOTA "obra en ejecución/habilitada": el FoxPro usa literal `hoy >= OBR_FIN AND hoy <= OBR_FCI`,
 * pero al habilitar una obra OBR_FCI queda vacía (1900) → esa condición literal nunca daría
 * vigente y nadie tendría acceso. Por pedido del usuario, una obra está EN EJECUCIÓN si NO tiene
 * fecha de fin (vacía/1900) o la fin es futura (tiene inicio); NO se exige que el inicio sea
 * anterior a hoy. Consistente con la lógica de "obrero ocupado". Ver esVigente().
 */
class ObraController extends Controller
{
    /** @route POST /api/obras */
    public function store(Request $request): JsonResponse
    {
        $d = $request->validate([
            'descripcion'  => 'required|string|max:200',
            'fecha_inicio' => 'required|date',
            'fecha_final'  => 'nullable|date',
            'contratista'  => 'required|integer',
            'notas'        => 'nullable|string',
            'empleados'    => 'required|array|min:1',   // `unico` de los obreros marcados
            'empleados.*'  => 'integer',
        ], [
            'descripcion.required' => 'No puede habilitar una obra sin descripción.',
            'empleados.required'   => 'Al menos un obrero debe trabajar en la obra.',
            'empleados.min'        => 'Al menos un obrero debe trabajar en la obra.',
        ]);

        $contratista = DB::table('contnom')->where('CTR_COD', $d['contratista'])->first();
        $nombreCon = $contratista ? trim((string) $contratista->CTR_NOM) : '';
        $usuario  = strtoupper(substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30));
        $terminal = substr($this->nombreTerminal($request), 0, 30);

        $obrCod = 0;
        DB::transaction(function () use ($d, $nombreCon, $usuario, $terminal, &$obrCod) {
            $obrCod = ((int) DB::table('obras')->max('OBR_COD')) + 1;

            DB::table('obras')->insert(Registro::completar('obras', [
                'OBR_COD'  => $obrCod,
                'OBR_DET'  => trim($d['descripcion']),
                'OBR_NOT'  => trim((string) ($d['notas'] ?? '')),
                'OBR_FIN'  => $d['fecha_inicio'],                       // INICIO
                'OBR_FCI'  => !empty($d['fecha_final']) ? $d['fecha_final'] : '1900-01-01',  // FINALIZACIÓN (vacía = en curso)
                'OBR_CON'  => (int) $d['contratista'],
                'OBR_COND' => $nombreCon,
                'OBR_USU'  => $usuario,
                'OBR_TER'  => $terminal,
            ]));

            foreach (array_unique(array_map('intval', $d['empleados'])) as $unico) {
                DB::table('obras_emp')->insert(Registro::completar('obras_emp', [
                    'OEM_OBR' => $obrCod,
                    'OEM_EMP' => $unico,      // `unico` del empleado (ver nota de cabecera)
                ]));
                // Habilita la entrada del empleado a la empresa
                DB::table('contemp')->where('unico', $unico)->update(['CEM_EST' => 'P']);
            }
        });

        return response()->json(['ok' => true, 'cod' => $obrCod], 201);
    }

    /**
     * Lista de obras para combos (obras_listados → combo LAOBRA).
     * @route GET /api/obras
     */
    public function index(): JsonResponse
    {
        $rows = DB::table('obras')->orderBy('OBR_DET')->get(['OBR_COD', 'OBR_DET', 'OBR_COND'])
            ->map(fn ($o) => [
                'cod'         => (int) $o->OBR_COD,
                'descripcion' => trim((string) $o->OBR_DET),
                'contratista' => trim((string) $o->OBR_COND),
            ]);
        return response()->json($rows);
    }

    /**
     * Datos de una obra para editar (obras_modificar), con sus empleados marcados.
     * @route GET /api/obras/{cod}
     */
    public function show(int $cod): JsonResponse
    {
        $o = DB::table('obras')->where('OBR_COD', $cod)->first();
        if (!$o) return response()->json(['message' => 'Obra no encontrada.'], 404);
        $hoy = now()->startOfDay();

        // `unico` de empleados marcados en esta obra
        $marcados = DB::table('obras_emp')->where('OEM_OBR', $cod)
            ->pluck('OEM_EMP')->map(fn ($v) => (int) $v)->flip();
        // `unico` ocupados en alguna obra sin finalizar
        $ocupados = DB::table('obras_emp as oe')->join('obras as ob', 'ob.OBR_COD', '=', 'oe.OEM_OBR')
            ->whereDate('ob.OBR_FCI', '<=', '1900-01-01')
            ->pluck('oe.OEM_EMP')->map(fn ($v) => (int) $v)->unique()->flip();

        $empleados = DB::table('contemp')->where('CEM_CON', $o->OBR_CON)->orderBy('CEM_NOM')
            ->get()->map(fn ($r) => [
                'unico'    => (int) $r->unico,
                'cod'      => (int) $r->CEM_COD,
                'nombre'   => trim((string) $r->CEM_NOM),
                'dni'      => trim((string) $r->CEM_DNI),
                'cuil'     => (string) (int) $r->CEM_CUI,
                'venc_art' => $this->fecha($r->CEM_VART),
                'art_vencida' => $r->CEM_VART ? \Carbon\Carbon::parse($r->CEM_VART)->lt($hoy) : true,
                'ocupado'  => isset($ocupados[(int) $r->unico]),
                '_sel'     => isset($marcados[(int) $r->unico]),
            ]);

        return response()->json([
            'cod'          => (int) $o->OBR_COD,
            'descripcion'  => trim((string) $o->OBR_DET),
            'inicio'       => $this->fecha($o->OBR_FIN),
            'final'        => $this->fecha($o->OBR_FCI),
            'contratista'  => (int) $o->OBR_CON,
            'contratista_nombre' => trim((string) $o->OBR_COND),
            'notas'        => trim((string) $o->OBR_NOT),
            'usuario'      => trim((string) $o->OBR_USU),
            'terminal'     => trim((string) $o->OBR_TER),
            'empleados'    => $empleados,
        ]);
    }

    /**
     * Modifica una obra (obras_modificar): actualiza datos, reemplaza los obreros
     * vinculados y habilita la entrada de los marcados.
     * @route PUT /api/obras/{cod}
     */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'descripcion'  => 'required|string|max:200',
            'fecha_inicio' => 'required|date',
            'fecha_final'  => 'nullable|date|after_or_equal:fecha_inicio',
            'contratista'  => 'required|integer',
            'notas'        => 'nullable|string',
            'empleados'    => 'required|array|min:1',   // `unico` de los obreros marcados
            'empleados.*'  => 'integer',
        ], [
            'descripcion.required'   => 'No puede modificar una obra sin descripción.',
            'fecha_final.after_or_equal' => 'Error en la fecha de finalización: no puede ser anterior a la de inicio.',
            'empleados.required'     => 'Al menos un obrero debe trabajar en la obra.',
            'empleados.min'          => 'Al menos un obrero debe trabajar en la obra.',
        ]);

        $obra = DB::table('obras')->where('OBR_COD', $cod)->first();
        if (!$obra) return response()->json(['message' => 'Obra no encontrada.'], 404);

        $contratista = DB::table('contnom')->where('CTR_COD', $d['contratista'])->first();
        $nombreCon = $contratista ? trim((string) $contratista->CTR_NOM) : '';
        $usuario  = strtoupper(substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30));
        $terminal = substr($this->nombreTerminal($request), 0, 30);

        DB::transaction(function () use ($d, $cod, $nombreCon, $usuario, $terminal) {
            DB::table('obras')->where('OBR_COD', $cod)->update([
                'OBR_DET'  => trim($d['descripcion']),
                'OBR_NOT'  => trim((string) ($d['notas'] ?? '')),
                'OBR_FIN'  => $d['fecha_inicio'],
                'OBR_FCI'  => !empty($d['fecha_final']) ? $d['fecha_final'] : '1900-01-01',
                'OBR_CON'  => (int) $d['contratista'],
                'OBR_COND' => $nombreCon,
                'OBR_USU'  => $usuario,
                'OBR_TER'  => $terminal,
            ]);

            // Reemplaza los obreros vinculados (borra todos y reinserta los marcados)
            DB::table('obras_emp')->where('OEM_OBR', $cod)->delete();
            foreach (array_unique(array_map('intval', $d['empleados'])) as $unico) {
                DB::table('obras_emp')->insert(Registro::completar('obras_emp', [
                    'OEM_OBR' => $cod, 'OEM_EMP' => $unico,
                ]));
                DB::table('contemp')->where('unico', $unico)->update(['CEM_EST' => 'P']);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Obras - Listados (obras_listados / informe_obras).
     * @route GET /api/obras/listado?cuantas=1|2&obra=&estado=1|2&orden=1|2
     *   cuantas 1=todas, 2=una (obra=OBR_COD). estado 1=histórico, 2=solo habilitadas (vigentes).
     *   orden 1=contratista, 2=código.
     */
    public function listado(Request $request): JsonResponse
    {
        $cuantas = (int) $request->input('cuantas', 1);
        $obra    = (int) $request->input('obra', 0);
        $estado  = (int) $request->input('estado', 1);
        $orden   = (int) $request->input('orden', 1);
        $hoy     = now()->startOfDay();

        $q = DB::table('obras')->when($cuantas === 2 && $obra > 0, fn ($w) => $w->where('OBR_COD', $obra));
        if ($estado === 2) {
            $q = $this->soloVigentes($q, $hoy);
        }
        $q->orderBy($orden === 2 ? 'OBR_COD' : 'OBR_COND');
        $obras = $q->get();

        $resultado = [];
        foreach ($obras as $o) {
            $vigente = $this->esVigente($o, $hoy);
            $emps = DB::table('obras_emp as oe')->join('contemp as c', 'c.unico', '=', 'oe.OEM_EMP')
                ->where('oe.OEM_OBR', $o->OBR_COD)->orderBy('c.CEM_NOM')
                ->get(['c.CEM_COD', 'c.CEM_NOM', 'c.CEM_DNI', 'c.CEM_CUI', 'c.CEM_TEL', 'c.CEM_CEL', 'c.CEM_VART'])
                ->map(fn ($e) => [
                    'cod'      => (int) $e->CEM_COD,
                    'nombre'   => trim((string) $e->CEM_NOM),
                    'dni'      => trim((string) $e->CEM_DNI),
                    'cuil'     => (string) (int) $e->CEM_CUI,
                    'telefono' => trim((string) $e->CEM_TEL),
                    'celular'  => trim((string) $e->CEM_CEL),
                    'venc_art' => $this->fecha($e->CEM_VART),
                    'estado'   => $vigente ? 'P' : 'D',
                ]);
            $resultado[] = [
                'cod'         => (int) $o->OBR_COD,
                'contratista' => trim((string) $o->OBR_COND),
                'descripcion' => trim((string) $o->OBR_DET),
                'notas'       => trim((string) $o->OBR_NOT),
                'inicio'      => $this->fecha($o->OBR_FIN),
                'final'       => $this->fecha($o->OBR_FCI),
                'usuario'     => trim((string) $o->OBR_USU),
                'vigente'     => $vigente,
                'empleados'   => $emps,
            ];
        }

        return response()->json(['obras' => $resultado]);
    }

    /**
     * Obras - Habilitados a Ingresar (obras_accesos / informe_accesos).
     * Lista los empleados que figuran en obras y si tienen acceso PERMITIDO
     * (están en al menos una obra vigente) o DENEGADO.
     * @route GET /api/obras/accesos
     */
    public function accesos(): JsonResponse
    {
        $hoy = now()->startOfDay();

        // Empleados distintos (por `unico`) que figuran en alguna obra
        $emps = DB::table('obras_emp as oe')
            ->join('obras as o', 'o.OBR_COD', '=', 'oe.OEM_OBR')
            ->join('contemp as c', 'c.unico', '=', 'oe.OEM_EMP')
            ->orderBy('o.OBR_COND')->orderBy('c.CEM_NOM')
            ->get(['o.OBR_COND', 'c.CEM_NOM', 'c.CEM_DNI', 'c.unico'])
            ->unique('unico')->values();

        // `unico` de empleados que están en alguna obra vigente
        $vigentesQ = $this->soloVigentes(DB::table('obras as o')
            ->join('obras_emp as oe', 'oe.OEM_OBR', '=', 'o.OBR_COD'), $hoy, 'o');
        $conAcceso = $vigentesQ->pluck('oe.OEM_EMP')->map(fn ($v) => (int) $v)->unique()->flip();

        $filas = $emps->map(fn ($e) => [
            'contratista' => trim((string) $e->OBR_COND),
            'nombre'      => trim((string) $e->CEM_NOM),
            'dni'         => trim((string) $e->CEM_DNI),
            'acceso'      => isset($conAcceso[(int) $e->unico]) ? 'PERMITIDO' : 'DENEGADO',
        ])->values();

        return response()->json($filas);
    }

    /** Obra en ejecución = no finalizó: OBR_FCI vacía/1900 o futura (tiene inicio). Ver nota de cabecera. */
    private function esVigente($o, $hoy): bool
    {
        $fin = \Carbon\Carbon::parse($o->OBR_FCI);
        return $fin->year <= 1900 || $fin->gte($hoy);
    }

    /** Agrega a un query el filtro de obras en ejecución (sin fecha de fin o fin futura). $alias = alias de obras. */
    private function soloVigentes($q, $hoy, string $alias = '')
    {
        $p = $alias !== '' ? $alias . '.' : '';
        return $q->where(fn ($w) => $w->whereDate($p . 'OBR_FCI', '<=', '1900-01-01')
            ->orWhereDate($p . 'OBR_FCI', '>=', $hoy->toDateString()));
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('Y-m-d');
    }

    /** Nombre de la terminal: header X-Terminal o IP del request. */
    private function nombreTerminal(Request $request): string
    {
        $declarado = trim((string) $request->header('X-Terminal', ''));
        if ($declarado !== '') {
            return strtoupper($declarado);
        }
        return strtoupper((string) $request->ip());
    }
}
