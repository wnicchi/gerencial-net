<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HaberController — ABM de Haberes (tabla haberes) con convenio asociado.
 *
 * Campos: HAB_COD (código incremental), HAB_DES (descripción), HAB_ALI (alícuota),
 * HAB_IMP (importe), HAB_ANT (mult. antigüedad S/N), HAB_HOR (mult. valor hora S/N),
 * HAB_RET (sujeto a retenciones S/N), HAB_CAN (tiene cantidad S/N),
 * HAB_B30 (básico/30 S/N), HAB_TXT (cambia el texto S/N), HAB_CON/HAB_CDE (convenio).
 */
class HaberController extends Controller
{
    /** @route GET /api/haberes */
    public function index(): JsonResponse
    {
        return response()->json(
            DB::table('haberes')->orderBy('HAB_DES')->get([
                'HAB_COD as cod',
                DB::raw('LTRIM(RTRIM(HAB_DES)) as descripcion'),
                DB::raw('LTRIM(RTRIM(HAB_CDE)) as convenio_desc'),
                'HAB_CON as convenio',
                'HAB_ALI as alicuota',
                'HAB_IMP as importe',
                DB::raw('LTRIM(RTRIM(HAB_ANT)) as mult_antiguedad'),
                DB::raw('LTRIM(RTRIM(HAB_HOR)) as mult_valor_hora'),
                DB::raw('LTRIM(RTRIM(HAB_RET)) as sujeto_retenciones'),
                DB::raw('LTRIM(RTRIM(HAB_CAN)) as tiene_cantidad'),
                DB::raw('LTRIM(RTRIM(HAB_B30)) as basico30'),
                DB::raw('LTRIM(RTRIM(HAB_TXT)) as cambia_texto'),
            ])
        );
    }

    /** @route POST /api/haberes */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('haberes')->max('HAB_COD') + 1;
        DB::table('haberes')->insert(array_merge($this->mapear($datos), ['HAB_COD' => $cod]));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/haberes/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);
        $afectados = DB::table('haberes')->where('HAB_COD', $cod)->update($this->mapear($datos));
        if ($afectados === 0 && !DB::table('haberes')->where('HAB_COD', $cod)->exists()) {
            return response()->json(['error' => 'Haber no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/haberes/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $borrados = DB::table('haberes')->where('HAB_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Haber no encontrado.'], 404);
        }
        return response()->json(['ok' => true]);
    }

    private function mapear(array $d): array
    {
        $con = DB::table('convenio')->where('CON_COD', $d['convenio'])->first(['CON_DES']);
        $sn = fn ($v) => strtoupper(trim((string) $v)) === 'S' ? 'S' : 'N';

        return [
            'HAB_DES' => trim($d['descripcion']),
            'HAB_ALI' => $d['alicuota'] ?? 0,
            'HAB_IMP' => $d['importe'] ?? 0,
            'HAB_ANT' => $sn($d['mult_antiguedad'] ?? 'N'),
            'HAB_HOR' => $sn($d['mult_valor_hora'] ?? 'N'),
            'HAB_RET' => $sn($d['sujeto_retenciones'] ?? 'N'),
            'HAB_CAN' => $sn($d['tiene_cantidad'] ?? 'N'),
            'HAB_B30' => $sn($d['basico30'] ?? 'N'),
            'HAB_TXT' => $sn($d['cambia_texto'] ?? 'N'),
            'HAB_CON' => (int) $d['convenio'],
            'HAB_CDE' => $con ? substr(trim((string) $con->CON_DES), 0, 50) : '',
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'descripcion'        => 'required|string|max:100',
            'convenio'           => 'required|integer',
            'alicuota'           => 'nullable|numeric',
            'importe'            => 'nullable|numeric',
            'mult_antiguedad'    => 'nullable|in:S,N',
            'mult_valor_hora'    => 'nullable|in:S,N',
            'sujeto_retenciones' => 'nullable|in:S,N',
            'tiene_cantidad'     => 'nullable|in:S,N',
            'basico30'           => 'nullable|in:S,N',
            'cambia_texto'       => 'nullable|in:S,N',
        ]);
    }
}
