<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoFrancesController — Bco. Francés (BBVA) - Exportar (empleados_exportar_bco_frances.scx).
 *
 * Igual que los demás exportadores pero para BBVA Francés: tablas FRHABERES/FRHAB_ITEM, banco depósito 5
 * (Francés), suma PER_DOM/PER_LOC/PER_CPA/PER_CBU. El archivo se arma en el formato BBVA multi-registro
 * (líneas de 250 caracteres): cabecera 2110, por empleado 2210/2220/2230/2240, cierre 2910.
 *
 * FRH_NRO/FRI_NRO no son identity (FRI_NRO = FRH_NRO del lote padre); el número nuevo es max(ambos)+1.
 * El código de empresa del archivo es el literal "26239" (asignado por el BBVA); FRH_EMP = empresas.EMP_BSFEMP.
 */
class BancoFrancesController extends Controller
{
    private const BANCO = 5;             // Francés (ctas_ban)
    private const COD_BBVA = '26239';    // código de empresa asignado por el BBVA (fijo)

    /** @route GET /api/bco-frances/consultar */
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

        $yaExportado = [];
        foreach (DB::table('FRHABERES as a')->join('FRHAB_ITEM as b', 'a.FRH_NRO', '=', 'b.FRI_NRO')
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

    /** @route POST /api/bco-frances/generar */
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
            $nro = (int) max((int) DB::table('FRHABERES')->max('FRH_NRO'), (int) DB::table('FRHAB_ITEM')->max('FRI_NRO')) + 1;
            DB::table('FRHABERES')->insert(Registro::completar('FRHABERES', [
                'FRH_NRO' => $nro, 'FRH_EMP' => $codEmpresa, 'FRH_EMD' => trim((string) $emp->EMP_NOM),
                'FRH_CON' => (int) $emp->EMP_BSFCON, 'FRH_COD' => $modalidad, 'FRH_FEC' => $fImput->format('Y-m-d'), 'FRH_MON' => $total,
                'FRH_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario, 'FRH_TER' => 'RRHH.NET', 'FRH_SUE' => $label,
                'FRH_MESLIQ' => $mes, 'FRH_ANOLIQ' => $anio,
            ]));
            foreach ($d['filas'] as $f) {
                DB::table('FRHAB_ITEM')->insert(Registro::completar('FRHAB_ITEM', [
                    'FRI_NRO' => $nro, 'FRI_EMP' => (int) $f['cod'], 'FRI_EMD' => trim((string) $f['nombre']),
                    'FRI_CON' => (int) $emp->EMP_BSFCON, 'FRI_COD' => $modalidad, 'FRI_SUC' => (int) $f['suc'],
                    'FRI_CTA' => (int) $f['cta'], 'FRI_MON' => round((float) $f['monto'], 2), 'FRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        $nombreArchivo = 'H' . now()->format('Ymd');
        $contenido = self::txtBBVA($d['filas'], 'SILCAR LOGISTICA Y REPRESENTACIONES S.A.', $modalidad, $fEnvio, $fImput, $nombreArchivo, $total);
        return response()->json([
            'message' => 'TXT generado y lote registrado.', 'filename' => $nombreArchivo . '.TXT',
            'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nro, 'total' => $total, 'lineas' => count($d['filas']) * 4 + 2,
        ]);
    }

    /**
     * Arma el TXT BBVA (multi-registro, líneas de 250 caracteres).
     * @param array  $filas  cada fila: cod, cbu, monto, nombre, dom, loc, cpa, cuil
     */
    public static function txtBBVA(array $filas, string $ordenante, string $concepto, \Carbon\Carbon $fEnvio, \Carbon\Carbon $fImput, string $nombreArchivo, float $total): string
    {
        $iz = fn ($v, $n) => substr(str_pad((string) $v, $n, '0', STR_PAD_LEFT), -$n);
        // Windows-1252: el banco lee por byte y los acentos deben ocupar 1 byte (ver App\Support\Latin1).
        $de = fn ($v, $n) => \App\Support\Latin1::campo((string) $v, $n);
        $cod = self::COD_BBVA;
        $fecha = $fImput->format('Ymd');

        $lineas = [];
        // 2110 cabecera
        $lineas[] = '2110' . $cod . $fEnvio->format('Ymd') . $fImput->format('Ymd') . '0017' . '0478' . '92' . '0100015900'
            . 'HABERES   ' . 'ARS' . '0' . $de($nombreArchivo, 12) . $de($ordenante, 36) . '20' . str_repeat(' ', 141);

        foreach ($filas as $f) {
            $cents = (int) round(((float) $f['monto']) * 100); $ent = intdiv($cents, 100); $dec = $cents % 100;
            $cbu = $iz(preg_replace('/\D/', '', (string) ($f['cbu'] ?? '')), 22);
            $cuil = $iz(preg_replace('/\D/', '', (string) ($f['cuil'] ?? '')), 15);
            $pc = $iz((int) $f['cod'], 18);
            // 2210
            $lineas[] = '2210' . $cod . '  ' . $pc . str_repeat(' ', 4) . '1' . $cbu . str_repeat('0', 10)
                . $iz($ent, 13) . $iz($dec, 2) . str_repeat(' ', 6) . $fecha . $cuil . str_repeat(' ', 23) . ' ' . str_repeat(' ', 40) . str_repeat(' ', 76);
            // 2220
            $lineas[] = '2220' . $cod . '  ' . $pc . str_repeat(' ', 4) . $de(trim((string) $f['nombre']), 36) . $de(trim((string) ($f['dom'] ?? '')), 72) . str_repeat(' ', 109);
            // 2230
            $lineas[] = '2230' . $cod . '  ' . $pc . str_repeat(' ', 4) . $de(trim((string) ($f['loc'] ?? '')), 36) . $de('SANTA FE', 36) . $de(trim((string) ($f['cpa'] ?? '')), 36) . str_repeat(' ', 109);
            // 2240
            $lineas[] = '2240' . $cod . '  ' . $pc . str_repeat(' ', 4) . $de($concepto, 40) . str_repeat(' ', 177);
        }

        $cents = (int) round($total * 100); $ent = intdiv($cents, 100); $dec = $cents % 100;
        $totalLineas = count($lineas) + 1; // incluye el propio 2910
        $lineas[] = '2910' . $cod . $iz($ent, 13) . $iz($dec, 2) . $iz(count($filas), 8) . $iz($totalLineas, 10) . str_repeat(' ', 208);

        return implode("\r\n", $lineas) . "\r\n";
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
