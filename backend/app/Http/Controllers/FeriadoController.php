<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FeriadoController — Liquidaciones / Definición de Feriados (definicion_de_feriados.scx).
 *
 * ABM de los feriados (tabla feriados: FER_MES, FER_ANO, FER_FEC fecha, FER_DIA nombre del día, FER_OBS
 * descripción). Se trabaja por mes/año; cada feriado se identifica por su fecha (FER_FEC). El nombre del
 * día se calcula a partir de la fecha. Los feriados los usa el cálculo de horas trabajadas (horas al 100%).
 */
class FeriadoController extends Controller
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sábado'];

    /** @route GET /api/feriados?mes=&anio= — feriados de un mes/año. */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate(['mes' => 'required|integer|between:1,12', 'anio' => 'required|integer']);
        $filas = DB::table('feriados')->where('FER_MES', (int) $d['mes'])->where('FER_ANO', (int) $d['anio'])
            ->orderBy('FER_FEC')->get(['FER_FEC', 'FER_MES', 'FER_ANO', 'FER_DIA', 'FER_OBS'])
            ->map(fn ($f) => $this->fila($f))->values();
        return response()->json(['feriados' => $filas]);
    }

    /** @route POST /api/feriados — agrega o modifica un feriado (por fecha). */
    public function guardar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mes' => 'required|integer|between:1,12', 'anio' => 'required|integer',
            'dia' => 'required|integer|between:1,31', 'obs' => 'required|string|max:100',
        ]);
        try {
            $fecha = \Carbon\Carbon::create((int) $d['anio'], (int) $d['mes'], (int) $d['dia']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Fecha no válida.'], 422);
        }
        if (!$fecha || $fecha->month !== (int) $d['mes']) return response()->json(['message' => 'Fecha no válida.'], 422);

        $obs = mb_strtoupper(trim($d['obs']));
        $diaNombre = self::DIAS[(int) $fecha->dayOfWeek];

        $existe = DB::table('feriados')->whereDate('FER_FEC', $fecha->format('Y-m-d'))->exists();
        if ($existe) {
            DB::table('feriados')->whereDate('FER_FEC', $fecha->format('Y-m-d'))->update(['FER_OBS' => $obs, 'FER_DIA' => $diaNombre]);
        } else {
            DB::table('feriados')->insert(Registro::completar('feriados', [
                'FER_MES' => (int) $d['mes'], 'FER_ANO' => (int) $d['anio'], 'FER_FEC' => $fecha->format('Y-m-d'),
                'FER_DIA' => $diaNombre, 'FER_OBS' => $obs,
            ]));
        }
        return response()->json(['message' => $existe ? 'Feriado modificado.' : 'Feriado agregado.']);
    }

    /** @route POST /api/feriados/eliminar — borra un feriado por fecha. */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['fecha' => 'required|date']);
        $fecha = \Carbon\Carbon::parse($d['fecha'])->format('Y-m-d');
        $n = DB::table('feriados')->whereDate('FER_FEC', $fecha)->delete();
        return response()->json(['message' => "Feriado eliminado ($n).", 'eliminados' => $n]);
    }

    /** @route GET /api/feriados/rango?desde=&hasta= — feriados de un rango (para imprimir). */
    public function rango(Request $request): JsonResponse
    {
        $d = $request->validate(['desde' => 'required|date', 'hasta' => 'required|date']);
        $filas = DB::table('feriados')
            ->whereDate('FER_FEC', '>=', \Carbon\Carbon::parse($d['desde'])->format('Y-m-d'))
            ->whereDate('FER_FEC', '<=', \Carbon\Carbon::parse($d['hasta'])->format('Y-m-d'))
            ->orderBy('FER_FEC')->get(['FER_FEC', 'FER_MES', 'FER_ANO', 'FER_DIA', 'FER_OBS'])
            ->map(fn ($f) => $this->fila($f))->values();
        return response()->json(['feriados' => $filas]);
    }

    private function fila($f): array
    {
        $fec = \Carbon\Carbon::parse($f->FER_FEC);
        return [
            'dia' => (int) $fec->day, 'mes' => (int) $f->FER_MES, 'anio' => (int) $f->FER_ANO,
            'fecha' => $fec->format('Y-m-d'), 'diaSemana' => trim((string) $f->FER_DIA), 'obs' => trim((string) $f->FER_OBS),
        ];
    }
}
