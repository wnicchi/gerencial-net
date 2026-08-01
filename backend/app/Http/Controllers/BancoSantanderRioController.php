<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoSantanderRioController — Bco. Santander Río - Exportar (empleados_exportar_bco_santander_rio.scx).
 *
 * Gemelo de Bco. Santa Fe - Exportar pero para Santander Río: usa las tablas SRHABERES/SRHAB_ITEM,
 * el banco depósito 1 (Santander Río), suma PER_DOM y PER_CBU, y genera el archivo en el formato
 * "Piryp / Santander" (registros de 650 caracteres: cabecera H, un detalle D por empleado, total T)
 * o un Excel para el banco. Tanto el TXT como el Excel registran el lote en SRHABERES/SRHAB_ITEM.
 *
 * SRH_NRO/SRI_NRO no son identity (SRI_NRO = SRH_NRO del lote padre); el número nuevo es max(ambos)+1.
 */
class BancoSantanderRioController extends Controller
{
    private const BANCO = 1; // Santander Río

    /** @route GET /api/bco-santander-rio/consultar */
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
            ->get(['PER_COD', 'PER_NOM', 'PER_TDO', 'PER_NDO', 'PER_CUI', 'PER_BSFSUC', 'PER_BSFNRO', 'PER_DOM', 'PER_CBU', 'PER_ANTI']);
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
        foreach (DB::table('SRHABERES as a')->join('SRHAB_ITEM as b', 'a.SRH_NRO', '=', 'b.SRI_NRO')
            ->where('a.SRH_ANU', '<>', 'S')->where('a.SRH_MESLIQ', $mes)->where('a.SRH_ANOLIQ', $anio)
            ->whereRaw('YEAR(b.SRI_FIM) = ?', [$anio])->whereRaw('UPPER(LTRIM(RTRIM(a.SRH_SUE))) = ?', [$label])
            ->whereIn('b.SRI_EMP', $codigos)->get(['b.SRI_EMP', 'b.SRI_MON']) as $r) {
            $yaExportado[(int) $r->SRI_EMP . '|' . round((float) $r->SRI_MON, 0)] = true;
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
                'dom' => trim((string) $e->PER_DOM), 'cbu' => $cbu,
                'sinCuenta' => $suc === 0 || $cta === 0, 'sinCbu' => $cbu === '',
            ];
        }

        return response()->json(['empleados' => $filas, 'total' => round($total, 2), 'etiqueta' => $label]);
    }

    /** @route POST /api/bco-santander-rio/generar  (tipo = txt | excel) */
    public function generar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tipo'            => 'required|in:txt,excel',
            'empresa'         => 'required|integer|min:1',
            'mes'             => 'required|integer|min:1|max:12',
            'anio'            => 'required|integer',
            'fechaEnvio'      => 'required|date',
            'fechaImputacion' => 'required|date',
            'modalidad'       => 'nullable|string',
            'orden'           => 'nullable|integer',
            'enviaAlBanco'    => 'nullable|boolean',
            'filas'             => 'required|array|min:1',
            'filas.*.cod'       => 'required|integer',
            'filas.*.nombre'    => 'required|string',
            'filas.*.cuil'      => 'nullable|string',
            'filas.*.dom'       => 'nullable|string',
            'filas.*.cbu'       => 'nullable|string',
            'filas.*.suc'       => 'required|integer',
            'filas.*.cta'       => 'required|integer',
            'filas.*.monto'     => 'required|numeric',
        ]);

        $emp = DB::table('empresas')->where('EMP_COD', (int) $d['empresa'])->first();
        if (!$emp) return response()->json(['message' => 'Empresa inexistente.'], 422);
        $codEmpresa  = (int) $emp->EMP_BSFEMP;
        $codConvenio = (int) $emp->EMP_BSFCON;
        $cuit = substr(preg_replace('/\D/', '', (string) $emp->EMP_CUI) . str_repeat(' ', 11), 0, 11);
        $modalidad = trim((string) ($d['modalidad'] ?? ''));
        $orden = (int) ($d['orden'] ?? 1);
        $envia = (bool) ($d['enviaAlBanco'] ?? false);

        $mes = (int) $d['mes']; $anio = (int) $d['anio'];
        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);
        $label = $this->etiquetaSueldo($request);
        $usuario = strtoupper(substr((string) (optional($request->user())->name ?? 'RRHH.NET'), 0, 24));
        $total = round(array_sum(array_map(fn ($f) => (float) $f['monto'], $d['filas'])), 2);

        $nro = 0;
        DB::transaction(function () use ($d, $emp, $codEmpresa, $modalidad, $envia, $fEnvio, $fImput, $label, $total, $mes, $anio, $usuario, &$nro) {
            $nro = (int) max((int) DB::table('SRHABERES')->max('SRH_NRO'), (int) DB::table('SRHAB_ITEM')->max('SRI_NRO')) + 1;
            DB::table('SRHABERES')->insert(Registro::completar('SRHABERES', [
                'SRH_NRO' => $nro, 'SRH_EMP' => $codEmpresa, 'SRH_EMD' => trim((string) $emp->EMP_NOM),
                'SRH_CON' => 1, 'SRH_COD' => $modalidad, 'SRH_FEC' => $fImput->format('Y-m-d'), 'SRH_MON' => $total,
                'SRH_ORD' => $envia ? 1 : 0, 'SRH_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario, 'SRH_TER' => 'RRHH.NET',
                'SRH_SUE' => $label, 'SRH_MESLIQ' => $mes, 'SRH_ANOLIQ' => $anio,
            ]));
            foreach ($d['filas'] as $f) {
                DB::table('SRHAB_ITEM')->insert(Registro::completar('SRHAB_ITEM', [
                    'SRI_NRO' => $nro, 'SRI_EMP' => (int) $f['cod'], 'SRI_EMD' => trim((string) $f['nombre']),
                    'SRI_CON' => (int) $emp->EMP_BSFCON, 'SRI_COD' => $modalidad, 'SRI_SUC' => (int) $f['suc'],
                    'SRI_CTA' => (int) $f['cta'], 'SRI_MON' => round((float) $f['monto'], 2), 'SRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        if ($d['tipo'] === 'excel') {
            $contenido = $this->excel($d['filas'], $fImput);
            $nombre = 'BANCO_RIO_' . $this->iz($codEmpresa, 4) . $this->iz((int) $modalidad, 4) . '.xls';
            return response()->json(['message' => 'Excel generado y lote registrado.', 'filename' => $nombre, 'contenido' => $contenido, 'mime' => 'application/vnd.ms-excel', 'lote' => $nro, 'total' => $total]);
        }

        $contenido = $this->txt($d['filas'], $cuit, $orden, $fImput, $mes, $anio, $total);
        $nombre = 'MOVI' . $this->iz($codEmpresa, 4) . $this->iz((int) $modalidad, 4) . '.TXT';
        // El TXT está en Windows-1252 (bytes crudos): se manda en base64 para que no se
        // re-codifique a UTF-8 al pasar por JSON y por el Blob del navegador.
        return response()->json(['message' => 'TXT generado y lote registrado.', 'filename' => $nombre, 'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nro, 'total' => $total, 'lineas' => count($d['filas']) + 2]);
    }

    /** Arma el TXT Piryp/Santander (registros de 650 caracteres). */
    private function txt(array $filas, string $cuit, int $orden, \Carbon\Carbon $fImput, int $mes, int $anio, float $total): string
    {
        $lineas = [];
        // Cabecera H
        $lineas[] = 'H' . $cuit . '001101' . '007' . $this->iz($orden, 5) . '00000' . str_repeat(' ', 7) . 'S' . str_repeat(' ', 611);

        $nroComprob = $this->iz(sprintf('%04d%02d', $anio, $mes), 15);
        $fecha = $fImput->format('Ymd');
        foreach ($filas as $f) {
            $importe = $this->iz((int) round(((float) $f['monto']) * 100), 15);
            $cbu = $this->iz(preg_replace('/\D/', '', (string) ($f['cbu'] ?? '')), 26);
            $cuil = $this->iz(preg_replace('/\D/', '', (string) ($f['cuil'] ?? '')), 11);
            $d = 'D' . ' ' . '0'
               . $this->de((string) $f['cod'], 15) . 'RC' . $nroComprob . '0000'
               . $this->de(trim((string) $f['nombre']), 30) . $this->de(trim((string) ($f['dom'] ?? '')), 51)
               . '00000' . '    ' . str_repeat('0', 83) . str_repeat(' ', 11) . $cuil . str_repeat(' ', 162)
               . 'N' . '0054' . $cbu . str_repeat('0', 8) . $fecha . $importe . '50'
               . str_repeat(' ', 3) . str_repeat('0', 11) . str_repeat(' ', 3) . str_repeat('0', 11)
               . str_repeat(' ', 3) . str_repeat('0', 11) . str_repeat(' ', 3) . str_repeat('0', 25)
               . str_repeat(' ', 1) . str_repeat('0', 17) . str_repeat(' ', 102);
            $lineas[] = $d;
        }
        // Total T
        $lineas[] = 'T' . str_repeat('0', 15) . $this->iz((int) round($total * 100), 15) . $this->iz(count($filas), 7) . str_repeat(' ', 612);

        return implode("\r\n", $lineas) . "\r\n";
    }

    /** Arma el Excel (HTML .xls) para el banco. */
    private function excel(array $filas, \Carbon\Carbon $fImput): string
    {
        $fecha = $fImput->format('d/m/Y');
        $rows = '';
        foreach ($filas as $f) {
            $rows .= '<tr>'
                . '<td>D</td><td>50</td><td></td><td></td>'
                . '<td>' . htmlspecialchars((string) ($f['cuil'] ?? '')) . '</td>'
                . '<td>' . (int) $f['cod'] . '</td>'
                . '<td>' . htmlspecialchars(trim((string) $f['nombre'])) . '</td>'
                . '<td>' . number_format((float) $f['monto'], 2, ',', '') . '</td>'
                . '<td>' . htmlspecialchars((string) ($f['cbu'] ?? '')) . '</td>'
                . '<td>' . $fecha . '</td></tr>';
        }
        return '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body><table border="1">'
            . '<tr><th>Cabecera</th><th>Forma Pago</th><th>Tipo Comp.</th><th>Nro Comp.</th><th>CUIL</th><th>Nro Empleado</th><th>Nombre</th><th>Importe</th><th>CBU</th><th>Fecha Pago</th></tr>'
            . $rows . '</table></body></html>';
    }

    private function etiquetaSueldo(Request $request): string
    {
        if ((int) $request->input('liquidacionModo', 1) === 2 && ($t = (int) $request->input('tipoSueldo', 0))) {
            $des = DB::table('sueldo_tip')->where('STI_COD', $t)->value('STI_DES');
            if ($des) return mb_strtoupper(trim((string) $des));
        }
        return 'COMPLETO';
    }

    /** Rellena con ceros a la izquierda hasta $n. */
    private function iz($valor, int $n): string
    {
        return substr(str_pad((string) $valor, $n, '0', STR_PAD_LEFT), -$n);
    }

    /** Texto a la izquierda en Windows-1252, relleno con espacios y recortado a $n (ver App\Support\Latin1). */
    private function de(string $valor, int $n): string
    {
        return \App\Support\Latin1::campo($valor, $n);
    }
}
