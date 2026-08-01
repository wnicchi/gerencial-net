<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoNacionConsultarController — Bco. Nación - Consultar (empleados_consultar_bco_nacion.scx).
 *
 * Gemelo de Bco. Francés - Consultar pero sobre las tablas bchaberes/bchab_item (Banco Nación) y banco
 * de Gestión 4 (BCO NACION):
 *  - lotes() / beneficiarios().
 *  - generarMovimiento(): registra el lote en gestion PAGOS_AUTORIZAR (OPA_BAN=4, "BCO NACION") y marca FRH_PAG.
 *  - eliminar(): FRH_ANU='S' (+ OPA_AUTANU='S' en Gestión si estaba pagado).
 *  - generarNuevo(): regenera el TXT BBVA (250 chars, mismo formato que Banco Nación/Francés) con los
 *    beneficiarios editados, releyendo el padrón, creando un NUEVO lote. El ordenante es el nombre del
 *    lote de origen y el concepto su FRH_COD. Reutiliza BancoFrancesController::txtBBVA.
 */
class BancoNacionConsultarController extends Controller
{
    private const BANCO_GESTION = 4; // BCO NACION (en el sistema de Gestión)

    /** @route GET /api/bco-nacion/lotes?empresa= */
    public function lotes(Request $request): JsonResponse
    {
        $q = DB::table('bchaberes');
        if ($emp = (int) $request->input('empresa', 0)) {
            $bsf = (int) (DB::table('empresas')->where('EMP_COD', $emp)->value('EMP_BSFEMP') ?? 0);
            if ($bsf) $q->where('FRH_EMP', $bsf);
        }
        $rows = $q->orderByDesc('FRH_NRO')
            ->get(['FRH_NRO', 'FRH_EMD', 'FRH_SUE', 'FRH_COD', 'FRH_FEC', 'FRH_MON', 'FRH_ANU', 'FRH_PAG'])
            ->map(fn ($r) => [
                'elegir' => false, 'nro' => (int) $r->FRH_NRO, 'empresa' => trim((string) $r->FRH_EMD),
                'concepto' => trim((string) $r->FRH_SUE), 'tipoArc' => trim((string) $r->FRH_COD),
                'fecha' => $this->fecha($r->FRH_FEC), 'monto' => round((float) $r->FRH_MON, 2),
                'anulado' => strtoupper(trim((string) $r->FRH_ANU)) === 'S', 'pagado' => (int) $r->FRH_PAG === 1,
            ]);
        return response()->json(['lotes' => $rows->values()]);
    }

    /** @route GET /api/bco-nacion/beneficiarios?lote= */
    public function beneficiarios(Request $request): JsonResponse
    {
        $lote = (int) $request->validate(['lote' => 'required|integer'])['lote'];
        $rows = DB::table('bchab_item as i')->leftJoin('personal as p', 'i.FRI_EMP', '=', 'p.PER_COD')
            ->where('i.FRI_NRO', $lote)->orderBy('i.FRI_EMD')
            ->get(['i.FRI_EMP', 'i.FRI_EMD', 'i.FRI_SUC', 'i.FRI_CTA', 'i.FRI_MON', 'p.PER_LEG'])
            ->map(fn ($r) => [
                'elegir' => false, 'emp' => (int) $r->FRI_EMP, 'emd' => trim((string) $r->FRI_EMD),
                'suc' => (int) $r->FRI_SUC, 'cta' => (int) $r->FRI_CTA, 'mon' => round((float) $r->FRI_MON, 2),
                'montoInicial' => round((float) $r->FRI_MON, 2), 'leg' => (int) ($r->PER_LEG ?? 0),
            ]);
        return response()->json(['beneficiarios' => $rows->values()]);
    }

    /** @route POST /api/bco-nacion/generar-movimiento */
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
            $frh = DB::table('bchaberes')->where('FRH_NRO', $nro)->first();
            if (!$frh) { $resultados[] = ['lote' => $nro, 'estado' => 'inexistente']; continue; }
            try {
                $gx = DB::connection('gestion');
                $previo = $gx->table('PAGOS_AUTORIZAR')->where('OPA_EMP', $opaEmp)->where('OPA_REG', $nro)->where('OPA_BAN', self::BANCO_GESTION)->exists();
                if ($previo) {
                    DB::table('bchaberes')->where('FRH_NRO', $nro)->update(['FRH_PAG' => 1]);
                    $resultados[] = ['lote' => $nro, 'estado' => 'ya_enviado'];
                    continue;
                }
                $proximo = (int) ($gx->table('PAGOS_AUTORIZAR')->max('OPA_AUT')) + 1;
                $gx->table('PAGOS_AUTORIZAR')->insert([
                    'OPA_AUT' => $proximo, 'OPA_EMP' => $opaEmp, 'OPA_FEC' => $this->desdeUsu($frh->FRH_USU), 'OPA_BAN' => self::BANCO_GESTION,
                    'OPA_BAD' => 'BCO NACION', 'OPA_DET' => $tipoDesc, 'OPA_IMP' => round((float) $frh->FRH_MON, 2),
                    'OPA_REG' => $nro, 'OPA_ENV' => $this->soloFecha($frh->FRH_FEC), 'OPA_USU' => 0, 'OPA_USN' => mb_strtoupper($usuario),
                    'OPA_ASN' => 'N', 'OPA_AUF' => '1900-01-01', 'OPA_AUH' => '', 'OPA_AUU' => 0, 'OPA_AUN' => '',
                    'OPA_PAG' => 0, 'OPA_CTA' => (int) $d['tipoSueldo'], 'OPA_CTD' => $tipoDesc, 'OPA_PER' => 0, 'OPA_NOM' => '',
                    'OPA_EMBSN' => '', 'OPA_EMBNOM' => '', 'OPA_EMBCBU' => '', 'OPA_EMBMES' => 0, 'OPA_EMBANO' => 0,
                    'OPA_LIQ' => 0, 'OPA_AUTBCO' => (int) $d['nroBancario'], 'OPA_AUTANU' => '',
                ]);
                DB::table('bchaberes')->where('FRH_NRO', $nro)->update(['FRH_PAG' => 1]);
                $resultados[] = ['lote' => $nro, 'estado' => 'ok', 'autorizacion' => $proximo];
            } catch (\Throwable $e) {
                \Log::error('[BcoNacion generarMovimiento] lote ' . $nro . ': ' . $e->getMessage());
                $resultados[] = ['lote' => $nro, 'estado' => 'error', 'detalle' => $e->getMessage()];
            }
        }
        $ok = count(array_filter($resultados, fn ($r) => $r['estado'] === 'ok'));
        return response()->json(['message' => "Procesado. Pagos generados: $ok.", 'resultados' => $resultados]);
    }

    /** @route POST /api/bco-nacion/eliminar */
    public function eliminar(Request $request): JsonResponse
    {
        $d = $request->validate(['lotes' => 'required|array|min:1', 'lotes.*' => 'integer', 'nroBancario' => 'nullable|integer']);
        $nroBancario = (int) ($d['nroBancario'] ?? 0);
        $anulados = 0;
        foreach ($d['lotes'] as $nro) {
            $nro = (int) $nro;
            $frh = DB::table('bchaberes')->where('FRH_NRO', $nro)->first();
            if (!$frh) continue;
            DB::table('bchaberes')->where('FRH_NRO', $nro)->update(['FRH_ANU' => 'S']);
            $anulados++;
            if ((int) $frh->FRH_PAG === 1 && $nroBancario > 0) {
                try {
                    DB::connection('gestion')->table('PAGOS_AUTORIZAR')
                        ->where('OPA_BAN', self::BANCO_GESTION)->where('OPA_AUTBCO', $nroBancario)->update(['OPA_AUTANU' => 'S']);
                } catch (\Throwable $e) { \Log::error('[BcoNacion eliminar] ' . $e->getMessage()); }
            }
        }
        return response()->json(['message' => "Lote(s) anulado(s): $anulados.", 'anulados' => $anulados]);
    }

    /** @route POST /api/bco-nacion/generar-nuevo */
    public function generarNuevo(Request $request): JsonResponse
    {
        $d = $request->validate([
            'loteOrigen'      => 'required|integer',
            'fechaEnvio'      => 'required|date',
            'fechaImputacion' => 'required|date',
            'beneficiarios'         => 'required|array|min:1',
            'beneficiarios.*.emp'   => 'required|integer',
            'beneficiarios.*.mon'   => 'required|numeric',
        ]);

        $origen = DB::table('bchaberes')->where('FRH_NRO', (int) $d['loteOrigen'])->first();
        if (!$origen) return response()->json(['message' => 'Lote de origen inexistente.'], 422);
        $codEmpresa = (int) $origen->FRH_EMP; $codConvenio = (int) $origen->FRH_CON;
        $concepto = trim((string) $origen->FRH_COD);
        $usuario = strtoupper(substr((string) (optional($request->user())->name ?? 'RRHH.NET'), 0, 24));
        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);

        $cods = array_map(fn ($b) => (int) $b['emp'], $d['beneficiarios']);
        $personal = DB::table('personal')->whereIn('PER_COD', $cods)
            ->get(['PER_COD', 'PER_NOM', 'PER_DOM', 'PER_LOC', 'PER_CPA', 'PER_CUI', 'PER_CBU', 'PER_BSFSUC', 'PER_BSFNRO'])->keyBy(fn ($p) => (int) $p->PER_COD);
        $filas = [];
        foreach ($d['beneficiarios'] as $b) {
            $p = $personal->get((int) $b['emp']);
            if (!$p) continue;
            $filas[] = [
                'cod' => (int) $p->PER_COD, 'nombre' => trim((string) $p->PER_NOM), 'dom' => trim((string) $p->PER_DOM),
                'loc' => trim((string) $p->PER_LOC), 'cpa' => trim((string) $p->PER_CPA), 'cuil' => trim((string) $p->PER_CUI),
                'cbu' => preg_replace('/\D/', '', (string) $p->PER_CBU), 'suc' => (int) $p->PER_BSFSUC, 'cta' => (int) $p->PER_BSFNRO,
                'monto' => round((float) $b['mon'], 2),
            ];
        }
        if (!$filas) return response()->json(['message' => 'No hay beneficiarios válidos.'], 422);
        $total = round(array_sum(array_column($filas, 'monto')), 2);

        $nuevoNro = 0;
        DB::transaction(function () use ($filas, $origen, $codEmpresa, $codConvenio, $concepto, $fEnvio, $fImput, $usuario, $total, &$nuevoNro) {
            $nuevoNro = (int) max((int) DB::table('bchaberes')->max('FRH_NRO'), (int) DB::table('bchab_item')->max('FRI_NRO')) + 1;
            DB::table('bchaberes')->insert(Registro::completar('bchaberes', [
                'FRH_NRO' => $nuevoNro, 'FRH_EMP' => $codEmpresa, 'FRH_EMD' => trim((string) $origen->FRH_EMD),
                'FRH_CON' => $codConvenio, 'FRH_COD' => $concepto, 'FRH_FEC' => $fImput->format('Y-m-d'), 'FRH_MON' => $total,
                'FRH_USU' => $fEnvio->format('d/m/Y') . ' ' . $usuario, 'FRH_TER' => 'RRHH.NET', 'FRH_SUE' => trim((string) $origen->FRH_SUE),
                'FRH_MESLIQ' => (int) $origen->FRH_MESLIQ, 'FRH_ANOLIQ' => (int) $origen->FRH_ANOLIQ,
            ]));
            foreach ($filas as $f) {
                DB::table('bchab_item')->insert(Registro::completar('bchab_item', [
                    'FRI_NRO' => $nuevoNro, 'FRI_EMP' => $f['cod'], 'FRI_EMD' => $f['nombre'], 'FRI_CON' => $codConvenio,
                    'FRI_COD' => $concepto, 'FRI_SUC' => $f['suc'], 'FRI_CTA' => $f['cta'], 'FRI_MON' => $f['monto'], 'FRI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        $nombreArchivo = 'H' . now()->format('Ymd');
        $contenido = BancoFrancesController::txtBBVA($filas, trim((string) $origen->FRH_EMD), $concepto, $fEnvio, $fImput, $nombreArchivo, $total);
        return response()->json([
            'message' => 'TXT regenerado y nuevo lote registrado.', 'filename' => $nombreArchivo . '.TXT',
            'contenido' => base64_encode($contenido), 'encoding' => 'base64', 'mime' => 'text/plain', 'lote' => $nuevoNro, 'total' => $total, 'lineas' => count($filas) * 4 + 2,
        ]);
    }

    private function desdeUsu($usu): string
    {
        try { return \Carbon\Carbon::createFromFormat('d/m/Y', substr(trim((string) $usu), 0, 10))->format('Y-m-d'); }
        catch (\Throwable $e) { return now()->format('Y-m-d'); }
    }
    private function soloFecha($v): string { return $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '1900-01-01'; }
    private function fecha($v): string { if (!$v) return ''; $c = \Carbon\Carbon::parse($v); return $c->year <= 1900 ? '' : $c->format('d/m/Y'); }
}
