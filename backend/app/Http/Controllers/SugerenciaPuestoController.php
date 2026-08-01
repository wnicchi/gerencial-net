<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SugerenciaPuestoController — Valores por defecto inteligentes a partir del puesto.
 *
 * Dado un puesto, calcula el valor más frecuente de ciertos campos entre los
 * empleados ACTIVOS que ya tienen ese puesto, y sugiere solo los que superan un
 * umbral de confianza (para no molestar con sugerencias flojas). En los datos
 * actuales el convenio queda fuertemente determinado por el puesto (>90%),
 * mientras que sector/categoría son ambiguos y no se sugieren.
 *
 * Devuelve códigos + confianza; el frontend mapea el código a su descripción
 * usando las mismas opciones (convenios, categorías, sectores, subsectores).
 */
class SugerenciaPuestoController extends Controller
{
    /** Campos candidatos: clave lógica => columna en personal. */
    private const CAMPOS = [
        'convenio'   => 'PER_CON',
        'categoria'  => 'PER_CAT',
        'sector'     => 'PER_SEC',
        'subsector'  => 'PER_SUC',
    ];

    private const UMBRAL     = 70;   // % mínimo de confianza para sugerir
    private const MIN_MUESTRA = 4;   // ocupantes mínimos para que la estadística valga

    public function index(Request $request): JsonResponse
    {
        $pue = trim((string) $request->query('puesto', ''));
        if ($pue === '') {
            return response()->json(['ocupantes' => 0, 'sugerencias' => (object) []]);
        }

        $base = fn () => DB::table('puestoempleado as pe')
            ->join('personal as p', DB::raw('LTRIM(RTRIM(p.PER_CUI))'), '=', DB::raw('LTRIM(RTRIM(pe.PEM_CUIL))'))
            ->where('p.PER_AOP', 'A')
            ->whereRaw('LTRIM(RTRIM(pe.PEM_PUE)) = ?', [$pue]);

        $ocupantes = (clone $base())->distinct()->count('p.PER_COD');

        $sug = [];
        foreach (self::CAMPOS as $clave => $col) {
            $dist = $base()
                ->whereNotNull("p.$col")->where("p.$col", '<>', 0)
                ->groupBy("p.$col")
                ->select("p.$col as v", DB::raw('COUNT(*) as n'))
                ->orderByDesc('n')
                ->get();

            $total = (int) $dist->sum('n');
            if ($total < self::MIN_MUESTRA || $dist->isEmpty()) continue;

            $top = $dist->first();
            $conf = (int) round(((int) $top->n) * 100 / $total);
            if ($conf < self::UMBRAL) continue;

            $sug[$clave] = [
                'valor'     => is_numeric($top->v) ? (int) $top->v : trim((string) $top->v),
                'confianza' => $conf,
                'base'      => $total,
            ];
        }

        return response()->json(['ocupantes' => $ocupantes, 'sugerencias' => (object) $sug]);
    }
}
