<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoVariosController — Bcos. Varios (Santander) - Exportar (empleados_exportar_bco_varios.scx).
 *
 * Variante del exportador de Santander Río para pagar, a través de Santander, a empleados cuyo banco de
 * depósito NO es Santander Río (1), Santa Fe (2) ni Francés (5) — es decir, "bancos varios" vía CCI.
 * Diferencias respecto de Santander Río:
 *  - tablas varhaberes/varhab_item (columnas VR_ y VRI_),
 *  - no hay selector de banco: se excluyen los bancos 1, 2 y 5,
 *  - en el TXT (formato Piryp/Santander, 650 chars) el Nº de acuerdo es 001102 y la forma de pago 57 (CCI otros bancos).
 *
 * VR_NRO/VRI_NRO no son identity (VRI_NRO = VR_NRO del lote padre); el número nuevo es max(ambos)+1.
 */
class BancoVariosController extends Controller
{
    private const BANCOS_EXCLUIDOS = [1, 2, 5]; // Santander Río, Santa Fe, Francés

    /** @route GET /api/bco-varios/consultar */
    public function consultar(Request $request): JsonResponse
    {
        $d = $request->validate(['empresa' => 'required|integer|min:1', 'mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer']);
        $empresa = (int) $d['empresa']; $mes = (int) $d['mes']; $anio = (int) $d['anio'];
        $anticipos = (int) $request->input('anticipos', 0) === 1;
        $label = $this->etiquetaSueldo($request);

        $q = DB::table('personal')->where('PER_AOP', 'A')->where('PER_EMP', $empresa)->whereNotIn('PER_BAN', self::BANCOS_EXCLUIDOS);
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
        foreach (DB::table('varhaberes as a')->join('varhab_item as b', 'a.VR_NRO', '=', 'b.VRI_NRO')
            ->where('a.VR_ANU', '<>', 'S')->where('a.VR_MESLIQ', $mes)->where('a.VR_ANOLIQ', $anio)
            ->whereRaw('YEAR(b.VRI_FIM) = ?', [$anio])->whereRaw('UPPER(LTRIM(RTRIM(a.VR_SUE))) = ?', [$label])
            ->whereIn('b.VRI_EMP', $codigos)->get(['b.VRI_EMP', 'b.VRI_MON']) as $r) {
            $yaExportado[(int) $r->VRI_EMP . '|' . round((float) $r->VRI_MON, 0)] = true;
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

    /** @route POST /api/bco-varios/generar  (tipo = txt | excel) */
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
            $nro = (int) max((int) DB::table('varhaberes')->max('VR_NRO'), (int) DB::table('varhab_item')->max('VRI_NRO')) + 1;
            DB::table('varhaberes')->insert(Registro::completar('varhaberes', [
                'VR_NRO' => $nro, 'VR_EMP' => $codEmpresa, 'VR_EMD' => trim((string) $emp->EMP_NOM),
                'VR_CON' => 1, 'VR_COD' => $modalidad, 'VR_FEC' => $fImput->format('Y-m-d'), 'VR_MON' => $total,
                'VR_ORD' => $envia ? 1 : 0, 'VR_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario, 'VR_TER' => 'RRHH.NET',
                'VR_SUE' => $label, 'VR_MESLIQ' => $mes, 'VR_ANOLIQ' => $anio,
            ]));
            foreach ($d['filas'] as $f) {
                DB::table('varhab_item')->insert(Registro::completar('varhab_item', [
                    'VRI_NRO' => $nro, 'VRI_EMP' => (int) $f['cod'], 'VRI_EMD' => trim((string) $f['nombre']),
                    'VRI_CON' => (int) $emp->EMP_BSFCON, 'VRI_COD' => $modalidad, 'VRI_SUC' => (int) $f['suc'],
                    'VRI_CTA' => (int) $f['cta'], 'VRI_MON' => round((float) $f['monto'], 2), 'VRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        if ($d['tipo'] === 'excel') {
            $contenido = self::excel($d['filas'], $fImput);
            $nombre = 'BANCO_RIO_' . self::iz($codEmpresa, 4) . self::iz((int) $modalidad, 4) . '.xls';
            return response()->json(['message' => 'Excel generado y lote registrado.', 'filename' => $nombre, 'contenido' => $contenido, 'mime' => 'application/vnd.ms-excel', 'lote' => $nro, 'total' => $total]);
        }

        $contenido = self::txt($d['filas'], $cuit, $orden, $fImput, $mes, $anio, $total);
        $nombre = 'MOVI' . self::iz($codEmpresa, 4) . self::iz((int) $modalidad, 4) . '.TXT';
        // TXT en Windows-1252 (bytes crudos): base64 para no re-codificarse a UTF-8 en el JSON/Blob.
        return response()->json(['message' => 'TXT generado y lote registrado.', 'filename' => $nombre, 'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nro, 'total' => $total, 'lineas' => count($d['filas']) + 2]);
    }

    /** Arma el TXT Piryp/Santander para bancos varios (registros de 650 caracteres; acuerdo 001102, forma de pago 57). */
    public static function txt(array $filas, string $cuit, int $orden, \Carbon\Carbon $fImput, int $mes, int $anio, float $total): string
    {
        $iz = fn ($v, $n) => self::iz($v, $n); $de = fn ($v, $n) => self::de($v, $n);
        $lineas = [];
        // Cabecera H (acuerdo 001102)
        $lineas[] = 'H' . $cuit . '001102' . '007' . $iz($orden, 5) . '00000' . str_repeat(' ', 7) . 'S' . str_repeat(' ', 611);

        $nroComprob = $iz(sprintf('%04d%02d', $anio, $mes), 15);
        $fecha = $fImput->format('Ymd');
        foreach ($filas as $f) {
            $importe = $iz((int) round(((float) $f['monto']) * 100), 15);
            $cbu = $iz(preg_replace('/\D/', '', (string) ($f['cbu'] ?? '')), 26);
            $cuil = $iz(preg_replace('/\D/', '', (string) ($f['cuil'] ?? '')), 11);
            $d = 'D' . ' ' . '0'
               . $de((string) $f['cod'], 15) . 'RC' . $nroComprob . '0000'
               . $de(trim((string) $f['nombre']), 30) . $de(trim((string) ($f['dom'] ?? '')), 51)
               . '00000' . '    ' . str_repeat('0', 83) . str_repeat(' ', 11) . $cuil . str_repeat(' ', 162)
               . 'N' . '0054' . $cbu . str_repeat('0', 8) . $fecha . $importe . '57' // 57 = CCI otros bancos
               . str_repeat(' ', 3) . str_repeat('0', 11) . str_repeat(' ', 3) . str_repeat('0', 11)
               . str_repeat(' ', 3) . str_repeat('0', 11) . str_repeat(' ', 3) . str_repeat('0', 25)
               . str_repeat(' ', 1) . str_repeat('0', 17) . str_repeat(' ', 102);
            $lineas[] = $d;
        }
        // Total T
        $lineas[] = 'T' . str_repeat('0', 15) . $iz((int) round($total * 100), 15) . $iz(count($filas), 7) . str_repeat(' ', 612);

        return implode("\r\n", $lineas) . "\r\n";
    }

    /** Arma el Excel (HTML .xls). */
    public static function excel(array $filas, \Carbon\Carbon $fImput): string
    {
        $fecha = $fImput->format('d/m/Y'); $rows = '';
        foreach ($filas as $f) {
            $rows .= '<tr><td>D</td><td>50</td><td></td><td></td>'
                . '<td>' . htmlspecialchars((string) ($f['cuil'] ?? '')) . '</td><td>' . (int) $f['cod'] . '</td>'
                . '<td>' . htmlspecialchars(trim((string) $f['nombre'])) . '</td>'
                . '<td>' . number_format((float) $f['monto'], 2, ',', '') . '</td>'
                . '<td>' . htmlspecialchars((string) ($f['cbu'] ?? '')) . '</td><td>' . $fecha . '</td></tr>';
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

    public static function iz($valor, int $n): string { return substr(str_pad((string) $valor, $n, '0', STR_PAD_LEFT), -$n); }
    public static function de(string $valor, int $n): string { return \App\Support\Latin1::campo($valor, $n); }
}
