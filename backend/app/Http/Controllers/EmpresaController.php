<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EmpresaController — ABM de Empresas (tabla empresas).
 *
 * Campos del formulario:
 *  EMP_COD código (incremental) · EMP_NOM nombre · EMP_DOM domicilio · EMP_PRV provincia
 *  EMP_CPO código postal · EMP_CUI CUIT · EMP_CAJ caja · EMP_NRO nro inscripción
 *  EMP_GCO gran contribuyente (S/N) · EMP_SER servicios eventuales (S/N) · EMP_RED redondeo (S/N)
 *  EMP_BAS base relacionada (1 SILCAR / 2 LOGISTICA) · EMP_CHA cuenta haberes
 *  EMP_BSFEMP Banco Santa Fe cód. empresa · EMP_BSFCON Banco Santa Fe cód. convenio
 */
class EmpresaController extends Controller
{
    /** @route GET /api/empresas-abm */
    public function index(): JsonResponse
    {
        $items = DB::table('empresas')->orderBy('EMP_COD')->get([
            'EMP_COD as cod',
            DB::raw('LTRIM(RTRIM(EMP_NOM)) as nombre'),
            DB::raw('LTRIM(RTRIM(EMP_DOM)) as domicilio'),
            DB::raw('LTRIM(RTRIM(EMP_PRV)) as provincia'),
            DB::raw('LTRIM(RTRIM(EMP_CPO)) as cpa'),
            DB::raw('LTRIM(RTRIM(EMP_CUI)) as cuit'),
            DB::raw('LTRIM(RTRIM(EMP_CAJ)) as caja'),
            DB::raw('LTRIM(RTRIM(EMP_NRO)) as inscripcion'),
            DB::raw('LTRIM(RTRIM(EMP_GCO)) as gran_contrib'),
            DB::raw('LTRIM(RTRIM(EMP_SER)) as servicios_ev'),
            DB::raw('LTRIM(RTRIM(EMP_RED)) as redondeo'),
            'EMP_BAS as base',
            DB::raw('LTRIM(RTRIM(EMP_CHA)) as cuenta_haberes'),
            DB::raw('LTRIM(RTRIM(EMP_BSFEMP)) as bsf_empresa'),
            DB::raw('LTRIM(RTRIM(EMP_BSFCON)) as bsf_convenio'),
        ]);

        return response()->json($items);
    }

    /** @route POST /api/empresas-abm */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $cod = (int) DB::table('empresas')->max('EMP_COD') + 1;

        DB::table('empresas')->insert(array_merge($this->mapear($datos), [
            'EMP_COD' => $cod,
            // Defaults de columnas que no maneja este formulario
            'EMP_LOC' => '',
            'EMP_CON' => '0',
            'EMP_SEG' => 0,
            'EMP_ANT' => 'N',
        ]));

        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/empresas-abm/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $datos = $this->validar($request);

        $afectados = DB::table('empresas')->where('EMP_COD', $cod)->update($this->mapear($datos));

        if ($afectados === 0 && !DB::table('empresas')->where('EMP_COD', $cod)->exists()) {
            return response()->json(['error' => 'Empresa no encontrada.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/empresas-abm/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        // No permitir borrar una empresa que tiene empleados asignados
        if (DB::table('personal')->where('PER_EMP', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay empleados asignados a esta empresa.'], 422);
        }

        $borrados = DB::table('empresas')->where('EMP_COD', $cod)->delete();
        if ($borrados === 0) {
            return response()->json(['error' => 'Empresa no encontrada.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /** Mapea los datos validados (claves del front) a columnas de la tabla. */
    private function mapear(array $d): array
    {
        return [
            'EMP_NOM'    => trim($d['nombre']),
            'EMP_DOM'    => trim($d['domicilio'] ?? ''),
            'EMP_PRV'    => trim($d['provincia'] ?? ''),
            'EMP_CPO'    => trim($d['cpa'] ?? ''),
            'EMP_CUI'    => trim($d['cuit'] ?? ''),
            'EMP_CAJ'    => trim($d['caja'] ?? ''),
            'EMP_NRO'    => trim($d['inscripcion'] ?? ''),
            'EMP_GCO'    => $d['gran_contrib'] ?? 'N',
            'EMP_SER'    => $d['servicios_ev'] ?? 'N',
            'EMP_RED'    => $d['redondeo'] ?? 'N',
            'EMP_BAS'    => (int) ($d['base'] ?? 1),
            'EMP_CHA'    => trim($d['cuenta_haberes'] ?? ''),
            'EMP_BSFEMP' => trim($d['bsf_empresa'] ?? ''),
            'EMP_BSFCON' => trim($d['bsf_convenio'] ?? ''),
        ];
    }

    /** Validación de los campos del formulario. */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'         => 'required|string|max:100',
            'domicilio'      => 'nullable|string|max:100',
            'provincia'      => 'nullable|string|max:30',
            'cpa'            => 'nullable|string|max:10',
            'cuit'           => 'nullable|string|max:14',
            'caja'           => 'nullable|string|max:20',
            'inscripcion'    => 'nullable|string|max:10',
            'gran_contrib'   => 'nullable|in:S,N',
            'servicios_ev'   => 'nullable|in:S,N',
            'redondeo'       => 'nullable|in:S,N',
            'base'           => 'nullable|integer|in:1,2',
            'cuenta_haberes' => 'nullable|string|max:20',
            'bsf_empresa'    => 'nullable|string|max:20',
            'bsf_convenio'   => 'nullable|string|max:20',
        ]);
    }
}
