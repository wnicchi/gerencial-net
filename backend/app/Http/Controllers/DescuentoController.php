<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DescuentoController — ABM de Descuentos (tabla descuent) con convenio asociado.
 *
 * Campos: DES_COD código, DES_DES descripción, DES_SOC (es obra social S/N),
 * DES_ANS (es ANSAAL S/N), DES_JRE (multiplica coeficiente S/N),
 * DES_SIN (es sindicato S/N), DES_067 (es acuerdo S/N),
 * DES_VOF (estado V=variable / F=fijo), DES_ALI alícuota, DES_IMP importe,
 * DES_CON/DES_CDE convenio, DES_JOM (tipo del convenio J/M),
 * DES_NOR (va mensuales S/N), DES_1QU (va 1ra quincena S/N),
 * DES_2QU (va 2da quincena S/N), DES_SAC (va SAC S/N/M).
 */
class DescuentoController extends Controller
{
    /** @route GET /api/descuentos */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('descuent')->orderBy('DES_DES')->get([
                'DES_COD as cod',
                DB::raw('LTRIM(RTRIM(DES_DES)) as descripcion'),
                DB::raw('LTRIM(RTRIM(DES_CDE)) as convenio_desc'),
                'DES_CON as convenio',
                DB::raw('LTRIM(RTRIM(DES_VOF)) as estado'),
                'DES_ALI as alicuota',
                'DES_IMP as importe',
                DB::raw('LTRIM(RTRIM(DES_SOC)) as es_obra_social'),
                DB::raw('LTRIM(RTRIM(DES_ANS)) as es_ansaal'),
                DB::raw('LTRIM(RTRIM(DES_JRE)) as multiplica_coef'),
                DB::raw('LTRIM(RTRIM(DES_SIN)) as es_sindicato'),
                DB::raw('LTRIM(RTRIM(DES_067)) as es_acuerdo'),
                DB::raw('LTRIM(RTRIM(DES_NOR)) as va_mensuales'),
                DB::raw('LTRIM(RTRIM(DES_1QU)) as va_1q'),
                DB::raw('LTRIM(RTRIM(DES_2QU)) as va_2q'),
                DB::raw('LTRIM(RTRIM(DES_SAC)) as va_sac'),
                DB::raw('LTRIM(RTRIM(DES_JOM)) as tipo'),
            ])
        );
    }

    /** @route POST /api/descuentos */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('descuent')->max('DES_COD') + 1;
        DB::table('descuent')->insert(array_merge($this->mapear($datos), ['DES_COD' => $cod]));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/descuentos/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);
        $afectados = DB::table('descuent')->where('DES_COD', $cod)->update($this->mapear($datos));
        if ($afectados === 0 && !DB::table('descuent')->where('DES_COD', $cod)->exists()) {
            return response()->json(['error' => 'Descuento no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/descuentos/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $borrados = DB::table('descuent')->where('DES_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Descuento no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function mapear(array $d): array
    {
        $con = DB::table('convenio')->where('CON_COD', $d['convenio'])->first(['CON_DES', 'CON_JOM']);
        $jom = $con ? (trim((string) $con->CON_JOM) ?: 'M') : 'M';
        $sn  = fn ($v) => strtoupper(trim((string) $v)) === 'S' ? 'S' : 'N';
        $variable = strtoupper(trim((string) ($d['estado'] ?? 'V'))) === 'F' ? 'F' : 'V';
        $sac = strtoupper(trim((string) ($d['va_sac'] ?? 'N')));
        if (!in_array($sac, ['S', 'N', 'M'], true)) $sac = 'N';

        return [
            'DES_DES' => trim($d['descripcion']),
            'DES_SOC' => $sn($d['es_obra_social'] ?? 'N'),
            'DES_ANS' => $sn($d['es_ansaal'] ?? 'N'),
            'DES_JRE' => $sn($d['multiplica_coef'] ?? 'N'),
            'DES_SIN' => $sn($d['es_sindicato'] ?? 'N'),
            'DES_067' => $sn($d['es_acuerdo'] ?? 'N'),
            'DES_VOF' => $variable,
            'DES_ALI' => $d['alicuota'] ?? 0,
            'DES_IMP' => $d['importe'] ?? 0,
            'DES_CON' => (int) $d['convenio'],
            'DES_CDE' => $con ? substr(trim((string) $con->CON_DES), 0, 50) : '',
            'DES_JOM' => $jom,
            // Mensual → usa "va mensuales"; Jornal → usa quincenas
            'DES_NOR' => $jom === 'M' ? $sn($d['va_mensuales'] ?? 'N') : 'N',
            'DES_1QU' => $jom === 'J' ? $sn($d['va_1q'] ?? 'N') : 'N',
            'DES_2QU' => $jom === 'J' ? $sn($d['va_2q'] ?? 'N') : 'N',
            'DES_SAC' => $sac,
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion'     => 'required|string|max:100',
            'convenio'        => 'required|integer',
            'estado'          => 'nullable|in:V,F',
            'alicuota'        => 'nullable|numeric',
            'importe'         => 'nullable|numeric',
            'es_obra_social'  => 'nullable|in:S,N',
            'es_ansaal'       => 'nullable|in:S,N',
            'multiplica_coef' => 'nullable|in:S,N',
            'es_sindicato'    => 'nullable|in:S,N',
            'es_acuerdo'      => 'nullable|in:S,N',
            'va_mensuales'    => 'nullable|in:S,N',
            'va_1q'           => 'nullable|in:S,N',
            'va_2q'           => 'nullable|in:S,N',
            'va_sac'          => 'nullable|in:S,N,M',
        ]);
    }
}
