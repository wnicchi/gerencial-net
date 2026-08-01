<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoSantaFeConsultarController — Bco. Santa Fe - Consultar (empleados_consultar_bco_sf.scx).
 *
 * Trabaja sobre los lotes ya exportados (SFHABERES / SFHAB_ITEM) del Nuevo Banco de Santa Fe:
 *  - lotes(): lista los lotes de una empresa.
 *  - beneficiarios(): detalle de empleados de un lote.
 *  - generarMovimiento(): "Generar el movimiento de pagos" — registra el/los lote(s) en la tabla
 *    PAGOS_AUTORIZAR del sistema de Gestión (SILCAR) para que GERENCIA autorice el pago, y marca el
 *    lote como pagado (SFH_PAG). Controla duplicados; si el registro previo está ANULADO lo reemplaza.
 *  - eliminar(): anula el/los lote(s) (SFH_ANU='S') y, si ya estaban pagados, marca en Gestión
 *    PAGOS_AUTORIZAR.OPA_AUTANU='S'.
 *  - generarNuevoTxt(): regenera un TXT (con los beneficiarios editados) creando un NUEVO lote
 *    SFHABERES/SFHAB_ITEM, con el mismo formato de ancho fijo del banco.
 *
 * Bancos en Gestión: 1=Río, 2=Nuevo Bco. Santa Fe, 3=BBVA. Empresa: 'S'=Silcar, 'L'=Logística.
 */
class BancoSantaFeConsultarController extends Controller
{
    private const BANCO_GESTION = 2; // Nuevo Banco de Santa Fe

    /** @route GET /api/bco-santa-fe/lotes?empresa= */
    public function lotes(Request $request): JsonResponse
    {
        $q = DB::table('SFHABERES');
        if ($emp = (int) $request->input('empresa', 0)) {
            $bsf = (int) (DB::table('empresas')->where('EMP_COD', $emp)->value('EMP_BSFEMP') ?? 0);
            if ($bsf) $q->where('SFH_EMP', $bsf);
        }
        $rows = $q->orderByDesc('SFH_NRO')
            ->get(['SFH_NRO', 'SFH_EMD', 'SFH_SUE', 'SFH_COD', 'SFH_FEC', 'SFH_MON', 'SFH_ANU', 'SFH_PAG'])
            ->map(fn ($r) => [
                'elegir'   => false,
                'nro'      => (int) $r->SFH_NRO,
                'empresa'  => trim((string) $r->SFH_EMD),
                'concepto' => trim((string) $r->SFH_SUE),
                'tipoArc'  => trim((string) $r->SFH_COD),
                'fecha'    => $this->fecha($r->SFH_FEC),
                'monto'    => round((float) $r->SFH_MON, 2),
                'anulado'  => strtoupper(trim((string) $r->SFH_ANU)) === 'S',
                'pagado'   => (int) $r->SFH_PAG === 1,
            ]);
        return response()->json(['lotes' => $rows->values()]);
    }

    /** @route GET /api/bco-santa-fe/beneficiarios?lote= */
    public function beneficiarios(Request $request): JsonResponse
    {
        $lote = (int) $request->validate(['lote' => 'required|integer'])['lote'];
        $rows = DB::table('SFHAB_ITEM as i')->leftJoin('personal as p', 'i.SFI_EMP', '=', 'p.PER_COD')
            ->where('i.SFI_NRO', $lote)->orderBy('i.SFI_EMD')
            ->get(['i.SFI_EMP', 'i.SFI_EMD', 'i.SFI_SUC', 'i.SFI_CTA', 'i.SFI_MON', 'p.PER_LEG'])
            ->map(fn ($r) => [
                'elegir'   => false,
                'emp'      => (int) $r->SFI_EMP,
                'emd'      => trim((string) $r->SFI_EMD),
                'suc'      => (int) $r->SFI_SUC,
                'cta'      => (int) $r->SFI_CTA,
                'mon'      => round((float) $r->SFI_MON, 2),
                'montoInicial' => round((float) $r->SFI_MON, 2),
                'leg'      => (int) ($r->PER_LEG ?? 0),
            ]);
        return response()->json(['beneficiarios' => $rows->values()]);
    }

    /** @route POST /api/bco-santa-fe/generar-movimiento */
    public function generarMovimiento(Request $request): JsonResponse
    {
        $d = $request->validate([
            'lotes'         => 'required|array|min:1',
            'lotes.*'       => 'integer',
            'tipoSueldo'    => 'required|integer|min:1',
            'nroBancario'   => 'required|integer|min:1',
            'empresa'       => 'required|integer|min:1',
        ]);

        $tipoDesc = trim((string) (DB::table('sueldo_tip')->where('STI_COD', (int) $d['tipoSueldo'])->value('STI_DES') ?? ''));
        // 'S' = Silcar (AUTOELEVADORES y relacionadas), 'L' = Logística.
        $opaEmp = mb_strtoupper(substr(trim((string) (DB::table('empresas')->where('EMP_COD', (int) $d['empresa'])->value('EMP_NOM') ?? '')), 0, 30));
        $opaEmp = str_contains($opaEmp, 'LOG') ? 'L' : 'S';
        $usuario = trim((string) (optional($request->user())->name ?? 'RRHH.NET'));

        $resultados = [];
        foreach ($d['lotes'] as $nro) {
            $nro = (int) $nro;
            $sfh = DB::table('SFHABERES')->where('SFH_NRO', $nro)->first();
            if (!$sfh) { $resultados[] = ['lote' => $nro, 'estado' => 'inexistente']; continue; }

            try {
                $gx = DB::connection('gestion');
                $previo = $gx->table('PAGOS_AUTORIZAR')
                    ->where('OPA_EMP', $opaEmp)->where('OPA_REG', $nro)->where('OPA_BAN', self::BANCO_GESTION)->first();

                if ($previo) {
                    if (mb_strtoupper(trim((string) $previo->OPA_BAD)) === 'ANULADO') {
                        // reemplazar: borrar el anulado y reinsertar
                        $gx->table('PAGOS_AUTORIZAR')->where('OPA_REG', $nro)->where('OPA_BAN', self::BANCO_GESTION)->delete();
                    } else {
                        // ya enviado: marcar pagado y avisar
                        DB::table('SFHABERES')->where('SFH_NRO', $nro)->update(['SFH_PAG' => 1]);
                        $resultados[] = ['lote' => $nro, 'estado' => 'ya_enviado'];
                        continue;
                    }
                }

                $proximo = (int) ($gx->table('PAGOS_AUTORIZAR')->max('OPA_AUT')) + 1;
                $gx->table('PAGOS_AUTORIZAR')->insert([
                    'OPA_AUT' => $proximo, 'OPA_EMP' => $opaEmp,
                    'OPA_FEC' => $this->desdeUsu($sfh->SFH_USU), 'OPA_BAN' => self::BANCO_GESTION,
                    'OPA_BAD' => 'NUEVO BCO.SANTA FE', 'OPA_DET' => $tipoDesc, 'OPA_IMP' => round((float) $sfh->SFH_MON, 2),
                    'OPA_REG' => $nro, 'OPA_ENV' => $this->soloFecha($sfh->SFH_FEC), 'OPA_USU' => 0, 'OPA_USN' => mb_strtoupper($usuario),
                    'OPA_ASN' => 'N', 'OPA_AUF' => '1900-01-01', 'OPA_AUH' => '', 'OPA_AUU' => 0, 'OPA_AUN' => '',
                    'OPA_PAG' => 0, 'OPA_CTA' => (int) $d['tipoSueldo'], 'OPA_CTD' => $tipoDesc, 'OPA_PER' => 0, 'OPA_NOM' => '',
                    'OPA_EMBSN' => '', 'OPA_EMBNOM' => '', 'OPA_EMBCBU' => '', 'OPA_EMBMES' => 0, 'OPA_EMBANO' => 0,
                    'OPA_LIQ' => 0, 'OPA_AUTBCO' => (int) $d['nroBancario'], 'OPA_AUTANU' => '',
                ]);

                DB::table('SFHABERES')->where('SFH_NRO', $nro)->update(['SFH_PAG' => 1]);
                $resultados[] = ['lote' => $nro, 'estado' => 'ok', 'autorizacion' => $proximo];
            } catch (\Throwable $e) {
                \Log::error('[BcoSF generarMovimiento] lote ' . $nro . ': ' . $e->getMessage());
                $resultados[] = ['lote' => $nro, 'estado' => 'error', 'detalle' => $e->getMessage()];
            }
        }

        $ok = count(array_filter($resultados, fn ($r) => $r['estado'] === 'ok'));
        return response()->json(['message' => "Procesado. Pagos generados: $ok.", 'resultados' => $resultados]);
    }

    /** @route POST /api/bco-santa-fe/eliminar */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'lotes' => 'required|array|min:1', 'lotes.*' => 'integer',
            'nroBancario' => 'nullable|integer',
        ]);
        $nroBancario = (int) ($d['nroBancario'] ?? 0);

        $anulados = 0;
        foreach ($d['lotes'] as $nro) {
            $nro = (int) $nro;
            $sfh = DB::table('SFHABERES')->where('SFH_NRO', $nro)->first();
            if (!$sfh) continue;
            DB::table('SFHABERES')->where('SFH_NRO', $nro)->update(['SFH_ANU' => 'S']);
            $anulados++;

            if ((int) $sfh->SFH_PAG === 1 && $nroBancario > 0) {
                try {
                    DB::connection('gestion')->table('PAGOS_AUTORIZAR')
                        ->where('OPA_BAN', self::BANCO_GESTION)->where('OPA_AUTBCO', $nroBancario)
                        ->update(['OPA_AUTANU' => 'S']);
                } catch (\Throwable $e) {
                    \Log::error('[BcoSF eliminar] gestion lote ' . $nro . ': ' . $e->getMessage());
                }
            }
        }
        return response()->json(['message' => "Lote(s) anulado(s): $anulados.", 'anulados' => $anulados]);
    }

    /** @route POST /api/bco-santa-fe/generar-nuevo-txt */
    public function generarNuevoTxt(Request $request): JsonResponse
    {
        $d = $request->validate([
            'loteOrigen'      => 'required|integer',
            'fechaEnvio'      => 'required|date',
            'fechaImputacion' => 'required|date',
            'beneficiarios'           => 'required|array|min:1',
            'beneficiarios.*.emp'     => 'required|integer',
            'beneficiarios.*.emd'     => 'required|string',
            'beneficiarios.*.suc'     => 'required|integer',
            'beneficiarios.*.cta'     => 'required|integer',
            'beneficiarios.*.mon'     => 'required|numeric',
        ]);

        $origen = DB::table('SFHABERES')->where('SFH_NRO', (int) $d['loteOrigen'])->first();
        if (!$origen) return response()->json(['message' => 'Lote de origen inexistente.'], 422);

        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);
        $codEmpresa  = (int) $origen->SFH_EMP;
        $codConvenio = (int) $origen->SFH_CON;
        $modalidad   = trim((string) $origen->SFH_COD);
        $usuario = trim((string) (optional($request->user())->name ?? 'RRHH.NET'));
        $total = round(array_sum(array_map(fn ($f) => (float) $f['mon'], $d['beneficiarios'])), 2);

        // Cabecera (119)
        $lineas = [];
        $cab  = '9998' . $this->iz($codEmpresa, 4) . $this->iz($codConvenio, 4) . $fEnvio->format('Ymd') . '000001'
              . $this->iz((int) round($total * 1000), 14) . str_repeat(' ', 79);
        $lineas[] = $cab;

        $nuevoNro = 0;
        DB::transaction(function () use ($d, $origen, $codEmpresa, $codConvenio, $modalidad, $fEnvio, $fImput, $usuario, $total, &$lineas, &$nuevoNro) {
            $nuevoNro = (int) max((int) DB::table('SFHABERES')->max('SFH_NRO'), (int) DB::table('SFHAB_ITEM')->max('SFI_NRO')) + 1;

            DB::table('SFHABERES')->insert(Registro::completar('SFHABERES', [
                'SFH_NRO' => $nuevoNro, 'SFH_EMP' => $codEmpresa, 'SFH_EMD' => trim((string) $origen->SFH_EMD),
                'SFH_CON' => $codConvenio, 'SFH_COD' => $modalidad, 'SFH_FEC' => $fImput->format('Y-m-d'),
                'SFH_MON' => $total, 'SFH_USU' => $fEnvio->format('d/m/Y') . ' ' . mb_strtoupper($usuario),
                'SFH_TER' => 'RRHH.NET', 'SFH_SUE' => trim((string) $origen->SFH_SUE),
                'SFH_MESLIQ' => (int) $origen->SFH_MESLIQ, 'SFH_ANOLIQ' => (int) $origen->SFH_ANOLIQ,
            ]));

            foreach ($d['beneficiarios'] as $b) {
                $suc = (int) $b['suc']; $cta = (int) $b['cta']; $mon = round((float) $b['mon'], 2);
                $det = $this->iz($codEmpresa, 4) . '01' . $this->iz($suc, 4) . $this->iz($cta, 12) . '02'
                     . $this->iz((int) round($mon * 1000), 14) . $fImput->format('Ymd') . '00' . '0000000000'
                     . $this->iz($codConvenio, 4) . '9999' . str_repeat(' ', 30) . str_repeat(' ', 10) . '00000000' . '00000';
                $lineas[] = $det;

                DB::table('SFHAB_ITEM')->insert(Registro::completar('SFHAB_ITEM', [
                    'SFI_NRO' => $nuevoNro, 'SFI_EMP' => (int) $b['emp'], 'SFI_EMD' => trim((string) $b['emd']),
                    'SFI_CON' => $codConvenio, 'SFI_COD' => $modalidad, 'SFI_SUC' => $suc, 'SFI_CTA' => $cta,
                    'SFI_MON' => $mon, 'SFI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        $contenido = implode("\r\n", $lineas) . "\r\n ";
        $nombre = 'MOVI' . $this->iz($codEmpresa, 4) . $this->iz((int) $modalidad, 4) . '.TXT';

        return response()->json([
            'message' => 'TXT regenerado y nuevo lote registrado.', 'filename' => $nombre,
            'contenido' => $contenido, 'lote' => $nuevoNro, 'total' => $total, 'lineas' => count($lineas),
        ]);
    }

    /** Fecha de débito desde el prefijo "dd/mm/yyyy ..." de SFH_USU. */
    private function desdeUsu($usu): string
    {
        try { return \Carbon\Carbon::createFromFormat('d/m/Y', substr(trim((string) $usu), 0, 10))->format('Y-m-d'); }
        catch (\Throwable $e) { return now()->format('Y-m-d'); }
    }

    private function soloFecha($v): string
    {
        return $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '1900-01-01';
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y');
    }

    private function iz($valor, int $n): string
    {
        return substr(str_pad((string) $valor, $n, '0', STR_PAD_LEFT), -$n);
    }
}
