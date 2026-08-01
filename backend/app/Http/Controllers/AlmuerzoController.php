<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AlmuerzoController — Editar Almuerzos del Personal.
 *
 * Arma la lista de comensales de un comedor para una fecha: empleados que
 * almuerzan (PER_ALM != 'N') del comedor elegido + invitados. Carga lo ya
 * editado para ese día si existe; si no, propone valores por defecto. Excluye
 * a quienes están de vacaciones o de licencia ese día. Cada fila tiene
 * Almuerza/Descuenta (sí/no), Cantidad y Observaciones. Al confirmar, hace
 * upsert en la tabla `almuerzo` (tipo "E" empleado / "I" invitado).
 */
class AlmuerzoController extends Controller
{
    /**
     * Carga automática de los almuerzos del día (réplica del proceso de alertas.scx).
     * Se ejecuta de lunes a sábado. Si el día YA tiene almuerzos cargados no hace nada
     * (idempotente). Si no, crea una fila por empleado activo que almuerza (PER_ALM != 'N'),
     * excluyendo a los que están de vacaciones o de licencia ese día. Valores por defecto
     * según PER_CAR ("come a cargo"): Almuerza/Descuenta = S y Cantidad = 1 si PER_CAR='S'.
     *
     * @return int cantidad de filas creadas (0 si no correspondía o ya estaban).
     */
    public function autoCargarDia(?Carbon $fecha = null): int
    {
        $fecha = ($fecha ?? Carbon::today())->startOfDay();
        // Lunes a sábado (en Fox: Dow(Date())>1, donde 1=Domingo).
        if ($fecha->dayOfWeek === Carbon::SUNDAY) {
            return 0;
        }
        $fd = $fecha->toDateString();
        // Si el día ya tiene almuerzos cargados, no se toca (idempotente).
        if (DB::table('almuerzo')->whereDate('ALM_FEC', $fd)->exists()) {
            return 0;
        }

        $personal = DB::table('personal')->where('PER_AOP', 'A')->where('PER_ALM', '!=', 'N')
            ->get(['PER_COD', 'PER_NOM', 'PER_CAR']);
        if ($personal->isEmpty()) {
            return 0;
        }
        $codes = $personal->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();

        // Vacaciones / licencias vigentes ese día (para excluir).
        $enVacaciones = []; $enLicencia = [];
        foreach (DB::table('vacaciones')->whereIn('VAC_PER', $codes)
            ->whereRaw('CAST(VAC_FDE AS DATE) <= ?', [$fd])->whereRaw('CAST(VAC_FHA AS DATE) >= ?', [$fd])
            ->get(['VAC_PER']) as $v) { $enVacaciones[(int) $v->VAC_PER] = true; }
        foreach (DB::table('reloj_faltas_diarias')->whereIn('AFD_PER', $codes)
            ->whereRaw('CAST(AFD_FE1 AS DATE) <= ?', [$fd])->whereRaw('CAST(AFD_FE2 AS DATE) >= ?', [$fd])
            ->get(['AFD_PER']) as $l) { $enLicencia[(int) $l->AFD_PER] = true; }

        $nombreDia = mb_strtoupper($fecha->locale('es')->isoFormat('dddd'));
        $creados = 0;

        DB::transaction(function () use ($personal, $enVacaciones, $enLicencia, $fecha, $nombreDia, &$creados) {
            // Re-chequeo dentro de la transacción por si otro proceso lo creó en paralelo.
            if (DB::table('almuerzo')->whereDate('ALM_FEC', $fecha->toDateString())->exists()) {
                return;
            }
            $maxOrd = (int) DB::table('almuerzo')->max('ALM_ORD');
            foreach ($personal as $p) {
                $cod = (int) $p->PER_COD;
                if (!empty($enVacaciones[$cod]) || !empty($enLicencia[$cod])) {
                    continue;
                }
                $car = trim((string) $p->PER_CAR) === 'S';
                DB::table('almuerzo')->insert(Registro::completar('almuerzo', [
                    'ALM_ORD' => ++$maxOrd,
                    'ALM_PER' => $cod,
                    'ALM_NOM' => mb_substr(mb_strtoupper(trim((string) $p->PER_NOM)), 0, 50),
                    'ALM_FEC' => $fecha,
                    'ALM_DIA' => $nombreDia,
                    'ALM_ALM' => $car ? 'S' : 'N',
                    'ALM_DES' => $car ? 'S' : 'N',
                    'ALM_CAN' => $car ? 1 : 0,
                    'ALM_OBS' => '',
                    'ALM_TIP' => 'E',
                ]));
                $creados++;
            }
        });

        return $creados;
    }

    /** @route GET /api/almuerzos?empresa=&comedor=&fecha= */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'empresa' => 'nullable|integer',
            'comedor' => 'required|integer',
            'fecha'   => 'required|date',
        ]);
        $empresa = (int) ($datos['empresa'] ?? 1);
        $comedor = (int) $datos['comedor'];
        $fecha = Carbon::parse($datos['fecha'])->startOfDay();
        $fd = $fecha->toDateString();

        // Almuerzos ya cargados ese día
        $almDia = DB::table('almuerzo')->whereDate('ALM_FEC', $fd)->get();
        $almE = $almDia->where('ALM_TIP', 'E')->keyBy('ALM_PER');
        $almI = $almDia->where('ALM_TIP', 'I')->keyBy('ALM_PER');

        // Personal del comedor que almuerza
        $personal = DB::table('personal')
            ->where('PER_EMP', $empresa)->where('PER_AOP', 'A')
            ->where('PER_ALM', '!=', 'N')->where('PER_COMN', $comedor)
            ->get(['PER_COD', 'PER_NOM', 'PER_CAR']);
        $codes = $personal->pluck('PER_COD')->map(fn ($v) => (int) $v)->all();

        // Vacaciones / licencias vigentes ese día (para excluir)
        $enVacaciones = []; $enLicencia = [];
        if (!empty($codes)) {
            foreach (DB::table('vacaciones')->whereIn('VAC_PER', $codes)
                ->whereRaw('CAST(VAC_FDE AS DATE) <= ?', [$fd])->whereRaw('CAST(VAC_FHA AS DATE) >= ?', [$fd])
                ->get(['VAC_PER']) as $v) { $enVacaciones[(int) $v->VAC_PER] = true; }
            foreach (DB::table('reloj_faltas_diarias')->whereIn('AFD_PER', $codes)
                ->whereRaw('CAST(AFD_FE1 AS DATE) <= ?', [$fd])->whereRaw('CAST(AFD_FE2 AS DATE) >= ?', [$fd])
                ->get(['AFD_PER']) as $l) { $enLicencia[(int) $l->AFD_PER] = true; }
        }

        $rows = [];
        foreach ($personal as $p) {
            $cod = (int) $p->PER_COD;
            $a = $almE[$cod] ?? null;
            if ($a) {
                $rows[] = $this->fila($cod, mb_strtoupper(trim((string) $a->ALM_NOM)),
                    trim((string) $a->ALM_ALM) === 'S', trim((string) $a->ALM_DES) === 'S',
                    (int) $a->ALM_CAN, trim((string) $a->ALM_OBS), 'E');
            } elseif (empty($enVacaciones[$cod]) && empty($enLicencia[$cod])) {
                $car = trim((string) $p->PER_CAR) === 'S';
                $rows[] = $this->fila($cod, mb_strtoupper(trim((string) $p->PER_NOM)), $car, $car, $car ? 1 : 0, '', 'E');
            }
        }
        usort($rows, fn ($x, $y) => strcmp($x['empleado'], $y['empleado']));

        // Invitados
        $invitados = [];
        foreach (DB::table('invitados')->orderBy('INV_NOM')->get(['INV_COD', 'INV_NOM']) as $inv) {
            $cod = (int) $inv->INV_COD;
            $a = $almI[$cod] ?? null;
            $invitados[] = $a
                ? $this->fila($cod, mb_strtoupper(trim((string) $a->ALM_NOM)), trim((string) $a->ALM_ALM) === 'S',
                    trim((string) $a->ALM_DES) === 'S', (int) $a->ALM_CAN, trim((string) $a->ALM_OBS), 'I')
                : $this->fila($cod, mb_strtoupper(trim((string) $inv->INV_NOM)), false, false, 0, '', 'I');
        }

        return response()->json(['rows' => array_merge($rows, $invitados)]);
    }

    /** @route POST /api/almuerzos/confirmar  body: { fecha, rows:[...] } */
    public function confirmar(Request $request): JsonResponse
    {
        $request->validate([
            'fecha'            => 'required|date',
            'rows'             => 'required|array|min:1',
            'rows.*.cod_emp'   => 'required|integer',
            'rows.*.tipo'      => 'required|in:E,I',
        ]);
        $rows = $request->input('rows');
        $fecha = Carbon::parse($request->input('fecha'))->startOfDay();
        $nombreDia = mb_strtoupper($fecha->locale('es')->isoFormat('dddd'));

        $maxOrd = (int) DB::table('almuerzo')->max('ALM_ORD');

        DB::transaction(function () use ($rows, $fecha, $nombreDia, &$maxOrd) {
            foreach ($rows as $r) {
                $vals = [
                    'ALM_ALM' => !empty($r['almuerza']) ? 'S' : 'N',
                    'ALM_DES' => !empty($r['descuenta']) ? 'S' : 'N',
                    'ALM_CAN' => (int) ($r['cantidad'] ?? 0),
                    'ALM_OBS' => trim((string) ($r['observacion'] ?? '')),
                    'ALM_TIP' => $r['tipo'],
                ];
                $q = DB::table('almuerzo')->whereDate('ALM_FEC', $fecha->toDateString())
                    ->where('ALM_PER', (int) $r['cod_emp'])->where('ALM_TIP', $r['tipo']);
                if ($q->exists()) {
                    $q->update($vals);
                } else {
                    DB::table('almuerzo')->insert(Registro::completar('almuerzo', array_merge($vals, [
                        'ALM_ORD' => ++$maxOrd,
                        'ALM_PER' => (int) $r['cod_emp'],
                        'ALM_NOM' => mb_substr((string) ($r['empleado'] ?? ''), 0, 50),
                        'ALM_FEC' => $fecha,
                        'ALM_DIA' => $nombreDia,
                    ])));
                }
            }
        });

        return response()->json(['ok' => true, 'grabados' => count($rows)]);
    }

    /**
     * Listado de almuerzos (detallado o resumido) agrupado por empresa.
     * @route GET /api/almuerzos/listado
     *   rango: 1=período, 2=jornada · empresa_modo/empresa_id · comedor_modo/comedor_id
     *   detallado: 1=detallado, 2=resumido · orden: 1=nombre, 2=legajo, 3=código
     */
    public function listado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rango'        => 'required|integer|in:1,2',
            'empresa_modo' => 'required|integer|in:1,2',
            'empresa_id'   => 'nullable|integer',
            'comedor_modo' => 'required|integer|in:1,2',
            'comedor_id'   => 'nullable|integer',
            'detallado'    => 'required|integer|in:1,2',
            'orden'        => 'required|integer|in:1,2,3',
            'fecha1'       => 'required|date',
            'fecha2'       => 'nullable|date',
        ]);

        $f1 = Carbon::parse($datos['fecha1'])->startOfDay();
        $f2 = $datos['rango'] == 2 ? $f1->copy() : Carbon::parse($datos['fecha2'] ?? $datos['fecha1'])->startOfDay();

        $titulo = 'ALMUERZOS ' . ($datos['rango'] == 2
            ? ' de la Jornada del ' . $f1->format('d/m/Y')
            : ' desde el ' . $f1->format('d/m/Y') . ' al ' . $f2->format('d/m/Y'));

        $q = DB::table('almuerzo as a')
            ->join('personal as p', 'a.ALM_PER', '=', 'p.PER_COD')
            ->join('empresas as e', 'p.PER_EMP', '=', 'e.EMP_COD')
            ->whereRaw('CAST(a.ALM_FEC AS DATE) BETWEEN ? AND ?', [$f1->toDateString(), $f2->toDateString()])
            ->where('a.ALM_CAN', '>', 0);
        if ($datos['empresa_modo'] == 2) $q->where('p.PER_EMP', (int) ($datos['empresa_id'] ?? 0));
        if ($datos['comedor_modo'] == 2) $q->where('p.PER_COMN', (int) ($datos['comedor_id'] ?? 0));

        $filas = $q->get(['a.ALM_ORD', 'a.ALM_CAN', 'a.ALM_FEC', 'p.PER_COD', 'p.PER_NOM', 'p.PER_LEG',
            'e.EMP_COD', DB::raw('LTRIM(RTRIM(e.EMP_NOM)) as EMP_NOM')])
            ->unique('ALM_ORD');   // dedupe por ALM_ORD (como el FoxPro)

        if ($datos['empresa_modo'] == 2 && $filas->isNotEmpty()) {
            $titulo .= ' de la Empresa ' . trim((string) $filas->first()->EMP_NOM);
        }

        // Agrupar por empresa
        $grupos = [];
        foreach ($filas as $r) {
            $emp = (int) $r->EMP_COD;
            $grupos[$emp]['emp_nom'] = trim((string) $r->EMP_NOM);
            $grupos[$emp]['items'][] = $r;
        }

        $empresas = [];
        foreach ($grupos as $g) {
            $items = collect($g['items']);
            if ($datos['detallado'] == 2) {
                // Resumido: total por empleado
                $rows = $items->groupBy('PER_COD')->map(function ($g2) {
                    $p = $g2->first();
                    return [
                        'cod'      => (int) $p->PER_COD,
                        'empleado' => mb_strtoupper(trim((string) $p->PER_NOM)),
                        'legajo'   => trim((string) $p->PER_LEG),
                        'cantidad' => (int) $g2->sum('ALM_CAN'),
                    ];
                })->values();
            } else {
                // Detallado: cada almuerzo
                $rows = $items->map(fn ($p) => [
                    'cod'      => (int) $p->PER_COD,
                    'empleado' => mb_strtoupper(trim((string) $p->PER_NOM)),
                    'legajo'   => trim((string) $p->PER_LEG),
                    'cantidad' => (int) $p->ALM_CAN,
                    'fecha'    => Carbon::parse($p->ALM_FEC)->format('d/m/Y'),
                ])->values();
            }
            // Orden interno
            $rows = $rows->sortBy(function ($x) use ($datos) {
                return match ((int) $datos['orden']) {
                    2 => str_pad((string) $x['legajo'], 12, '0', STR_PAD_LEFT),
                    3 => str_pad((string) $x['cod'], 12, '0', STR_PAD_LEFT),
                    default => $x['empleado'],
                };
            })->values();

            $empresas[] = [
                'emp_nom' => $g['emp_nom'],
                'total'   => (int) $items->sum('ALM_CAN'),
                'rows'    => $rows->all(),
            ];
        }
        usort($empresas, fn ($a, $b) => strcmp($a['emp_nom'], $b['emp_nom']));

        return response()->json([
            'titulo'    => $titulo,
            'tipo'      => $datos['detallado'] == 2 ? 'resumido' : 'detallado',
            'empresas'  => $empresas,
            'total'     => (int) $filas->sum('ALM_CAN'),
        ]);
    }

    private function fila(int $cod, string $nombre, bool $alm, bool $des, int $can, string $obs, string $tipo): array
    {
        return [
            'cod_emp'     => $cod,
            'empleado'    => $nombre,
            'almuerza'    => $alm,
            'descuenta'   => $des,
            'cantidad'    => $can,
            'observacion' => $obs,
            'tipo'        => $tipo,
        ];
    }
}
