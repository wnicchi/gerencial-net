<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojAjustesController — Reloj / Ajuste Horarios Trabajados (reloj_ajustes_horarios.scx).
 *
 * Permite cargar/editar el ajuste manual de horario de UN empleado en UNA fecha (tabla reloj_ajustes,
 * keyed por AJR_PER + AJR_FEC) y, además, marcar como IGNORAR las marcaciones reales del reloj de ese día.
 *
 * Reglas: la hora de salida no puede ser menor a la de entrada (por turno); no se cargan ajustes a futuro
 * ni con más de 35 días de antigüedad.
 */
class RelojAjustesController extends Controller
{
    /** @route GET /api/reloj/ajustes/dia?cod=&fecha= — ajuste existente + capturas reales del día. */
    public function dia(Request $request): JsonResponse
    {
        $d = $request->validate(['cod' => 'required|integer', 'fecha' => 'required|date']);
        $cod = (int) $d['cod'];
        $fecha = \Carbon\Carbon::parse($d['fecha'])->format('Y-m-d');

        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 404);

        $a = DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereDate('AJR_FEC', $fecha)->first();
        $ajuste = $a ? [
            'hen1' => (int) round((float) $a->AJR_HEN1), 'hsa1' => (int) round((float) $a->AJR_HSA1),
            'hen2' => (int) round((float) $a->AJR_HEN2), 'hsa2' => (int) round((float) $a->AJR_HSA2),
            've1' => (bool) $a->AJR_VE1, 'vs1' => (bool) $a->AJR_VS1, 've2' => (bool) $a->AJR_VE2, 'vs2' => (bool) $a->AJR_VS2,
        ] : null;

        $capturas = DB::table('reloj')->where('rel_cod', $cod)->whereDate('rel_fec', $fecha)
            ->orderBy('rel_fec')->get(['UNICO', 'rel_fec', 'ignorar'])
            ->map(fn ($r) => [
                'unico' => (int) $r->UNICO,
                'fecha' => \Carbon\Carbon::parse($r->rel_fec)->format('Y-m-d H:i:s'),
                'ignorar' => (bool) $r->ignorar,
            ])->values();

        return response()->json([
            'cod' => $cod, 'nombre' => trim((string) $p->PER_NOM), 'fecha' => $fecha,
            'ajuste' => $ajuste, 'capturas' => $capturas,
        ]);
    }

    /** @route POST /api/reloj/ajustes — confirma el ajuste (insert/update) y aplica IGNORAR a las capturas. */
    public function confirmar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'cod'   => 'required|integer',
            'fecha' => 'required|date',
            'hen1'  => 'nullable|integer', 'hsa1' => 'nullable|integer',
            'hen2'  => 'nullable|integer', 'hsa2' => 'nullable|integer',
            've1'   => 'nullable|boolean', 'vs1' => 'nullable|boolean',
            've2'   => 'nullable|boolean', 'vs2' => 'nullable|boolean',
            'capturas'           => 'nullable|array',
            'capturas.*.unico'   => 'required_with:capturas|integer',
            'capturas.*.ignorar' => 'required_with:capturas|boolean',
        ]);
        $cod = (int) $d['cod'];
        $fecha = \Carbon\Carbon::parse($d['fecha'])->startOfDay();
        $hen1 = (int) ($d['hen1'] ?? 0); $hsa1 = (int) ($d['hsa1'] ?? 0);
        $hen2 = (int) ($d['hen2'] ?? 0); $hsa2 = (int) ($d['hsa2'] ?? 0);
        $ve1 = (bool) ($d['ve1'] ?? false); $vs1 = (bool) ($d['vs1'] ?? false);
        $ve2 = (bool) ($d['ve2'] ?? false); $vs2 = (bool) ($d['vs2'] ?? false);

        // Validaciones de negocio (igual que el FoxPro).
        if ($ve1 && $vs1 && $hsa1 < $hen1) return response()->json(['message' => 'La hora de salida no puede ser inferior a la de entrada (Turno 1).'], 422);
        if ($ve2 && $vs2 && $hsa2 < $hen2) return response()->json(['message' => 'La hora de salida no puede ser inferior a la de entrada (Turno 2).'], 422);
        if ($fecha->gt(now()->startOfDay())) return response()->json(['message' => 'No puede cargar ajustes a futuro, verifique la fecha.'], 422);
        if ($fecha->lt(now()->startOfDay()->subDays(35))) return response()->json(['message' => 'No puede cargar ajustes con más de 35 días hacia el pasado.'], 422);

        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM']);
        if (!$p) return response()->json(['message' => 'Empleado inexistente.'], 422);
        if (!\App\Support\Empleado::activo($cod)) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar ajustes.'], 422);
        }

        DB::transaction(function () use ($cod, $fecha, $hen1, $hsa1, $hen2, $hsa2, $ve1, $vs1, $ve2, $vs2, $p, $d) {
            $existe = DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereDate('AJR_FEC', $fecha->format('Y-m-d'))->exists();
            $valores = [
                'AJR_HEN1' => $hen1, 'AJR_HSA1' => $hsa1, 'AJR_HEN2' => $hen2, 'AJR_HSA2' => $hsa2,
                'AJR_VE1' => $ve1 ? 1 : 0, 'AJR_VS1' => $vs1 ? 1 : 0, 'AJR_VE2' => $ve2 ? 1 : 0, 'AJR_VS2' => $vs2 ? 1 : 0,
            ];
            if ($existe) {
                DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereDate('AJR_FEC', $fecha->format('Y-m-d'))->update($valores);
            } else {
                DB::table('reloj_ajustes')->insert(Registro::completar('reloj_ajustes', $valores + [
                    'AJR_PER' => $cod, 'AJR_NOM' => trim((string) $p->PER_NOM), 'AJR_FEC' => $fecha->format('Y-m-d'),
                ]));
            }

            foreach (($d['capturas'] ?? []) as $c) {
                DB::table('reloj')->where('UNICO', (int) $c['unico'])->update(['ignorar' => ($c['ignorar'] ?? false) ? 1 : 0]);
            }
        });

        return response()->json(['message' => 'Ajuste grabado correctamente.']);
    }

    /** @route POST /api/reloj/ajustes/eliminar — borra todos los ajustes del empleado en la fecha. */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['cod' => 'required|integer', 'fecha' => 'required|date']);
        $cod = (int) $d['cod'];
        $fecha = \Carbon\Carbon::parse($d['fecha'])->format('Y-m-d');
        $n = DB::table('reloj_ajustes')->where('AJR_PER', $cod)->whereDate('AJR_FEC', $fecha)->delete();
        return response()->json(['message' => "Ajustes borrados ($n).", 'eliminados' => $n]);
    }

    // ── Ajustes Horarios - Borrar (reloj_ajustes_horarios_borrar.scx) ─

    /** @route GET /api/reloj/ajustes/lista?desde=&hasta=&nombre= — ajustes manuales en un rango. */
    public function lista(Request $request): JsonResponse
    {
        $d = $request->validate(['desde' => 'required|date', 'hasta' => 'required|date', 'nombre' => 'nullable|string']);
        $desde = \Carbon\Carbon::parse($d['desde'])->format('Y-m-d');
        $hasta = \Carbon\Carbon::parse($d['hasta'])->format('Y-m-d');
        $nombre = mb_strtoupper(trim((string) ($d['nombre'] ?? '')));

        $q = DB::table('reloj_ajustes as a')->join('personal as p', 'a.AJR_PER', '=', 'p.PER_COD')
            ->whereDate('a.AJR_FEC', '>=', $desde)->whereDate('a.AJR_FEC', '<=', $hasta);
        if ($nombre !== '') $q->whereRaw('UPPER(p.PER_NOM) LIKE ?', ['%' . $nombre . '%']);

        $filas = $q->orderBy('a.AJR_FEC', 'desc')->orderBy('p.PER_NOM')
            ->get(['a.AJR_PER', 'a.AJR_FEC', 'p.PER_NOM'])
            ->map(fn ($r) => [
                'cod' => (int) $r->AJR_PER, 'fecha' => \Carbon\Carbon::parse($r->AJR_FEC)->format('Y-m-d'),
                'nombre' => trim((string) $r->PER_NOM), 'sel' => false,
            ])->values();

        return response()->json(['total' => $filas->count(), 'ajustes' => $filas]);
    }

    /** @route POST /api/reloj/ajustes/borrar-lote — borra los ajustes seleccionados (por empleado+fecha). */
    public function borrarLote(Request $request): JsonResponse
    {
        $d = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.cod' => 'required|integer', 'items.*.fecha' => 'required|date',
        ]);
        $n = 0;
        foreach ($d['items'] as $it) {
            $n += DB::table('reloj_ajustes')->where('AJR_PER', (int) $it['cod'])
                ->whereDate('AJR_FEC', \Carbon\Carbon::parse($it['fecha'])->format('Y-m-d'))->delete();
        }
        return response()->json(['message' => "Proceso concluido. Ajustes eliminados ($n).", 'eliminados' => $n]);
    }
}
