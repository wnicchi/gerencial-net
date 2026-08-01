<?php

namespace App\Http\Controllers;

use App\Services\BibliotecaDigitalService;
use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RelojFaltasController — Reloj / Ajuste Faltas Diarias (reloj_faltas_diarias.scx).
 *
 * Carga una falta/licencia de un empleado para un rango de fechas (tabla reloj_faltas_diarias). El tipo de
 * licencia se elige de la tabla de licencias. UNICO es identity.
 *
 * Documentación asociada: se guarda en la biblioteca digital (tabla documentos, DOC_TIP='L',
 * DOC_REF = UNICO de la falta), con el mismo mecanismo que Exámenes Médicos.
 */
class RelojFaltasController extends Controller
{
    private const EXT_BLOQUEADAS = ['EXE', 'BAT', 'DLL', 'ZIP', 'RAR', 'CMD', 'CAB'];

    /** @route GET /api/reloj/faltas/licencias — catálogo de licencias y tipos de documento. */
    public function licencias(): JsonResponse
    {
        $filas = DB::table('licencias')->orderBy('lic_cod')->get(['lic_cod', 'lic_det'])
            ->map(fn ($l) => ['cod' => (int) $l->lic_cod, 'detalle' => trim((string) $l->lic_det)])->values();
        // Tipos de documento para las licencias (TDO_TIP='L': ENFERMEDAD, OTROS, …).
        $tiposDoc = DB::table('tipo_doc')->where('TDO_TIP', 'L')->orderBy('TDO_DES')->get(['TDO_COD', 'TDO_DES'])
            ->map(fn ($t) => ['cod' => trim((string) $t->TDO_COD), 'nombre' => trim((string) $t->TDO_DES)])->values();
        return response()->json(['licencias' => $filas, 'tipos_doc' => $tiposDoc]);
    }

    /** @route POST /api/reloj/faltas — graba el ajuste por falta diaria. */
    public function confirmar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cod'           => 'required|integer',
            'fecha1'        => 'required|date',
            'fecha2'        => 'required|date',
            'licencia'      => 'required|integer',
            'observacion'   => 'nullable|string',
            'permiso_cod'   => 'nullable|integer',  // permiso laboral que se está "usando"
        ]);
        $cod = (int) $d['cod'];
        $f1 = \Carbon\Carbon::parse($d['fecha1'])->startOfDay();
        $f2 = \Carbon\Carbon::parse($d['fecha2'])->startOfDay();
        $lic = (int) $d['licencia'];

        if ($f1->gt($f2)) return response()->json(['message' => "La fecha 'Desde' no puede ser mayor a la fecha 'Hasta'."], 422);
        if ($lic === 0) return response()->json(['message' => 'Código de licencia no válido.'], 422);

        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_AOP']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 422);
        if (trim((string) $p->PER_AOP) !== 'A') {
            return response()->json(['message' => 'No se pueden cargar faltas: el empleado está dado de baja.'], 422);
        }
        $licDet = DB::table('licencias')->where('lic_cod', $lic)->value('lic_det');
        if ($licDet === null) return response()->json(['message' => 'Código de licencia no válido.'], 422);

        $unico = (int) DB::table('reloj_faltas_diarias')->insertGetId(Registro::completar('reloj_faltas_diarias', [
            'AFD_PER' => $cod, 'AFD_NOM' => trim((string) $p->PER_NOM),
            'AFD_LIC' => $lic, 'AFD_LID' => trim((string) $licDet),
            'AFD_FE1' => $f1->format('Y-m-d'), 'AFD_FE2' => $f2->format('Y-m-d'),
            'AFD_OBS' => substr(trim((string) ($d['observacion'] ?? '')), 0, 200),
        ]), 'UNICO');

        // Si la falta se cargó a partir de un permiso pendiente, marcarlo como usado.
        $mensaje = 'Ajuste por falta diaria grabado correctamente.';
        if (!empty($d['permiso_cod'])) {
            $usuario = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 50);
            DB::connection('gestion')->table('PERMISOS_LABORALES')
                ->where('pla_cod', (int) $d['permiso_cod'])->where('pla_est', 0)
                ->update(['PLA_EST' => 1, 'pla_fconfirma' => now()->format('Y-m-d H:i:s'), 'pla_rconfirma' => $usuario]);
            $mensaje = 'Falta grabada y permiso marcado como usado.';
        }

        return response()->json(['message' => $mensaje, 'unico' => $unico]);
    }

    /**
     * @route PUT /api/reloj/faltas/{unico} — modifica una falta cargada.
     * Solo cambia tipo de licencia y observación (NO las fechas ni el empleado).
     */
    public function actualizar(Request $request, int $unico): JsonResponse
    {
        $d = $request->validate([
            'licencia'    => 'required|integer',
            'observacion' => 'nullable|string',
        ]);
        $falta = DB::table('reloj_faltas_diarias')->where('UNICO', $unico)->first(['UNICO', 'AFD_PER']);
        if (!$falta) return response()->json(['message' => 'La falta no existe o ya fue eliminada.'], 404);

        $lic = (int) $d['licencia'];
        $licDet = DB::table('licencias')->where('lic_cod', $lic)->value('lic_det');
        if ($licDet === null) return response()->json(['message' => 'Código de licencia no válido.'], 422);

        DB::table('reloj_faltas_diarias')->where('UNICO', $unico)->update([
            'AFD_LIC' => $lic, 'AFD_LID' => trim((string) $licDet),
            'AFD_OBS' => substr(trim((string) ($d['observacion'] ?? '')), 0, 200),
        ]);

        return response()->json(['message' => 'Falta modificada correctamente.']);
    }

    // ── Edición Faltas Diarias (reloj_faltas_diarias_editar.scx) ─────

    /** @route GET /api/reloj/faltas/edicion?desde=&hasta=&empresa=&cod= */
    public function edicion(Request $request): JsonResponse
    {
        $d = $request->validate([
            'desde' => 'nullable|date', 'hasta' => 'nullable|date',
            'empresa' => 'nullable|integer', 'cod' => 'nullable|integer',
        ]);
        $desde = !empty($d['desde']) ? \Carbon\Carbon::parse($d['desde'])->format('Y-m-d') : null;
        $hasta = !empty($d['hasta']) ? \Carbon\Carbon::parse($d['hasta'])->format('Y-m-d') : null;

        $q = DB::table('reloj_faltas_diarias as a')->join('personal as p', 'a.AFD_PER', '=', 'p.PER_COD');
        if ($desde && $hasta) {
            $q->where(function ($w) use ($desde, $hasta) {
                $w->where(fn ($x) => $x->whereDate('a.AFD_FE1', '<=', $desde)->whereDate('a.AFD_FE2', '>=', $desde))
                  ->orWhere(fn ($x) => $x->whereDate('a.AFD_FE1', '<=', $hasta)->whereDate('a.AFD_FE2', '>=', $hasta));
            });
        }
        if (!empty($d['empresa'])) $q->where('p.PER_EMP', (int) $d['empresa']);
        if (!empty($d['cod'])) $q->where('a.AFD_PER', (int) $d['cod']);

        $docs = DB::table('documentos')->where('DOC_TIP', 'L')->pluck('DOC_REF')->map(fn ($x) => (int) $x)->flip();

        $filas = $q->orderBy('a.AFD_PER')->orderBy('a.AFD_FE1')
            ->get(['a.UNICO', 'a.AFD_PER', 'a.AFD_NOM', 'a.AFD_LIC', 'a.AFD_LID', 'a.AFD_FE1', 'a.AFD_FE2', 'a.AFD_OBS'])
            ->map(fn ($r) => [
                'unico' => (int) $r->UNICO, 'cod' => (int) $r->AFD_PER, 'nombre' => trim((string) $r->AFD_NOM),
                'lic' => (int) $r->AFD_LIC, 'detalle' => trim((string) $r->AFD_LID),
                'desde' => \Carbon\Carbon::parse($r->AFD_FE1)->format('Y-m-d'), 'hasta' => \Carbon\Carbon::parse($r->AFD_FE2)->format('Y-m-d'),
                'obs' => trim((string) $r->AFD_OBS), 'conDocu' => $docs->has((int) $r->UNICO), 'esVacacion' => false, 'sel' => false,
            ])->values()->all();

        // Inyectar vacaciones de los empleados presentes (como el FoxPro).
        if ($desde && $hasta) {
            $cods = array_unique(array_column($filas, 'cod'));
            foreach ($cods as $cod) {
                $vac = DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_VTR', 0)
                    ->where(function ($w) use ($desde, $hasta) {
                        $w->whereBetween('VAC_FDE', [$desde, $hasta])->orWhereBetween('VAC_FHA', [$desde, $hasta]);
                    })->get(['VAC_PER', 'VAC_NOM', 'VAC_FDE', 'VAC_FHA']);
                foreach ($vac as $v) {
                    $filas[] = [
                        'unico' => 0, 'cod' => (int) $v->VAC_PER, 'nombre' => trim((string) $v->VAC_NOM), 'lic' => 0, 'detalle' => '',
                        'desde' => \Carbon\Carbon::parse($v->VAC_FDE)->format('Y-m-d'), 'hasta' => \Carbon\Carbon::parse($v->VAC_FHA)->format('Y-m-d'),
                        'obs' => 'VACACIONES', 'conDocu' => false, 'esVacacion' => true, 'sel' => false,
                    ];
                }
            }
        }

        return response()->json(['total' => count($filas), 'faltas' => array_values($filas)]);
    }

    /** @route POST /api/reloj/faltas/edicion/eliminar — borra las faltas seleccionadas por UNICO. */
    public function edicionEliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['unicos' => 'required|array|min:1', 'unicos.*' => 'integer']);
        $unicos = array_filter(array_map('intval', $d['unicos']));
        if (!$unicos) return response()->json(['message' => 'No hay faltas reales seleccionadas.'], 422);
        $n = DB::table('reloj_faltas_diarias')->whereIn('UNICO', $unicos)->delete();
        return response()->json(['message' => "Faltas eliminadas ($n).", 'eliminados' => $n]);
    }

    // ── Listados Faltas Diarias (reloj_faltas_diarias_listados.scx) ──

    /** @route GET /api/reloj/faltas/listados */
    public function listados(Request $request): JsonResponse
    {
        $d = $request->validate([
            'periodo' => 'nullable|in:historico,rango', 'desde' => 'nullable|date', 'hasta' => 'nullable|date',
            'cod' => 'nullable|integer', 'empresa' => 'nullable|integer',
            'lugar' => 'nullable|integer', 'convenio' => 'nullable|integer', 'sector' => 'nullable|integer', 'subsector' => 'nullable|integer',
            'licencias' => 'nullable|array', 'licencias.*' => 'integer',
            'agrupar' => 'nullable|boolean', 'incluyeVacas' => 'nullable|boolean',
        ]);
        $rango = ($d['periodo'] ?? 'historico') === 'rango';
        $m1 = $rango && !empty($d['desde']) ? \Carbon\Carbon::parse($d['desde'])->startOfDay() : \Carbon\Carbon::create(2001, 1, 1);
        $m2 = $rango && !empty($d['hasta']) ? \Carbon\Carbon::parse($d['hasta'])->startOfDay() : now()->startOfDay();
        $lics = array_map('intval', $d['licencias'] ?? []);

        $q = DB::table('reloj_faltas_diarias as a')->join('personal as p', 'a.AFD_PER', '=', 'p.PER_COD');
        foreach ([['empresa', 'p.PER_EMP'], ['lugar', 'p.PER_LUGAR'], ['convenio', 'p.PER_CON'], ['sector', 'p.PER_SEC'], ['subsector', 'p.PER_SUC']] as [$k, $col]) {
            if (!empty($d[$k])) $q->where($col, (int) $d[$k]);
        }
        if (!empty($d['cod'])) $q->where('a.AFD_PER', (int) $d['cod']);
        if ($rango) {
            $q->where(function ($w) use ($m1, $m2) {
                $w->whereBetween('a.AFD_FE1', [$m1->format('Y-m-d'), $m2->format('Y-m-d')])
                  ->orWhere(fn ($x) => $x->whereDate('a.AFD_FE1', '<=', $m1->format('Y-m-d'))->whereDate('a.AFD_FE2', '>=', $m1->format('Y-m-d')))
                  ->orWhere(fn ($x) => $x->whereDate('a.AFD_FE1', '<=', $m2->format('Y-m-d'))->whereDate('a.AFD_FE2', '>=', $m2->format('Y-m-d')));
            });
        }
        if ($lics) $q->whereIn('a.AFD_LIC', $lics);

        $docs = DB::table('documentos')->where('DOC_TIP', 'L')->pluck('DOC_REF')->map(fn ($x) => (int) $x)->flip();

        $filas = $q->orderBy('p.PER_NOM')->orderBy('a.AFD_PER')->orderBy('a.AFD_FE1', 'desc')
            ->get(['a.UNICO', 'a.AFD_PER', 'a.AFD_NOM', 'a.AFD_LIC', 'a.AFD_LID', 'a.AFD_FE1', 'a.AFD_FE2', 'a.AFD_OBS', 'p.PER_NOM', 'p.PER_LEG'])
            ->map(fn ($r) => [
                'unico' => (int) $r->UNICO, 'cod' => (int) $r->AFD_PER, 'legajo' => (int) $r->PER_LEG, 'nombre' => trim((string) $r->PER_NOM),
                'lic' => (int) $r->AFD_LIC, 'detalle' => trim((string) $r->AFD_LID),
                'desde' => \Carbon\Carbon::parse($r->AFD_FE1)->format('Y-m-d'), 'hasta' => \Carbon\Carbon::parse($r->AFD_FE2)->format('Y-m-d'),
                'obs' => trim((string) $r->AFD_OBS), 'conDocu' => $docs->has((int) $r->UNICO),
                'dias' => $this->dias($r->AFD_FE1, $r->AFD_FE2, $m1, $m2),
            ])->values()->all();

        // Vacaciones de los empleados presentes (si se incluyen).
        if ($d['incluyeVacas'] ?? false) {
            $emps = [];
            foreach ($filas as $f) $emps[$f['cod']] = ['legajo' => $f['legajo'], 'nombre' => $f['nombre']];
            foreach ($emps as $cod => $info) {
                $vq = DB::table('vacaciones')->where('VAC_PER', $cod)->where('VAC_VTR', 0);
                if ($rango) $vq->where(fn ($w) => $w->whereBetween('VAC_FDE', [$m1->format('Y-m-d'), $m2->format('Y-m-d')])->orWhereBetween('VAC_FHA', [$m1->format('Y-m-d'), $m2->format('Y-m-d')]));
                foreach ($vq->get(['VAC_PER', 'VAC_NOM', 'VAC_FDE', 'VAC_FHA']) as $v) {
                    $filas[] = [
                        'unico' => 0, 'cod' => (int) $v->VAC_PER, 'legajo' => $info['legajo'], 'nombre' => $info['nombre'],
                        'lic' => 0, 'detalle' => 'VACACIONES',
                        'desde' => \Carbon\Carbon::parse($v->VAC_FDE)->format('Y-m-d'), 'hasta' => \Carbon\Carbon::parse($v->VAC_FHA)->format('Y-m-d'),
                        'obs' => 'VACACIONES', 'conDocu' => false, 'dias' => $this->dias($v->VAC_FDE, $v->VAC_FHA, $m1, $m2),
                    ];
                }
            }
        }
        usort($filas, fn ($a, $b) => [$a['nombre'], $a['cod'], $b['desde']] <=> [$b['nombre'], $b['cod'], $a['desde']]);

        // Agrupado por empleado + tipo.
        $agrupado = [];
        if ($d['agrupar'] ?? false) {
            foreach ($filas as $f) {
                $k = $f['cod'] . '|' . $f['lic'];
                $agrupado[$k] ??= ['cod' => $f['cod'], 'nombre' => $f['nombre'], 'lic' => $f['lic'], 'detalle' => $f['detalle'], 'cantidad' => 0];
                $agrupado[$k]['cantidad'] += $f['dias'];
            }
            $agrupado = array_values($agrupado);
            usort($agrupado, fn ($a, $b) => [$a['nombre'], $a['detalle']] <=> [$b['nombre'], $b['detalle']]);
        }

        return response()->json(['detalle' => $filas, 'agrupado' => $agrupado, 'rango' => $rango,
            'desde' => $m1->format('Y-m-d'), 'hasta' => $m2->format('Y-m-d')]);
    }

    /** Días de una falta acotada al rango (mínimo 1). */
    private function dias($fe1, $fe2, \Carbon\Carbon $m1, \Carbon\Carbon $m2): int
    {
        $fd = \Carbon\Carbon::parse($fe1)->startOfDay(); $fh = \Carbon\Carbon::parse($fe2)->startOfDay();
        if ($fd->lt($m1)) $fd = $m1->copy();
        if ($fh->gt($m2)) $fh = $m2->copy();
        $dif = $fd->diffInDays($fh, false);
        return $dif > 0 ? $dif + 1 : 1;
    }

    // ── Documentación de la falta (biblioteca digital, DOC_TIP='L') ──

    /** @route GET /api/reloj/faltas/{unico}/documentos — documentos de una falta. */
    public function documentos(int $unico): JsonResponse
    {
        return response()->json($this->docsFalta($unico));
    }

    /** @route POST /api/reloj/faltas/{unico}/documento — adjunta un documento a la falta. */
    public function agregarDocumento(Request $request, int $unico): JsonResponse
    {
        $d = $request->validate([
            'doc_tipo'  => 'required|string|max:2',
            'doc_fecha' => 'required|date',
            'doc_obs'   => 'nullable|string|max:60',
            'archivo'   => 'required|file|max:51200',
        ]);
        $falta = DB::table('reloj_faltas_diarias')->where('UNICO', $unico)->first(['AFD_PER', 'AFD_LIC', 'AFD_FE1']);
        if (!$falta) return response()->json(['message' => 'La falta no existe.'], 404);

        $file = $request->file('archivo');
        $ext  = mb_strtoupper((string) $file->getClientOriginalExtension());
        if ($ext === '' || in_array($ext, self::EXT_BLOQUEADAS, true)) {
            return response()->json(['message' => 'Extensión de archivo no válida.'], 422);
        }
        try {
            $this->guardarDocumento($file, $unico, mb_strtoupper(trim($d['doc_tipo'])), $d['doc_fecha'],
                (string) ($d['doc_obs'] ?? ''), (int) $falta->AFD_PER, (int) $falta->AFD_LIC, substr((string) $falta->AFD_FE1, 0, 10), $request);
        } catch (\Throwable $e) {
            Log::error("[faltaAddDoc] falta {$unico}: " . $e->getMessage());
            return response()->json(['message' => 'No se pudo guardar el archivo en la biblioteca digital.'], 500);
        }
        return response()->json(['ok' => true, 'documentos' => $this->docsFalta($unico)]);
    }

    /** @route GET /api/reloj/faltas/documento/{id}/ver — visualiza un documento. */
    public function verDocumento(int $id)
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'L')->first();
        if (!$doc) return response()->json(['error' => 'Documento no encontrado'], 404);
        $resp = (new BibliotecaDigitalService())->archivoDigitalVisualizar(config('rrhh.docs_sistema'), 'LICENCIAS', $this->identDoc($doc));
        return $resp ?? response()->json(['error' => 'El archivo no está en la biblioteca digital'], 404);
    }

    /** @route DELETE /api/reloj/faltas/documento/{id} — elimina un documento de la falta. */
    public function eliminarDocumento(int $id): JsonResponse
    {
        $doc = DB::table('documentos')->where('UNICO', $id)->where('DOC_TIP', 'L')->first();
        if (!$doc) return response()->json(['message' => 'Documento no encontrado.'], 404);
        try { (new BibliotecaDigitalService())->archivoDigitalEliminar(config('rrhh.docs_sistema'), 'LICENCIAS', $this->identDoc($doc)); }
        catch (\Throwable $e) { Log::warning("[faltaDelDoc] {$id}: " . $e->getMessage()); }
        DB::table('documentos')->where('UNICO', $id)->delete();
        return response()->json(['ok' => true, 'documentos' => $this->docsFalta((int) $doc->DOC_REF)]);
    }

    /** Documentos de una falta (DOC_TIP='L', DOC_REF=falta). */
    private function docsFalta(int $unico): array
    {
        return DB::table('documentos')->where('DOC_REF', $unico)->where('DOC_TIP', 'L')->orderByDesc('DOC_FEC')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->UNICO, 'nro' => (int) $r->DOC_ORD, 'tipo' => trim((string) $r->DOC_TDO),
                'detalle' => trim((string) $r->DOC_TDD), 'nombre' => trim((string) $r->DOC_NOM),
                'ext' => trim((string) $r->DOC_EXT), 'fecha' => $r->DOC_FEC ? substr((string) $r->DOC_FEC, 0, 10) : '',
                'creado' => trim((string) $r->DOC_CRE), 'observaciones' => trim((string) $r->DOC_OBS),
            ])->values()->all();
    }

    /**
     * Identificación del archivo en la biblioteca digital (proceso LICENCIAS), tal como
     * lo nombra FoxPro: DOC_ORD + DOC_NRO(5) + DOC_REF(6) + EXT, sin punto.
     * Ej.: ORD=1709 NRO=1 REF=7013 EXT=JPEG → "170900001007013JPEG".
     */
    private function identDoc(object $doc): string
    {
        return (string) (int) $doc->DOC_ORD
            . str_pad((string) (int) $doc->DOC_NRO, 5, '0', STR_PAD_LEFT)
            . str_pad((string) (int) $doc->DOC_REF, 6, '0', STR_PAD_LEFT)
            . mb_strtoupper(trim((string) $doc->DOC_EXT));
    }

    /** Guarda el archivo en la biblioteca digital y registra la fila en documentos (DOC_TIP='L'). */
    private function guardarDocumento($file, int $faltaUnico, string $tipo, string $fecha, string $obs, int $emp, int $lic, string $fecFalta, Request $request): void
    {
        $extArch = mb_strtoupper((string) $file->getClientOriginalExtension());
        $tdd     = trim((string) DB::table('tipo_doc')->where('TDO_COD', $tipo)->where('TDO_TIP', 'L')->value('TDO_DES'));
        $nombre  = mb_strtoupper(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $tam     = (int) $file->getSize();
        $ahora   = now();
        $usuario  = mb_substr(trim((string) ($request->user()->NOMBRE ?? 'RRHH.NET')), 0, 20);
        $terminal = mb_substr(gethostname() ?: 'RRHH.NET', 0, 20);

        $docOrd = (int) DB::table('documentos')->max('DOC_ORD') + 1;
        $docNro = (int) DB::table('documentos')->where('DOC_TDO', $tipo)->max('DOC_NRO') + 1;

        DB::table('documentos')->insert([
            'DOC_ORD' => $docOrd, 'DOC_TDO' => $tipo, 'DOC_NRO' => $docNro, 'DOC_FEC' => substr($fecha, 0, 10),
            'DOC_TDD' => mb_substr($tdd, 0, 30), 'DOC_TIP' => 'L', 'DOC_REF' => $faltaUnico,
            'DOC_DET' => mb_substr("Licencia/Falta: Emp.{$emp} Lic.{$lic} {$fecFalta}", 0, 60),
            'DOC_FUL' => mb_substr((string) $file->getClientOriginalName(), 0, 120), 'DOC_DIR' => '',
            'DOC_NOM' => mb_substr($nombre, 0, 120), 'DOC_CRE' => $ahora->format('d\m\Y H:i'),
            'DOC_TAM' => $tam, 'DOC_KB' => (int) round($tam / 1024), 'DOC_EXT' => mb_substr($extArch, 0, 5),
            'DOC_UBI' => 'en SQL DOCUMENTOS DIGITALES', 'DOC_OBS' => mb_substr(trim($obs), 0, 60),
            'DOC_TER' => $terminal, 'DOC_USU' => $usuario, 'DOC_GRA' => $ahora->format('d\m\y H:i'),
        ]);

        // Nombre en la biblioteca = DOC_ORD + DOC_NRO(5) + DOC_REF(6) + EXT (convención FoxPro, proceso LICENCIAS).
        $referencia = (string) $docOrd . str_pad((string) $docNro, 5, '0', STR_PAD_LEFT)
            . str_pad((string) $faltaUnico, 6, '0', STR_PAD_LEFT) . $extArch;
        (new BibliotecaDigitalService())->archivoDigitalGuardar(
            config('rrhh.docs_sistema'), 'LICENCIAS', $referencia, $extArch,
            file_get_contents($file->getRealPath()), $usuario
        );
    }
}
