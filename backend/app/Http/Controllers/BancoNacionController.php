<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoNacionController — Bco. Nación - Exportar (empleados_exportar_bco_nacion.scx).
 *
 * Igual que el de Banco Francés (mismo formato de archivo BBVA de 250 caracteres), pero:
 *  - usa las tablas bchaberes/bchab_item (con columnas de prefijo FRH_ y FRI_),
 *  - banco depósito 3 (Banco de la Nación Argentina),
 *  - el ordenante del archivo es "AUTOELEVADORES SILCAR S.R.L.".
 *
 * Reutiliza BancoFrancesController::txtBBVA() para armar el TXT. FRH_NRO/FRI_NRO no son identity
 * (FRI_NRO = FRH_NRO del lote padre); el número nuevo es max(ambos)+1.
 */
class BancoNacionController extends Controller
{
    private const BANCO = 3;           // Banco de la Nación Argentina (ctas_ban)
    private const ORDENANTE = 'AUTOELEVADORES SILCAR S.R.L.';

    /** @route GET /api/bco-nacion/consultar */
    public function consultar(Request $request): JsonResponse
    {
        $d = $request->validate(['empresa' => 'required|integer|min:1', 'mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer']);
        $empresa = (int) $d['empresa']; $mes = (int) $d['mes']; $anio = (int) $d['anio'];
        $banco = (int) ($request->input('banco') ?: self::BANCO);
        $anticipos = (int) $request->input('anticipos', 0) === 1;
        $label = $this->etiquetaSueldo($request);

        $q = DB::table('personal')->where('PER_AOP', 'A')->where('PER_EMP', $empresa)->where('PER_BAN', $banco);
        foreach (['contratista' => 'PER_CONTRA', 'lugar' => 'PER_LUGAR', 'convenio' => 'PER_CON', 'categoria' => 'PER_CAT', 'empleado' => 'PER_COD'] as $p => $col) {
            if ($v = (int) $request->input($p, 0)) $q->where($col, $v);
        }
        $empleados = $q->orderBy('PER_NOM')
            ->get(['PER_COD', 'PER_NOM', 'PER_TDO', 'PER_NDO', 'PER_CUI', 'PER_BSFSUC', 'PER_BSFNRO', 'PER_DOM', 'PER_LOC', 'PER_CPA', 'PER_CBU', 'PER_ANTI']);
        if ($empleados->isEmpty()) return response()->json(['empleados' => [], 'total' => 0]);

        $codigos = $empleados->pluck('PER_COD')->map(fn ($c) => (int) $c)->all();
        $tipos = null;
        if ((int) $request->input('remuModo', 1) === 2 && ($tr = (int) $request->input('tipoRemu', 0))) $tipos = [$tr];

        $liq = DB::table('LIQUIDAC as l')->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->where('l.LIQ_MES', $mes)->where('l.LIQ_ANO', $anio)->whereIn('l.LIQ_COD', $codigos);
        if ($tipos) $liq->whereIn('l.LIQ_TIP', $tipos);
        $netos = $liq->groupBy('l.LIQ_COD', 'l.LIQ_TIP', 'l.LIQ_NRO')
            ->get(['l.LIQ_COD', 'l.LIQ_TIP', 'l.LIQ_NRO', DB::raw('SUM(li.LIT_HAB - li.LIT_DED) as neto')]);

        // Control de duplicados contra bchaberes/bchab_item (Banco Nación).
        $yaExportado = [];
        foreach (DB::table('bchaberes as a')->join('bchab_item as b', 'a.FRH_NRO', '=', 'b.FRI_NRO')
            ->where('a.FRH_ANU', '<>', 'S')->where('a.FRH_MESLIQ', $mes)->where('a.FRH_ANOLIQ', $anio)
            ->whereRaw('YEAR(b.FRI_FIM) = ?', [$anio])->whereRaw('UPPER(LTRIM(RTRIM(a.FRH_SUE))) = ?', [$label])
            ->whereIn('b.FRI_EMP', $codigos)->get(['b.FRI_EMP', 'b.FRI_MON']) as $r) {
            $yaExportado[(int) $r->FRI_EMP . '|' . round((float) $r->FRI_MON, 0)] = true;
        }

        $montoEmp = array_fill_keys($codigos, 0.0);
        foreach ($netos as $r) {
            $cod = (int) $r->LIQ_COD; $neto = round((float) $r->neto, 2);
            if (isset($yaExportado[$cod . '|' . round($neto, 0)])) continue;
            $montoEmp[$cod] += $neto;
        }

        $filas = []; $total = 0.0;
        foreach ($empleados as $e) {
            $cod = (int) $e->PER_COD;
            $monto = round($montoEmp[$cod] ?? 0, 2);
            if ($anticipos) $monto = round($monto + (float) $e->PER_ANTI, 2);
            $suc = (int) $e->PER_BSFSUC; $cta = (int) $e->PER_BSFNRO; $cbu = preg_replace('/\D/', '', (string) $e->PER_CBU);
            $total += $monto;
            $filas[] = [
                'elegir' => false, 'cod' => $cod, 'nombre' => trim((string) $e->PER_NOM),
                'tdoc' => trim((string) $e->PER_TDO), 'ndoc' => trim((string) $e->PER_NDO), 'cuil' => trim((string) $e->PER_CUI),
                'suc' => $suc, 'cta' => $cta, 'monto' => $monto, 'anti' => round((float) $e->PER_ANTI, 2),
                'dom' => trim((string) $e->PER_DOM), 'loc' => trim((string) $e->PER_LOC), 'cpa' => trim((string) $e->PER_CPA), 'cbu' => $cbu,
                'sinCuenta' => $suc === 0 || $cta === 0, 'sinCbu' => $cbu === '',
            ];
        }

        return response()->json(['empleados' => $filas, 'total' => round($total, 2), 'etiqueta' => $label]);
    }

    /** @route POST /api/bco-nacion/generar */
    public function generar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa'         => 'required|integer|min:1',
            'mes'             => 'required|integer|min:1|max:12',
            'anio'            => 'required|integer',
            'fechaEnvio'      => 'required|date',
            'fechaImputacion' => 'required|date',
            'modalidad'       => 'nullable|string',
            'filas'             => 'required|array|min:1',
            'filas.*.cod'       => 'required|integer',
            'filas.*.nombre'    => 'required|string',
            'filas.*.cuil'      => 'nullable|string',
            'filas.*.dom'       => 'nullable|string',
            'filas.*.loc'       => 'nullable|string',
            'filas.*.cpa'       => 'nullable|string',
            'filas.*.cbu'       => 'nullable|string',
            'filas.*.suc'       => 'required|integer',
            'filas.*.cta'       => 'required|integer',
            'filas.*.monto'     => 'required|numeric',
        ]);

        $emp = DB::table('empresas')->where('EMP_COD', (int) $d['empresa'])->first();
        if (!$emp) return response()->json(['message' => 'Empresa inexistente.'], 422);
        $codEmpresa = (int) $emp->EMP_BSFEMP;
        $modalidad = mb_strtoupper(trim((string) ($d['modalidad'] ?? '')));
        $mes = (int) $d['mes']; $anio = (int) $d['anio'];
        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);
        $label = $this->etiquetaSueldo($request);
        $usuario = strtoupper(substr((string) (optional($request->user())->name ?? 'RRHH.NET'), 0, 24));
        $total = round(array_sum(array_map(fn ($f) => (float) $f['monto'], $d['filas'])), 2);

        $nro = 0;
        DB::transaction(function () use ($d, $emp, $codEmpresa, $modalidad, $fEnvio, $fImput, $label, $total, $mes, $anio, $usuario, &$nro) {
            $nro = (int) max((int) DB::table('bchaberes')->max('FRH_NRO'), (int) DB::table('bchab_item')->max('FRI_NRO')) + 1;
            DB::table('bchaberes')->insert(Registro::completar('bchaberes', [
                'FRH_NRO' => $nro, 'FRH_EMP' => $codEmpresa, 'FRH_EMD' => trim((string) $emp->EMP_NOM),
                'FRH_CON' => (int) $emp->EMP_BSFCON, 'FRH_COD' => $modalidad, 'FRH_FEC' => $fImput->format('Y-m-d'), 'FRH_MON' => $total,
                'FRH_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario, 'FRH_TER' => 'RRHH.NET', 'FRH_SUE' => $label,
                'FRH_MESLIQ' => $mes, 'FRH_ANOLIQ' => $anio,
            ]));
            foreach ($d['filas'] as $f) {
                DB::table('bchab_item')->insert(Registro::completar('bchab_item', [
                    'FRI_NRO' => $nro, 'FRI_EMP' => (int) $f['cod'], 'FRI_EMD' => trim((string) $f['nombre']),
                    'FRI_CON' => (int) $emp->EMP_BSFCON, 'FRI_COD' => $modalidad, 'FRI_SUC' => (int) $f['suc'],
                    'FRI_CTA' => (int) $f['cta'], 'FRI_MON' => round((float) $f['monto'], 2), 'FRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        $nombreArchivo = 'H' . now()->format('Ymd');
        $contenido = BancoFrancesController::txtBBVA($d['filas'], self::ORDENANTE, $modalidad, $fEnvio, $fImput, $nombreArchivo, $total);
        return response()->json([
            'message' => 'TXT generado y lote registrado.', 'filename' => $nombreArchivo . '.TXT',
            'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nro, 'total' => $total, 'lineas' => count($d['filas']) * 4 + 2,
        ]);
    }

    private function etiquetaSueldo(Request $request): string
    {
        if ((int) $request->input('liquidacionModo', 1) === 2 && ($t = (int) $request->input('tipoSueldo', 0))) {
            $des = DB::table('sueldo_tip')->where('STI_COD', $t)->value('STI_DES');
            if ($des) return mb_strtoupper(trim((string) $des));
        }
        return 'COMPLETO';
    }
}
