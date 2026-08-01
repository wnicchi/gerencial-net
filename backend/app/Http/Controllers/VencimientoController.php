<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VencimientoController — Vencimientos y recordatorios centralizados.
 *
 * Junta en una sola grilla todos los vencimientos con fecha real que hoy están
 * repartidos por distintos módulos:
 *   - Exámenes médicos (examenes.EXA_VEN)
 *   - Licencias de conducir (personal.PER_LF1..3)
 *   - Plazos de prueba (personal.PER_ING + 90/180 días)
 *
 * Los Siniestros ART (abiertos, sin fecha de vencimiento) se ven en su módulo
 * y en el panel de inicio, no en esta grilla por fecha.
 */
class VencimientoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hoy   = Carbon::today();
        $desde = $this->fecha($request->query('desde')) ?? $hoy->copy()->subDays(30);
        $hasta = $this->fecha($request->query('hasta')) ?? $hoy->copy()->addDays(30);
        $q     = strtoupper(trim((string) $request->query('q', '')));
        $estado = (string) $request->query('estado', 'todos');           // vencidos | porvencer | todos
        $tipos = array_filter(array_map('trim', explode(',', (string) $request->query('tipos', ''))));
        $quiere = fn (string $t) => empty($tipos) || in_array($t, $tipos, true);

        $rows = [];
        $add = function (string $tipo, $cod, $emp, $detalle, ?Carbon $venc) use (&$rows, $hoy, $desde, $hasta) {
            if (!$venc) return;
            if ($venc->lt($desde) || $venc->gt($hasta)) return;
            $rows[] = [
                'tipo'     => $tipo,
                'cod'      => (int) $cod,
                'empleado' => strtoupper(trim((string) $emp)),
                'detalle'  => trim((string) $detalle),
                'vence'    => $venc->format('Y-m-d'),
                'dias'     => $hoy->diffInDays($venc, false),   // negativo = vencido
            ];
        };

        // ── Exámenes médicos (próximo control) — uno por empleado (el más reciente) ──
        if ($quiere('examen')) {
            try {
                $ex = DB::table('examenes as x')->join('personal as p', 'x.EXA_EMP', '=', 'p.PER_COD')
                    ->where('p.PER_AOP', 'A')
                    ->get(['p.PER_NOM as nom', 'x.EXA_EMP as cod', 'x.EXA_VEN as ven', DB::raw('LTRIM(RTRIM(x.EXA_TID)) as tipo')]);
                $mejor = [];
                foreach ($ex as $e) {
                    $fv = $this->fecha($e->ven);
                    if (!$fv) continue;
                    $c = (int) $e->cod;
                    if (!isset($mejor[$c]) || $fv->gt($mejor[$c]['fv'])) $mejor[$c] = ['fv' => $fv, 'r' => $e];
                }
                foreach ($mejor as $m) {
                    $add('examen', $m['r']->cod, $m['r']->nom, $m['r']->tipo ?: 'Control', $m['fv']);
                }
            } catch (\Throwable $e) { /* sin datos */ }
        }

        // ── Licencias de conducir (3 carnets por empleado) ──
        if ($quiere('licencia_conducir')) {
            try {
                $emp = DB::table('personal')->where('PER_AOP', 'A')
                    ->get(['PER_COD', 'PER_NOM', 'PER_LF1', 'PER_LF2', 'PER_LF3', 'PER_LN1', 'PER_LN2', 'PER_LN3']);
                foreach ($emp as $e) {
                    for ($n = 1; $n <= 3; $n++) {
                        $fv = $this->fecha($e->{'PER_LF' . $n});
                        if (!$fv) continue;
                        $nro = trim((string) $e->{'PER_LN' . $n});
                        $det = 'Carnet ' . $n . ($nro !== '' ? ' Nº ' . $nro : '');
                        $add('licencia_conducir', $e->PER_COD, $e->PER_NOM, $det, $fv);
                    }
                }
            } catch (\Throwable $e) { /* sin datos */ }
        }

        // ── Plazo de prueba (ingreso + 90/180 días) ──
        if ($quiere('plazo_prueba')) {
            try {
                $corte = Carbon::create(2024, 7, 9);
                $emp = DB::table('personal')->where('PER_AOP', 'A')->get(['PER_COD', 'PER_NOM', 'PER_ING']);
                foreach ($emp as $e) {
                    $ing = $this->fecha($e->PER_ING);
                    if (!$ing) continue;
                    $plazo = $ing->lte($corte) ? 90 : 180;
                    $fpl = $ing->copy()->addDays($plazo);
                    $add('plazo_prueba', $e->PER_COD, $e->PER_NOM, 'Ingreso ' . $ing->format('d/m/Y'), $fpl);
                }
            } catch (\Throwable $e) { /* sin datos */ }
        }

        // ── Filtros de texto y estado ──
        if ($q !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains($r['empleado'], $q)));
        }
        if ($estado === 'vencidos') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['dias'] < 0));
        } elseif ($estado === 'porvencer') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['dias'] >= 0));
        }

        // Orden por urgencia: primero lo más vencido / más próximo (menor "días").
        usort($rows, fn ($a, $b) => $a['dias'] <=> $b['dias']);

        return response()->json(['rows' => $rows, 'total' => count($rows)]);
    }

    /** Fecha válida o null (1900 = vacío, estilo FoxPro). */
    private function fecha($v): ?Carbon
    {
        $s = substr((string) $v, 0, 10);
        if ($s === '' || $s === '1900-01-01') return null;
        try { $c = Carbon::parse($s); return $c->year > 1900 ? $c : null; }
        catch (\Throwable $e) { return null; }
    }
}
