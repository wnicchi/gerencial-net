<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SueldoNetoController — Exportar / Importar Sueldo Neto (sueldos_netos).
 *
 *  - Importar: actualiza personal.PER_SUE (neto) desde un Excel (código + sueldo),
 *    dejando registro en per_hist por cada empleado modificado.
 *  - Exportar: lista CODIGO, NOMBRE, NETO de los empleados activos, con filtros.
 */
class SueldoNetoController extends Controller
{
    /**
     * @route POST /api/sueldos-netos/importar  body: { rows:[{codigo, sueldo}] }
     */
    public function importar(Request $request): JsonResponse
    {
        $request->validate(['rows' => 'required|array|min:1']);
        $rows = $request->input('rows');
        $usuario = substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30);
        $terminal = substr($this->nombreTerminal($request), 0, 30);

        $actualizados = 0; $noEncontrados = 0;
        DB::transaction(function () use ($rows, $usuario, $terminal, &$actualizados, &$noEncontrados) {
            foreach ($rows as $r) {
                $cod = (int) preg_replace('/\D/', '', (string) ($r['codigo'] ?? ''));
                if ($cod === 0) continue;
                $sueldo = $this->aNumero($r['sueldo'] ?? 0);

                $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_SUE']);
                if (!$p) { $noEncontrados++; continue; }

                DB::table('per_hist')->insert(Registro::completar('per_hist', [
                    'hla_cod' => $cod, 'hla_fec' => now(), 'hla_usu' => $usuario, 'hla_ter' => $terminal,
                    'hla_cam' => 'Importación Sueldo Neto: ' . number_format($sueldo, 2, '.', '') .
                        ' (antes era ' . number_format((float) $p->PER_SUE, 2, '.', '') . ')',
                ]));
                DB::table('personal')->where('PER_COD', $cod)->update(['PER_SUE' => $sueldo]);
                $actualizados++;
            }
        });

        return response()->json(['ok' => true, 'actualizados' => $actualizados, 'no_encontrados' => $noEncontrados]);
    }

    /**
     * @route GET /api/sueldos-netos/exportar?empresa=&contratista=&lugar=&convenio=&categoria=&sector=&subsector=
     */
    public function exportar(Request $request): JsonResponse
    {
        $q = DB::table('personal')->where('PER_AOP', 'A');
        foreach ([
            'empresa' => 'PER_EMP', 'contratista' => 'PER_CONTRA', 'lugar' => 'PER_LUGAR', 'convenio' => 'PER_CON',
            'categoria' => 'PER_CAT', 'sector' => 'PER_SEC', 'subsector' => 'PER_SUC',
        ] as $param => $col) {
            if ($v = (int) $request->input($param, 0)) $q->where($col, $v);
        }
        $rows = $q->orderBy('PER_NOM')->get(['PER_COD', 'PER_NOM', 'PER_SUE'])->map(fn ($p) => [
            'codigo' => (int) $p->PER_COD, 'nombre' => trim((string) $p->PER_NOM), 'neto' => round((float) $p->PER_SUE, 2),
        ]);
        return response()->json($rows);
    }

    private function nombreTerminal(Request $request): string
    {
        $declarado = trim((string) $request->header('X-Terminal', ''));
        return $declarado !== '' ? strtoupper($declarado) : strtoupper((string) $request->ip());
    }

    private function aNumero($v): float
    {
        if (is_int($v) || is_float($v)) return round((float) $v, 2);
        $s = trim((string) $v);
        if ($s === '') return 0.0;
        if (str_contains($s, ',')) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
        $s = preg_replace('/[^\d.\-]/', '', $s);
        return round((float) $s, 2);
    }
}
