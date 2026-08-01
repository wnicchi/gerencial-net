<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AlertaController — pantalla de Alertas. Corre una serie de análisis sobre
 * varias tablas y devuelve las alertas agrupadas en secciones. Se muestra en el
 * menú y también al iniciar sesión.
 *
 * Réplica del proceso FoxPro (alertas.scx). Implementado:
 *  - Permisos pendientes de procesar (base de Gestión)
 *  - Ropa sin entregar por convenio
 *  - Cumplen décadas de servicio (10..50) y 25 años
 *  - Cerca de cumplir el plazo de prueba
 *  - Falta cargar fecha de ingreso efectivo
 *  - Cumpleaños (fin de semana pasado si es lunes / hoy / mañana / pasado mañana)
 *  - Próximas vacaciones
 *  - Multas pagadas en los últimos 45 días (base de Gestión)
 *  - Vencimiento de licencias
 *  - Exámenes vencidos último año + próximos (SOLO Logística, agrupados por tipo)
 *  - Embargos pendientes (LIQ_ITE 612/601 no autorizados en Gestión)
 */
class AlertaController extends Controller
{
    /** @route GET /api/alertas */
    public function index(): JsonResponse
    {
        $hoy = Carbon::today();
        $sec = [];
        // En Fox: SISTEMA_RRHH_SILCAR=.T. (Autoelevadores) / =.F. (Logística).
        // Los exámenes solo se calculan en Logística.
        $esLogistica = config('rrhh.docs_sistema') === 'RRHHLOG';

        $activos = DB::table('personal')->where('PER_AOP', 'A')
            ->get(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_CON', 'PER_ING', 'PER_FNA', 'PER_FEFECTIVO']);

        // ── Permisos pendientes (base de Gestión) ──
        try {
            $perm = DB::connection('gestion')->table('PERMISOS_LABORALES')
                ->where('pla_est', 0)->orderByDesc('pla_cod')->get();
            $l = [];
            foreach ($perm as $p) {
                $l[] = "(Pedido {$p->pla_cod} de " . trim((string) $p->pla_responsable) . ")  ->  Para el "
                    . $this->dc($p->pla_fec_permiso) . " " . strtoupper(trim((string) $p->pla_emd))
                    . " --> TIPO " . trim((string) $p->pla_falta) . " / " . trim(mb_substr((string) $p->pla_obs, 0, 100));
            }
            if ($l) $sec[] = ['titulo' => 'PERMISOS PENDIENTES DE PROCESAR', 'lineas' => $l];
        } catch (\Throwable $e) { /* Gestión no disponible */ }

        // ── Ropa sin entregar por convenio ──
        try {
            $epp = [];
            foreach (DB::table('convenio_epp')->get(['conrop_con', 'conrop_rop', 'conrop_dias']) as $r) {
                $epp[((int) $r->conrop_con) . '|' . ((int) $r->conrop_rop)] = (int) $r->conrop_dias;
            }
            if ($epp) {
                // Última entrega por (empleado, CÓDIGO de prenda) — como Fox, SIN partir por
                // descripción (la misma prenda puede tener distintas descripciones). ROW_NUMBER
                // deja una sola fila por (PER_COD, ENR_ROP): la de la fecha más reciente.
                $sub = DB::table('entregaropa as b')
                    ->join('personal as a', DB::raw('LTRIM(RTRIM(a.PER_CUI))'), '=', DB::raw('LTRIM(RTRIM(b.ENR_CUIL))'))
                    ->join('ropa as r', DB::raw('LTRIM(RTRIM(r.ROP_COD))'), '=', DB::raw('LTRIM(RTRIM(b.ENR_ROP))'))
                    ->where('a.PER_AOP', 'A')
                    ->select([
                        'a.PER_COD as cod', DB::raw('LTRIM(RTRIM(a.PER_NOM)) as nom'), 'a.PER_CON as con',
                        'b.ENR_ROP as rop', DB::raw('LTRIM(RTRIM(b.ENR_DES)) as des'),
                        DB::raw('CAST(b.ENR_FEC AS DATE) as fec'),
                        DB::raw('ROW_NUMBER() OVER (PARTITION BY a.PER_COD, b.ENR_ROP ORDER BY CAST(b.ENR_FEC AS DATE) DESC) as rn'),
                    ]);
                $rows = DB::query()->fromSub($sub, 't')->where('rn', 1)->get();
                $l = [];
                foreach ($rows as $r) {
                    $key = ((int) $r->con) . '|' . ((int) $r->rop);
                    if (!isset($epp[$key]) || !$r->fec || substr((string) $r->fec, 0, 10) === '1900-01-01') continue;
                    $dias = Carbon::parse(substr((string) $r->fec, 0, 10))->diffInDays($hoy, false);
                    if ($dias > $epp[$key]) {
                        $l[] = $this->pad('(' . (int) $r->cod . ') ' . $r->nom, 40)
                            . ' --> EPP (' . (int) $r->rop . ') ' . $r->des
                            . ' ....Atrasado ' . ($dias - $epp[$key]) . ' días';
                    }
                }
                if ($l) $sec[] = ['titulo' => 'ROPA SIN ENTREGAR POR CONVENIO', 'lineas' => $l];
            }
        } catch (\Throwable $e) { /* sin datos */ }

        // ── Décadas de servicio (10..50) y 25 años ──
        $decadas = []; $a25 = [];
        foreach ($activos as $e) {
            $ing = $this->fecha($e->PER_ING);
            if (!$ing) continue;
            for ($d = 0; $d <= 30; $d++) {
                $f = $hoy->copy()->addDays($d);
                if ($f->month === $ing->month && $f->day === $ing->day) {
                    $años = $f->year - $ing->year;
                    if ($años > 0 && $años % 10 === 0 && $años <= 50) {
                        $decadas[] = $this->pad(trim((string) $e->PER_NOM), 30) . ' -> el ' . $f->format('d/m/Y') . " cumple {$años} años.";
                    }
                    if ($años === 25) {
                        $a25[] = $this->pad(trim((string) $e->PER_NOM), 30) . ' -> el ' . $f->format('d/m/Y') . ' cumple 25 años.';
                    }
                    break;
                }
            }
        }
        if ($decadas) $sec[] = ['titulo' => 'CUMPLEN DECADAS DE SERVICIOS DESDE SU FECHA DE INGRESO', 'lineas' => $decadas];
        if ($a25) $sec[] = ['titulo' => 'CUMPLEN 25 AÑOS DE SERVICIOS DESDE SU FECHA DE INGRESO', 'lineas' => $a25];

        // ── Cerca de cumplir el plazo de prueba (ventana 165..195 días) ──
        $l = [];
        $corte = Carbon::create(2024, 7, 9);
        foreach ($activos as $e) {
            $ing = $this->fecha($e->PER_ING);
            if (!$ing) continue;
            if ($hoy->gt($ing->copy()->addDays(165)) && $hoy->lt($ing->copy()->addDays(195))) {
                $plazo = $ing->lte($corte) ? 90 : 180;
                $fpl = $ing->copy()->addDays($plazo);
                $nom = $this->pad(trim((string) $e->PER_NOM), 30);
                $l[] = $hoy->gt($fpl)
                    ? "$nom -> el " . $fpl->format('d/m/Y') . ' YA PASO el PLAZO DE PRUEBA, este aviso saldra por 15 días mas !'
                    : "$nom -> el " . $fpl->format('d/m/Y') . ' cumple el PLAZO DE PRUEBA.';
            }
        }
        if ($l) $sec[] = ['titulo' => 'CERCA DE CUMPLIR EL PLAZO DE PRUEBA', 'lineas' => $l];

        // ── Falta cargar fecha de ingreso efectivo ──
        $l = [];
        foreach ($activos as $e) {
            $ing = $this->fecha($e->PER_ING);
            if (!$ing) continue;
            if ($hoy->gt($ing->copy()->addDays(180)) && !$this->fecha($e->PER_FEFECTIVO)) {
                $l[] = $this->pad(trim((string) $e->PER_NOM), 30) . ' Fecha de Ingreso: ' . $ing->format('d/m/Y');
            }
        }
        sort($l);
        if ($l) $sec[] = ['titulo' => 'FALTA CARGAR FECHA DE INGRESO EFECTIVO', 'lineas' => $l];

        // ── Cumpleaños ──
        $cumpleEn = function (Carbon $f) use ($activos) {
            $out = [];
            foreach ($activos as $e) {
                $fna = $this->fecha($e->PER_FNA);
                if (!$fna) continue;
                if ($fna->month === $f->month && $fna->day === $f->day) {
                    $out[] = $this->pad(trim((string) $e->PER_NOM), 30) . ' -> (' . ($f->year - $fna->year) . ')';
                }
            }
            return $out;
        };
        if ($hoy->dayOfWeek === Carbon::MONDAY) {
            $sab = $hoy->copy()->subDays(2); $dom = $hoy->copy()->subDays(1);
            $l = array_merge($cumpleEn($sab), $cumpleEn($dom));
            if ($l) $sec[] = ['titulo' => 'CUMPLIERON AÑOS ESTE FIN DE SEMANA PASADO (Sábado ' . $sab->format('d/m/Y') . ' y Domingo ' . $dom->format('d/m/Y') . ')', 'lineas' => $l];
        }
        if ($l = $cumpleEn($hoy)) $sec[] = ['titulo' => 'CUMPLEN AÑOS HOY', 'lineas' => $l];
        if ($l = $cumpleEn($hoy->copy()->addDay())) $sec[] = ['titulo' => 'CUMPLEN AÑOS MAÑANA (' . $hoy->copy()->addDay()->format('d/m/Y') . ')', 'lineas' => $l];
        if ($l = $cumpleEn($hoy->copy()->addDays(2))) $sec[] = ['titulo' => 'CUMPLEN AÑOS PASADO MAÑANA (' . $hoy->copy()->addDays(2)->format('d/m/Y') . ')', 'lineas' => $l];

        // ── Próximas vacaciones (hoy .. hoy+10) ──
        try {
            $vac = DB::table('vacaciones as v')->join('personal as p', 'v.VAC_PER', '=', 'p.PER_COD')
                ->where('p.PER_AOP', 'A')
                ->whereBetween(DB::raw('CAST(v.VAC_FEC AS DATE)'), [$hoy->format('Y-m-d'), $hoy->copy()->addDays(10)->format('Y-m-d')])
                ->whereRaw("CAST(v.VAC_FDE AS DATE) <> '1900-01-01'")
                ->whereRaw("CAST(v.VAC_FHA AS DATE) <> '1900-01-01'")
                ->orderBy('v.VAC_FEC')->orderBy('v.VAC_NOM')
                ->get(['v.VAC_FEC', 'v.VAC_NOM', 'v.VAC_FDE', 'v.VAC_FHA']);
            $l = [];
            foreach ($vac as $v) {
                $l[] = $this->dc($v->VAC_FEC) . ' ' . $this->pad(trim((string) $v->VAC_NOM), 30)
                    . '  -> desde ' . $this->dc($v->VAC_FDE) . ' hasta ' . $this->dc($v->VAC_FHA);
            }
            if ($l) $sec[] = ['titulo' => 'PROXIMAS VACACIONES', 'lineas' => $l];
        } catch (\Throwable $e) { /* sin datos */ }

        // ── Multas pagadas en los últimos 45 días (base de Gestión) ──
        try {
            $f1 = $hoy->copy()->subDays(45)->format('Y-m-d');
            $multas = DB::connection('gestion')->table('VEH_MULT_CUOTAS as c')
                ->join('VEH_MULT as m', 'c.VEM_NRO', '=', 'm.VEM_NRO')
                ->where('c.VEM_PAG', 1)
                ->whereBetween(DB::raw('CAST(c.VEM_FEC AS DATE)'), [$f1, $hoy->format('Y-m-d')])
                ->orderBy('c.VEM_FEC')
                ->get(['m.VEM_NOM as nom', 'c.VEM_DOM as dom', 'c.VEM_FEC as fec', 'c.VEM_CUO as cuo', 'c.VEM_IMP as imp']);
            $l = [];
            foreach ($multas as $mu) {
                $l[] = $this->pad(trim((string) $mu->nom), 30) . ' Vehículo ' . trim((string) $mu->dom)
                    . ' ' . $this->dc($mu->fec) . '  Cuota ' . ((int) $mu->cuo)
                    . '   $' . number_format((float) $mu->imp, 2, ',', '.');
            }
            if ($l) $sec[] = ['titulo' => 'MULTAS PAGADAS EN LOS ULTIMOS 45 DIAS', 'lineas' => $l];
        } catch (\Throwable $e) { /* Gestión no disponible */ }

        // ── Vencimiento de licencias (faltas que vencen hoy) ──
        try {
            $fal = DB::table('reloj_faltas_diarias as f')->join('personal as p', 'f.AFD_PER', '=', 'p.PER_COD')
                ->where('p.PER_AOP', 'A')->whereRaw('CAST(f.AFD_FE2 AS DATE) = ?', [$hoy->format('Y-m-d')])
                ->orderBy('f.AFD_NOM')->get(['f.AFD_NOM', 'f.AFD_LID', 'f.AFD_FE1', 'f.AFD_FE2', 'f.AFD_OBS']);
            $l = [];
            foreach ($fal as $f) {
                $l[] = $this->pad(trim((string) $f->AFD_NOM), 30) . ' ' . trim(mb_substr((string) $f->AFD_LID, 0, 30))
                    . '  -> desde ' . $this->dc($f->AFD_FE1) . ' hasta ' . $this->dc($f->AFD_FE2)
                    . ' ... ' . trim(mb_substr((string) $f->AFD_OBS, 0, 50));
            }
            if ($l) $sec[] = ['titulo' => 'VENCIMIENTO DE LICENCIAS', 'lineas' => $l];
        } catch (\Throwable $e) { /* sin datos */ }

        // ── Exámenes (SOLO Logística, igual que Fox): vencidos último año + próximos ──
        if ($esLogistica) {
            $examSeccion = function (string $titulo, callable $filtro): ?array {
                try {
                    $q = DB::table('examenes as x')->join('personal as p', 'x.EXA_EMP', '=', 'p.PER_COD')
                        ->where('p.PER_AOP', 'A');
                    $filtro($q);
                    $rows = $q->orderBy('x.EXA_TID')->orderBy('x.EXA_VEN')
                        ->get(['x.EXA_VEN as ven', DB::raw('LTRIM(RTRIM(x.EXA_EMD)) as emd'), DB::raw('LTRIM(RTRIM(x.EXA_TID)) as tid')]);
                } catch (\Throwable $e) { return null; }
                $l = []; $ult = null;
                foreach ($rows as $x) {
                    $tid = trim((string) $x->tid);
                    if ($ult !== $tid) { $ult = $tid; $l[] = '   ' . $tid; } // subtítulo por tipo (como Fox)
                    $l[] = '     ' . $this->dc($x->ven) . ' -> ' . $this->pad((string) $x->emd, 30) . ' ' . mb_substr($tid, 0, 50);
                }
                return $l ? ['titulo' => $titulo, 'lineas' => $l] : null;
            };
            // Vencidos en el último año: hoy-365 < EXA_VEN < hoy
            $v = $examSeccion('EXAMENES VENCIDOS EN EL ULTIMO AÑO', function ($q) use ($hoy) {
                $q->whereRaw('CAST(x.EXA_VEN AS DATE) < ?', [$hoy->format('Y-m-d')])
                  ->whereRaw('CAST(x.EXA_VEN AS DATE) > ?', [$hoy->copy()->subDays(365)->format('Y-m-d')]);
            });
            if ($v) $sec[] = $v;
            // Próximos: hoy+15 < EXA_VEN < hoy+30
            $p = $examSeccion('PROXIMOS EXAMENES', function ($q) use ($hoy) {
                $q->whereRaw('CAST(x.EXA_VEN AS DATE) > ?', [$hoy->copy()->addDays(15)->format('Y-m-d')])
                  ->whereRaw('CAST(x.EXA_VEN AS DATE) < ?', [$hoy->copy()->addDays(30)->format('Y-m-d')]);
            });
            if ($p) $sec[] = $p;
        }

        // ── Embargos pendientes (LIQ_ITE cód 612/601 no autorizados en Gestión) ──
        try {
            // Umbral de LIT_NRO como en Fox: Autoelevadores > 47300, Logística > 47000.
            $umbral = $esLogistica ? 47000 : 47300;
            $emb = DB::table('liq_ite')
                ->whereIn('LIT_COD', [612, 601])
                ->where('LIT_NRO', '>', $umbral)
                ->orderBy('LIT_NRO')
                ->get(['LIT_NRO', 'LIT_PER', 'LIT_COD', 'LIT_DES', 'LIT_DED', 'LIT_MES', 'LIT_ANO']);
            if ($emb->isNotEmpty()) {
                // Liquidaciones ya autorizadas (base de Gestión) → no son embargo pendiente.
                $autorizadas = DB::connection('gestion')->table('PAGOS_AUTORIZAR')
                    ->where('OPA_LIQ', '>', 0)->pluck('OPA_LIQ')
                    ->mapWithKeys(fn ($x) => [(int) $x => true]);
                // Fecha de pago por liquidación (LIQUIDAC, base RRHH).
                $pagos = DB::table('liquidac')->pluck('LIQ_PAG', 'LIQ_NRO');
                // Nombres/CBU de los empleados involucrados.
                $pers = DB::table('personal')->whereIn('PER_COD', $emb->pluck('LIT_PER')->map(fn ($x) => (int) $x)->unique()->all())
                    ->get(['PER_COD', 'PER_NOM'])->keyBy('PER_COD');
                // Agrupado por empleado: total embargado + cantidad de embargos pendientes.
                $porEmp = [];
                foreach ($emb as $it) {
                    if ($autorizadas->has((int) $it->LIT_NRO)) continue; // ya autorizado
                    $cod = (int) $it->LIT_PER;
                    $nom = mb_strtoupper(isset($pers[$cod]) ? trim((string) $pers[$cod]->PER_NOM) : ('Empleado ' . $cod));
                    $porEmp[$cod] ??= ['nombre' => $nom, 'total' => 0.0, 'cant' => 0];
                    $porEmp[$cod]['total'] += (float) $it->LIT_DED;
                    $porEmp[$cod]['cant']++;
                }
                uasort($porEmp, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));   // alfabético por nombre
                $l = [];
                $totalGral = 0.0; $cantGral = 0;
                foreach ($porEmp as $e) {
                    $l[] = $e['nombre'] . ' -> $ ' . number_format($e['total'], 2, ',', '.')
                        . ' (' . $e['cant'] . ' embargo' . ($e['cant'] === 1 ? '' : 's') . ')';
                    $totalGral += $e['total']; $cantGral += $e['cant'];
                }
                if ($l) {
                    $l[] = '➤ TOTAL EMBARGADO: $ ' . number_format($totalGral, 2, ',', '.')
                        . '   (' . count($porEmp) . ' empleado' . (count($porEmp) === 1 ? '' : 's')
                        . ' · ' . $cantGral . ' embargo' . ($cantGral === 1 ? '' : 's') . ')';
                    $sec[] = ['titulo' => 'EMBARGOS PENDIENTES', 'lineas' => $l];
                }
            }
        } catch (\Throwable $e) { /* Gestión no disponible o sin datos */ }

        // ── Carga automática de almuerzos del día (lun-sáb, idempotente) — como el alertas.scx ──
        try {
            $creados = (new AlmuerzoController())->autoCargarDia($hoy);
            if ($creados > 0) {
                $sec[] = ['titulo' => 'ALMUERZOS DEL DIA ACTUALIZADOS', 'lineas' => ["Se cargaron automáticamente $creados almuerzos para hoy."]];
            }
        } catch (\Throwable $e) { /* no frenar las alertas si falla */ }

        return response()->json(['secciones' => $sec, 'total' => count($sec)]);
    }

    /** Carbon de una fecha válida (año > 1900); null si vacía/1900. */
    private function fecha($v): ?Carbon
    {
        $s = substr((string) $v, 0, 10);
        if ($s === '' || $s === '1900-01-01') return null;
        try { $c = Carbon::parse($s); return $c->year > 1900 ? $c : null; }
        catch (\Throwable $e) { return null; }
    }

    /** Formatea a dd/mm/yyyy; vacío si nula/1900. */
    private function dc($v): string
    {
        $c = $this->fecha($v);
        return $c ? $c->format('d/m/Y') : '';
    }

    /** Nombre con puntos hasta N caracteres (estilo FoxPro). */
    private function pad(string $s, int $n): string
    {
        $s = strtoupper(trim($s));
        return mb_substr($s . str_repeat('.', $n), 0, $n);
    }
}
