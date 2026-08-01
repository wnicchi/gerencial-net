<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoVariosConsultarController — Bcos. Varios (Santander) - Consultar (empleados_consultar_bco_varios.scx).
 *
 * Gemelo de Bco. Santander Río - Consultar pero sobre las tablas varhaberes/varhab_item. El débito sigue
 * siendo de la cuenta Santander, por lo que el banco de Gestión es 1 (SANTANDER RIO).
 *  - lotes() / beneficiarios().
 *  - generarMovimiento(): registra el lote en gestion PAGOS_AUTORIZAR (OPA_BAN=1, "SANTANDER RIO") y marca VR_PAG.
 *  - eliminar(): VR_ANU='S' (+ OPA_AUTANU='S' en Gestión si estaba pagado).
 *  - generarNuevo(): regenera el TXT (formato Piryp/Santander para bancos varios: acuerdo 001102, forma de
 *    pago 57) o Excel con los beneficiarios editados, releyendo el padrón, creando un NUEVO lote.
 *    Reutiliza BancoVariosController::txt / ::excel.
 */
class BancoVariosConsultarController extends Controller
{
    private const BANCO_GESTION = 1; // Santander Río (la cuenta de débito)

    /** @route GET /api/bco-varios/lotes?empresa= */
    public function lotes(Request $request): JsonResponse
    {
        $q = DB::table('varhaberes');
        if ($emp = (int) $request->input('empresa', 0)) {
            $bsf = (int) (DB::table('empresas')->where('EMP_COD', $emp)->value('EMP_BSFEMP') ?? 0);
            if ($bsf) $q->where('VR_EMP', $bsf);
        }
        $rows = $q->orderByDesc('VR_NRO')
            ->get(['VR_NRO', 'VR_EMD', 'VR_SUE', 'VR_COD', 'VR_FEC', 'VR_MON', 'VR_ANU', 'VR_PAG'])
            ->map(fn ($r) => [
                'elegir' => false, 'nro' => (int) $r->VR_NRO, 'empresa' => trim((string) $r->VR_EMD),
                'concepto' => trim((string) $r->VR_SUE), 'tipoArc' => trim((string) $r->VR_COD),
                'fecha' => $this->fecha($r->VR_FEC), 'monto' => round((float) $r->VR_MON, 2),
                'anulado' => strtoupper(trim((string) $r->VR_ANU)) === 'S', 'pagado' => (int) $r->VR_PAG === 1,
            ]);
        return response()->json(['lotes' => $rows->values()]);
    }

    /** @route GET /api/bco-varios/beneficiarios?lote= */
    public function beneficiarios(Request $request): JsonResponse
    {
        $lote = (int) $request->validate(['lote' => 'required|integer'])['lote'];
        $rows = DB::table('varhab_item as i')->leftJoin('personal as p', 'i.VRI_EMP', '=', 'p.PER_COD')
            ->where('i.VRI_NRO', $lote)->orderBy('i.VRI_EMD')
            ->get(['i.VRI_EMP', 'i.VRI_EMD', 'i.VRI_SUC', 'i.VRI_CTA', 'i.VRI_MON', 'p.PER_LEG'])
            ->map(fn ($r) => [
                'elegir' => false, 'emp' => (int) $r->VRI_EMP, 'emd' => trim((string) $r->VRI_EMD),
                'suc' => (int) $r->VRI_SUC, 'cta' => (int) $r->VRI_CTA, 'mon' => round((float) $r->VRI_MON, 2),
                'montoInicial' => round((float) $r->VRI_MON, 2), 'leg' => (int) ($r->PER_LEG ?? 0),
            ]);
        return response()->json(['beneficiarios' => $rows->values()]);
    }

    /** @route POST /api/bco-varios/generar-movimiento */
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
            $vr = DB::table('varhaberes')->where('VR_NRO', $nro)->first();
            if (!$vr) { $resultados[] = ['lote' => $nro, 'estado' => 'inexistente']; continue; }
            try {
                $gx = DB::connection('gestion');
                $previo = $gx->table('PAGOS_AUTORIZAR')->where('OPA_EMP', $opaEmp)->where('OPA_REG', $nro)->where('OPA_BAN', self::BANCO_GESTION)->exists();
                if ($previo) {
                    DB::table('varhaberes')->where('VR_NRO', $nro)->update(['VR_PAG' => 1]);
                    $resultados[] = ['lote' => $nro, 'estado' => 'ya_enviado'];
                    continue;
                }
                $proximo = (int) ($gx->table('PAGOS_AUTORIZAR')->max('OPA_AUT')) + 1;
                $gx->table('PAGOS_AUTORIZAR')->insert([
                    'OPA_AUT' => $proximo, 'OPA_EMP' => $opaEmp, 'OPA_FEC' => $this->desdeUsu($vr->VR_USU), 'OPA_BAN' => self::BANCO_GESTION,
                    'OPA_BAD' => 'SANTANDER RIO', 'OPA_DET' => $tipoDesc, 'OPA_IMP' => round((float) $vr->VR_MON, 2),
                    'OPA_REG' => $nro, 'OPA_ENV' => $this->soloFecha($vr->VR_FEC), 'OPA_USU' => 0, 'OPA_USN' => mb_strtoupper($usuario),
                    'OPA_ASN' => 'N', 'OPA_AUF' => '1900-01-01', 'OPA_AUH' => '', 'OPA_AUU' => 0, 'OPA_AUN' => '',
                    'OPA_PAG' => 0, 'OPA_CTA' => (int) $d['tipoSueldo'], 'OPA_CTD' => $tipoDesc, 'OPA_PER' => 0, 'OPA_NOM' => '',
                    'OPA_EMBSN' => '', 'OPA_EMBNOM' => '', 'OPA_EMBCBU' => '', 'OPA_EMBMES' => 0, 'OPA_EMBANO' => 0,
                    'OPA_LIQ' => 0, 'OPA_AUTBCO' => (int) $d['nroBancario'], 'OPA_AUTANU' => '',
                ]);
                DB::table('varhaberes')->where('VR_NRO', $nro)->update(['VR_PAG' => 1]);
                $resultados[] = ['lote' => $nro, 'estado' => 'ok', 'autorizacion' => $proximo];
            } catch (\Throwable $e) {
                \Log::error('[BcoVarios generarMovimiento] lote ' . $nro . ': ' . $e->getMessage());
                $resultados[] = ['lote' => $nro, 'estado' => 'error', 'detalle' => $e->getMessage()];
            }
        }
        $ok = count(array_filter($resultados, fn ($r) => $r['estado'] === 'ok'));
        return response()->json(['message' => "Procesado. Pagos generados: $ok.", 'resultados' => $resultados]);
    }

    /** @route POST /api/bco-varios/eliminar */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['lotes' => 'required|array|min:1', 'lotes.*' => 'integer', 'nroBancario' => 'nullable|integer']);
        $nroBancario = (int) ($d['nroBancario'] ?? 0);
        $anulados = 0;
        foreach ($d['lotes'] as $nro) {
            $nro = (int) $nro;
            $vr = DB::table('varhaberes')->where('VR_NRO', $nro)->first();
            if (!$vr) continue;
            DB::table('varhaberes')->where('VR_NRO', $nro)->update(['VR_ANU' => 'S']);
            $anulados++;
            if ((int) $vr->VR_PAG === 1 && $nroBancario > 0) {
                try {
                    DB::connection('gestion')->table('PAGOS_AUTORIZAR')
                        ->where('OPA_BAN', self::BANCO_GESTION)->where('OPA_AUTBCO', $nroBancario)->update(['OPA_AUTANU' => 'S']);
                } catch (\Throwable $e) { \Log::error('[BcoVarios eliminar] ' . $e->getMessage()); }
            }
        }
        return response()->json(['message' => "Lote(s) anulado(s): $anulados.", 'anulados' => $anulados]);
    }

    /** @route POST /api/bco-varios/generar-nuevo  (tipo = txt | excel) */
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

        $origen = DB::table('varhaberes')->where('VR_NRO', (int) $d['loteOrigen'])->first();
        if (!$origen) return response()->json(['message' => 'Lote de origen inexistente.'], 422);
        $emp = DB::table('empresas')->where('EMP_BSFEMP', (int) $origen->VR_EMP)->first();
        $cuit = substr(preg_replace('/\D/', '', (string) ($emp->EMP_CUI ?? '')) . str_repeat(' ', 11), 0, 11);
        $codEmpresa = (int) $origen->VR_EMP; $codConvenio = (int) $origen->VR_CON;
        $modalidad = trim((string) $origen->VR_COD);
        $orden = (int) ($d['orden'] ?? 1);
        $usuario = strtoupper(substr((string) (optional($request->user())->name ?? 'RRHH.NET'), 0, 24));
        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);

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
            $nuevoNro = (int) max((int) DB::table('varhaberes')->max('VR_NRO'), (int) DB::table('varhab_item')->max('VRI_NRO')) + 1;
            DB::table('varhaberes')->insert(Registro::completar('varhaberes', [
                'VR_NRO' => $nuevoNro, 'VR_EMP' => $codEmpresa, 'VR_EMD' => trim((string) $origen->VR_EMD),
                'VR_CON' => 1, 'VR_COD' => $modalidad, 'VR_FEC' => $fImput->format('Y-m-d'), 'VR_MON' => $total,
                'VR_ORD' => ($d['enviaAlBanco'] ?? false) ? 1 : 0, 'VR_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario,
                'VR_TER' => 'RRHH.NET', 'VR_SUE' => trim((string) $origen->VR_SUE),
                'VR_MESLIQ' => (int) $origen->VR_MESLIQ, 'VR_ANOLIQ' => (int) $origen->VR_ANOLIQ,
            ]));
            foreach ($filas as $f) {
                DB::table('varhab_item')->insert(Registro::completar('varhab_item', [
                    'VRI_NRO' => $nuevoNro, 'VRI_EMP' => $f['cod'], 'VRI_EMD' => $f['nombre'], 'VRI_CON' => $codConvenio,
                    'VRI_COD' => $modalidad, 'VRI_SUC' => $f['suc'], 'VRI_CTA' => $f['cta'], 'VRI_MON' => $f['monto'], 'VRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        if ($d['tipo'] === 'excel') {
            $contenido = BancoVariosController::excel($filas, $fImput);
            $nombre = 'BANCO_RIO_' . BancoVariosController::iz($codEmpresa, 4) . BancoVariosController::iz((int) $modalidad, 4) . '.xls';
            return response()->json(['message' => 'Excel regenerado y nuevo lote registrado.', 'filename' => $nombre, 'contenido' => $contenido, 'mime' => 'application/vnd.ms-excel', 'lote' => $nuevoNro, 'total' => $total]);
        }
        $contenido = BancoVariosController::txt($filas, $cuit, $orden, $fImput, (int) $origen->VR_MESLIQ, (int) $origen->VR_ANOLIQ, $total);
        $nombre = 'MOVI' . BancoVariosController::iz($codEmpresa, 4) . BancoVariosController::iz((int) $modalidad, 4) . '.TXT';
        return response()->json(['message' => 'TXT regenerado y nuevo lote registrado.', 'filename' => $nombre, 'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nuevoNro, 'total' => $total, 'lineas' => count($filas) + 2]);
    }

    private function desdeUsu($usu): string
    {
        try { return \Carbon\Carbon::createFromFormat('d/m/Y', substr(trim((string) $usu), 0, 10))->format('Y-m-d'); }
        catch (\Throwable $e) { return now()->format('Y-m-d'); }
    }
    private function soloFecha($v): string { return $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '1900-01-01'; }
    private function fecha($v): string { if (!$v) return ''; $c = \Carbon\Carbon::parse($v); return $c->year <= 1900 ? '' : $c->format('d/m/Y'); }
}
