<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoSantanderRioConsultarController — Bco. Santander Río - Consultar (empleados_consultar_bco_santander_rio.scx).
 *
 * Gemelo de Bco. Santa Fe - Consultar pero sobre SRHABERES/SRHAB_ITEM y banco de Gestión 1 (Santander Río):
 *  - lotes() / beneficiarios(): listado y detalle.
 *  - generarMovimiento(): registra el lote en gestion PAGOS_AUTORIZAR (OPA_BAN=1, "SANTANDER RIO") para
 *    autorización de Gerencia y marca SRH_PAG. Si ya fue enviado, no duplica.
 *  - eliminar(): SRH_ANU='S' (+ OPA_AUTANU='S' en Gestión si estaba pagado).
 *  - generarNuevo(): regenera TXT (formato Piryp/Santander, 650 chars) o Excel con los beneficiarios
 *    editados, releyendo los datos del padrón (nombre/domicilio/CUIL/CBU), creando un NUEVO lote.
 */
class BancoSantanderRioConsultarController extends Controller
{
    private const BANCO = 1; // Santander Río

    /** @route GET /api/bco-santander-rio/lotes?empresa= */
    public function lotes(Request $request): JsonResponse
    {
        $q = DB::table('SRHABERES');
        if ($emp = (int) $request->input('empresa', 0)) {
            $bsf = (int) (DB::table('empresas')->where('EMP_COD', $emp)->value('EMP_BSFEMP') ?? 0);
            if ($bsf) $q->where('SRH_EMP', $bsf);
        }
        $rows = $q->orderByDesc('SRH_NRO')
            ->get(['SRH_NRO', 'SRH_EMD', 'SRH_SUE', 'SRH_COD', 'SRH_FEC', 'SRH_MON', 'SRH_ANU', 'SRH_PAG'])
            ->map(fn ($r) => [
                'elegir' => false, 'nro' => (int) $r->SRH_NRO, 'empresa' => trim((string) $r->SRH_EMD),
                'concepto' => trim((string) $r->SRH_SUE), 'tipoArc' => trim((string) $r->SRH_COD),
                'fecha' => $this->fecha($r->SRH_FEC), 'monto' => round((float) $r->SRH_MON, 2),
                'anulado' => strtoupper(trim((string) $r->SRH_ANU)) === 'S', 'pagado' => (int) $r->SRH_PAG === 1,
            ]);
        return response()->json(['lotes' => $rows->values()]);
    }

    /** @route GET /api/bco-santander-rio/beneficiarios?lote= */
    public function beneficiarios(Request $request): JsonResponse
    {
        $lote = (int) $request->validate(['lote' => 'required|integer'])['lote'];
        $rows = DB::table('SRHAB_ITEM as i')->leftJoin('personal as p', 'i.SRI_EMP', '=', 'p.PER_COD')
            ->where('i.SRI_NRO', $lote)->orderBy('i.SRI_EMD')
            ->get(['i.SRI_EMP', 'i.SRI_EMD', 'i.SRI_SUC', 'i.SRI_CTA', 'i.SRI_MON', 'p.PER_LEG'])
            ->map(fn ($r) => [
                'elegir' => false, 'emp' => (int) $r->SRI_EMP, 'emd' => trim((string) $r->SRI_EMD),
                'suc' => (int) $r->SRI_SUC, 'cta' => (int) $r->SRI_CTA, 'mon' => round((float) $r->SRI_MON, 2),
                'montoInicial' => round((float) $r->SRI_MON, 2), 'leg' => (int) ($r->PER_LEG ?? 0),
            ]);
        return response()->json(['beneficiarios' => $rows->values()]);
    }

    /** @route POST /api/bco-santander-rio/generar-movimiento */
    public function generarMovimiento(Request $request): JsonResponse
    {
        $d = $request->validate([
            'lotes' => 'required|array|min:1', 'lotes.*' => 'integer',
            'tipoSueldo' => 'required|integer|min:1', 'nroBancario' => 'required|integer|min:1', 'empresa' => 'required|integer|min:1',
        ]);
        $tipoDesc = trim((string) (DB::table('sueldo_tip')->where('STI_COD', (int) $d['tipoSueldo'])->value('STI_DES') ?? ''));
        $opaEmp = mb_strtoupper(substr(trim((string) (DB::table('empresas')->where('EMP_COD', (int) $d['empresa'])->value('EMP_NOM') ?? '')), 0, 30));
        $opaEmp = str_contains($opaEmp, 'LOG') ? 'L' : 'S';
        $usuario = trim((string) (optional($request->user())->name ?? 'RRHH.NET'));

        $resultados = [];
        foreach ($d['lotes'] as $nro) {
            $nro = (int) $nro;
            $srh = DB::table('SRHABERES')->where('SRH_NRO', $nro)->first();
            if (!$srh) { $resultados[] = ['lote' => $nro, 'estado' => 'inexistente']; continue; }
            try {
                $gx = DB::connection('gestion');
                $previo = $gx->table('PAGOS_AUTORIZAR')->where('OPA_EMP', $opaEmp)->where('OPA_REG', $nro)->where('OPA_BAN', self::BANCO)->exists();
                if ($previo) {
                    DB::table('SRHABERES')->where('SRH_NRO', $nro)->update(['SRH_PAG' => 1]);
                    $resultados[] = ['lote' => $nro, 'estado' => 'ya_enviado'];
                    continue;
                }
                $proximo = (int) ($gx->table('PAGOS_AUTORIZAR')->max('OPA_AUT')) + 1;
                $gx->table('PAGOS_AUTORIZAR')->insert([
                    'OPA_AUT' => $proximo, 'OPA_EMP' => $opaEmp, 'OPA_FEC' => $this->desdeUsu($srh->SRH_USU), 'OPA_BAN' => self::BANCO,
                    'OPA_BAD' => 'SANTANDER RIO', 'OPA_DET' => $tipoDesc, 'OPA_IMP' => round((float) $srh->SRH_MON, 2),
                    'OPA_REG' => $nro, 'OPA_ENV' => $this->soloFecha($srh->SRH_FEC), 'OPA_USU' => 0, 'OPA_USN' => mb_strtoupper($usuario),
                    'OPA_ASN' => 'N', 'OPA_AUF' => '1900-01-01', 'OPA_AUH' => '', 'OPA_AUU' => 0, 'OPA_AUN' => '',
                    'OPA_PAG' => 0, 'OPA_CTA' => (int) $d['tipoSueldo'], 'OPA_CTD' => $tipoDesc, 'OPA_PER' => 0, 'OPA_NOM' => '',
                    'OPA_EMBSN' => '', 'OPA_EMBNOM' => '', 'OPA_EMBCBU' => '', 'OPA_EMBMES' => 0, 'OPA_EMBANO' => 0,
                    'OPA_LIQ' => 0, 'OPA_AUTBCO' => (int) $d['nroBancario'], 'OPA_AUTANU' => '',
                ]);
                DB::table('SRHABERES')->where('SRH_NRO', $nro)->update(['SRH_PAG' => 1]);
                $resultados[] = ['lote' => $nro, 'estado' => 'ok', 'autorizacion' => $proximo];
            } catch (\Throwable $e) {
                \Log::error('[BcoSantanderRio generarMovimiento] lote ' . $nro . ': ' . $e->getMessage());
                $resultados[] = ['lote' => $nro, 'estado' => 'error', 'detalle' => $e->getMessage()];
            }
        }
        $ok = count(array_filter($resultados, fn ($r) => $r['estado'] === 'ok'));
        return response()->json(['message' => "Procesado. Pagos generados: $ok.", 'resultados' => $resultados]);
    }

    /** @route POST /api/bco-santander-rio/eliminar */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['lotes' => 'required|array|min:1', 'lotes.*' => 'integer', 'nroBancario' => 'nullable|integer']);
        $nroBancario = (int) ($d['nroBancario'] ?? 0);
        $anulados = 0;
        foreach ($d['lotes'] as $nro) {
            $nro = (int) $nro;
            $srh = DB::table('SRHABERES')->where('SRH_NRO', $nro)->first();
            if (!$srh) continue;
            DB::table('SRHABERES')->where('SRH_NRO', $nro)->update(['SRH_ANU' => 'S']);
            $anulados++;
            if ((int) $srh->SRH_PAG === 1 && $nroBancario > 0) {
                try {
                    DB::connection('gestion')->table('PAGOS_AUTORIZAR')
                        ->where('OPA_BAN', self::BANCO)->where('OPA_AUTBCO', $nroBancario)->update(['OPA_AUTANU' => 'S']);
                } catch (\Throwable $e) { \Log::error('[BcoSantanderRio eliminar] ' . $e->getMessage()); }
            }
        }
        return response()->json(['message' => "Lote(s) anulado(s): $anulados.", 'anulados' => $anulados]);
    }

    /** @route POST /api/bco-santander-rio/generar-nuevo  (tipo = txt | excel) */
    public function generarNuevo(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tipo'            => 'required|in:txt,excel',
            'loteOrigen'      => 'required|integer',
            'fechaEnvio'      => 'required|date',
            'fechaImputacion' => 'required|date',
            'orden'           => 'nullable|integer',
            'enviaAlBanco'    => 'nullable|boolean',
            'beneficiarios'         => 'required|array|min:1',
            'beneficiarios.*.emp'   => 'required|integer',
            'beneficiarios.*.mon'   => 'required|numeric',
        ]);

        $origen = DB::table('SRHABERES')->where('SRH_NRO', (int) $d['loteOrigen'])->first();
        if (!$origen) return response()->json(['message' => 'Lote de origen inexistente.'], 422);
        $emp = DB::table('empresas')->where('EMP_BSFEMP', (int) $origen->SRH_EMP)->first();
        $cuit = substr(preg_replace('/\D/', '', (string) ($emp->EMP_CUI ?? '')) . str_repeat(' ', 11), 0, 11);
        $codEmpresa = (int) $origen->SRH_EMP; $codConvenio = (int) $origen->SRH_CON;
        $modalidad = trim((string) $origen->SRH_COD);
        $orden = (int) ($d['orden'] ?? 1);
        $usuario = strtoupper(substr((string) (optional($request->user())->name ?? 'RRHH.NET'), 0, 24));

        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);

        // Releer padrón de los beneficiarios y armar filas con el monto editado.
        $cods = array_map(fn ($b) => (int) $b['emp'], $d['beneficiarios']);
        $personal = DB::table('personal')->whereIn('PER_COD', $cods)
            ->get(['PER_COD', 'PER_NOM', 'PER_DOM', 'PER_CUI', 'PER_CBU', 'PER_BSFSUC', 'PER_BSFNRO'])->keyBy(fn ($p) => (int) $p->PER_COD);
        $filas = [];
        foreach ($d['beneficiarios'] as $b) {
            $p = $personal->get((int) $b['emp']);
            if (!$p) continue;
            $filas[] = [
                'cod' => (int) $p->PER_COD, 'nombre' => trim((string) $p->PER_NOM), 'dom' => trim((string) $p->PER_DOM),
                'cuil' => trim((string) $p->PER_CUI), 'cbu' => preg_replace('/\D/', '', (string) $p->PER_CBU),
                'suc' => (int) $p->PER_BSFSUC, 'cta' => (int) $p->PER_BSFNRO, 'monto' => round((float) $b['mon'], 2),
            ];
        }
        if (!$filas) return response()->json(['message' => 'No hay beneficiarios válidos.'], 422);
        $total = round(array_sum(array_column($filas, 'monto')), 2);

        $nuevoNro = 0;
        DB::transaction(function () use ($filas, $origen, $codEmpresa, $codConvenio, $modalidad, $fEnvio, $fImput, $usuario, $total, $d, &$nuevoNro) {
            $nuevoNro = (int) max((int) DB::table('SRHABERES')->max('SRH_NRO'), (int) DB::table('SRHAB_ITEM')->max('SRI_NRO')) + 1;
            DB::table('SRHABERES')->insert(Registro::completar('SRHABERES', [
                'SRH_NRO' => $nuevoNro, 'SRH_EMP' => $codEmpresa, 'SRH_EMD' => trim((string) $origen->SRH_EMD),
                'SRH_CON' => 1, 'SRH_COD' => $modalidad, 'SRH_FEC' => $fImput->format('Y-m-d'), 'SRH_MON' => $total,
                'SRH_ORD' => ($d['enviaAlBanco'] ?? false) ? 1 : 0, 'SRH_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario,
                'SRH_TER' => 'RRHH.NET', 'SRH_SUE' => trim((string) $origen->SRH_SUE),
                'SRH_MESLIQ' => (int) $origen->SRH_MESLIQ, 'SRH_ANOLIQ' => (int) $origen->SRH_ANOLIQ,
            ]));
            foreach ($filas as $f) {
                DB::table('SRHAB_ITEM')->insert(Registro::completar('SRHAB_ITEM', [
                    'SRI_NRO' => $nuevoNro, 'SRI_EMP' => $f['cod'], 'SRI_EMD' => $f['nombre'], 'SRI_CON' => $codConvenio,
                    'SRI_COD' => $modalidad, 'SRI_SUC' => $f['suc'], 'SRI_CTA' => $f['cta'], 'SRI_MON' => $f['monto'], 'SRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        if ($d['tipo'] === 'excel') {
            $contenido = $this->excel($filas, $fImput);
            $nombre = 'BANCO_RIO_' . $this->iz($codEmpresa, 4) . $this->iz((int) $modalidad, 4) . '.xls';
            return response()->json(['message' => 'Excel regenerado y nuevo lote registrado.', 'filename' => $nombre, 'contenido' => $contenido, 'mime' => 'application/vnd.ms-excel', 'lote' => $nuevoNro, 'total' => $total]);
        }
        $contenido = $this->txt($filas, $cuit, $orden, $fImput, (int) $origen->SRH_MESLIQ, (int) $origen->SRH_ANOLIQ, $total);
        $nombre = 'MOVI' . $this->iz($codEmpresa, 4) . $this->iz((int) $modalidad, 4) . '.TXT';
        return response()->json(['message' => 'TXT regenerado y nuevo lote registrado.', 'filename' => $nombre, 'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nuevoNro, 'total' => $total, 'lineas' => count($filas) + 2]);
    }

    /** Arma el TXT Piryp/Santander (registros de 650 caracteres). */
    private function txt(array $filas, string $cuit, int $orden, \Carbon\Carbon $fImput, int $mes, int $anio, float $total): string
    {
        $lineas = [];
        $lineas[] = 'H' . $cuit . '001101' . '007' . $this->iz($orden, 5) . '00000' . str_repeat(' ', 7) . 'S' . str_repeat(' ', 611);
        $nroComprob = $this->iz(sprintf('%04d%02d', $anio, $mes), 15);
        $fecha = $fImput->format('Ymd');
        foreach ($filas as $f) {
            $importe = $this->iz((int) round(((float) $f['monto']) * 100), 15);
            $cbu = $this->iz(preg_replace('/\D/', '', (string) ($f['cbu'] ?? '')), 26);
            $cuil = $this->iz(preg_replace('/\D/', '', (string) ($f['cuil'] ?? '')), 11);
            $lineas[] = 'D' . ' ' . '0' . $this->de((string) $f['cod'], 15) . 'RC' . $nroComprob . '0000'
               . $this->de(trim((string) $f['nombre']), 30) . $this->de(trim((string) ($f['dom'] ?? '')), 51)
               . '00000' . '    ' . str_repeat('0', 83) . str_repeat(' ', 11) . $cuil . str_repeat(' ', 162)
               . 'N' . '0054' . $cbu . str_repeat('0', 8) . $fecha . $importe . '50'
               . str_repeat(' ', 3) . str_repeat('0', 11) . str_repeat(' ', 3) . str_repeat('0', 11)
               . str_repeat(' ', 3) . str_repeat('0', 11) . str_repeat(' ', 3) . str_repeat('0', 25)
               . str_repeat(' ', 1) . str_repeat('0', 17) . str_repeat(' ', 102);
        }
        $lineas[] = 'T' . str_repeat('0', 15) . $this->iz((int) round($total * 100), 15) . $this->iz(count($filas), 7) . str_repeat(' ', 612);
        return implode("\r\n", $lineas) . "\r\n";
    }

    /** Arma el Excel (HTML .xls). */
    private function excel(array $filas, \Carbon\Carbon $fImput): string
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

    private function desdeUsu($usu): string
    {
        try { return \Carbon\Carbon::createFromFormat('d/m/Y', substr(trim((string) $usu), 0, 10))->format('Y-m-d'); }
        catch (\Throwable $e) { return now()->format('Y-m-d'); }
    }
    private function soloFecha($v): string { return $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '1900-01-01'; }
    private function fecha($v): string { if (!$v) return ''; $c = \Carbon\Carbon::parse($v); return $c->year <= 1900 ? '' : $c->format('d/m/Y'); }
    private function iz($valor, int $n): string { return substr(str_pad((string) $valor, $n, '0', STR_PAD_LEFT), -$n); }
    private function de(string $valor, int $n): string { return \App\Support\Latin1::campo($valor, $n); }
}
