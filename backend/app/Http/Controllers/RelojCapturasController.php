<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojCapturasController — Reloj / Registro de Personal (reloj_capturas.scx).
 *
 * Lista las marcaciones del reloj (tabla reloj) cruzadas con el legajo del empleado, filtradas por un
 * rango de fechas y, opcionalmente, por nombre. Permite intercalar los AJUSTES manuales de horario
 * (reloj_ajustes), que se muestran con REL = 0 y resaltados en amarillo, o ver SOLO los ajustes.
 *
 * La tabla reloj es muy grande (cientos de miles de registros): SIEMPRE se filtra por rango de fechas.
 */
class RelojCapturasController extends Controller
{
    /** @route GET /api/reloj/capturas?desde=&hasta=&nombre=&soloAjustes=&conAjustes= */
    public function capturas(Request $request): JsonResponse
    {
        $d = $request->validate([
            'desde'       => 'required|date',
            'hasta'       => 'required|date',
            'nombre'      => 'nullable|string',
            'soloAjustes' => 'nullable|boolean',
            'conAjustes'  => 'nullable|boolean',
        ]);
        $desde = \Carbon\Carbon::parse($d['desde'])->startOfDay();
        $hasta = \Carbon\Carbon::parse($d['hasta'])->endOfDay();
        $nombre = mb_strtoupper(trim((string) ($d['nombre'] ?? '')));
        $soloAjustes = (bool) ($d['soloAjustes'] ?? false);
        $conAjustes = (bool) ($d['conAjustes'] ?? false);

        $filas = [];

        // Marcaciones reales del reloj (salvo que se pida sólo ajustes).
        if (!$soloAjustes) {
            $q = DB::table('reloj')
                ->join('personal', 'reloj.rel_cod', '=', 'personal.PER_COD')
                ->whereBetween('reloj.rel_fec', [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')]);
            if ($nombre !== '') $q->whereRaw('UPPER(personal.PER_NOM) LIKE ?', ['%' . $nombre . '%']);
            foreach ($q->orderByDesc('reloj.rel_fec')
                ->get(['reloj.rel_cod', 'reloj.rel_fec', 'reloj.rel_id', 'personal.PER_NOM', 'personal.PER_LEG', 'personal.PER_TDO', 'personal.PER_NDO', 'personal.PER_CUI', 'personal.PER_CEL']) as $r) {
                $filas[] = $this->fila((int) $r->rel_cod, $r->rel_fec, (int) round((float) $r->rel_id), $r);
            }
        }

        // Ajustes manuales de horario (si se piden, o si se ven sólo ajustes).
        if ($conAjustes || $soloAjustes) {
            $qa = DB::table('reloj_ajustes')
                ->join('personal', 'reloj_ajustes.AJR_PER', '=', 'personal.PER_COD')
                ->whereBetween('reloj_ajustes.AJR_FEC', [$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')]);
            if ($nombre !== '') $qa->whereRaw('UPPER(personal.PER_NOM) LIKE ?', ['%' . $nombre . '%']);
            $marcas = [['AJR_VE1', 'AJR_HEN1'], ['AJR_VS1', 'AJR_HSA1'], ['AJR_VE2', 'AJR_HEN2'], ['AJR_VS2', 'AJR_HSA2']];
            foreach ($qa->get(['reloj_ajustes.*', 'personal.PER_NOM', 'personal.PER_LEG', 'personal.PER_TDO', 'personal.PER_NDO', 'personal.PER_CUI', 'personal.PER_CEL']) as $a) {
                $fec = \Carbon\Carbon::parse($a->AJR_FEC);
                foreach ($marcas as [$flag, $hora]) {
                    if (!(int) $a->$flag) continue;
                    $hhmm = (int) round((float) $a->$hora);
                    $dt = $fec->copy()->setTime(intdiv($hhmm, 100), $hhmm % 100, 0);
                    $filas[] = $this->fila((int) $a->AJR_PER, $dt->format('Y-m-d H:i:s'), 0, $a);
                }
            }
        }

        // Orden por fecha/hora descendente (como el FoxPro).
        usort($filas, fn ($x, $y) => strcmp($y['registro'], $x['registro']));

        return response()->json(['total' => count($filas), 'capturas' => $filas]);
    }

    private function fila(int $cod, $fec, int $relId, $p): array
    {
        return [
            'codigo' => $cod,
            'registro' => \Carbon\Carbon::parse($fec)->format('Y-m-d H:i:s'),
            'empleado' => trim((string) ($p->PER_NOM ?? '')),
            'legajo' => (int) ($p->PER_LEG ?? 0),
            'tdoc' => trim((string) ($p->PER_TDO ?? '')),
            'ndoc' => trim((string) ($p->PER_NDO ?? '')),
            'cuil' => trim((string) ($p->PER_CUI ?? '')),
            'celular' => trim((string) ($p->PER_CEL ?? '')),
            'rel' => $relId,
            'ajuste' => $relId === 0,
        ];
    }
}
