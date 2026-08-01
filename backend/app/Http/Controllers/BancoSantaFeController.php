<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BancoSantaFeController — Bco. Santa Fe - Exportar (empleados_exportar_bco_sf.scx).
 *
 *  - consultar(): arma la lista de empleados a depositar con el MONTO calculado del período
 *    (suma de los netos de sus liquidaciones de los tipos seleccionados), descontando lo ya
 *    exportado antes (control de duplicados contra SFHABERES/SFHAB_ITEM). Opcionalmente suma
 *    el anticipo del legajo (PER_ANTI).
 *
 *  - generar(): genera el archivo TXT de ancho fijo para el Nuevo Banco de Santa Fe (una línea
 *    cabecera + una por empleado) y REGISTRA el lote en SFHABERES (cabecera) + SFHAB_ITEM (detalle),
 *    que es lo que luego permite el control de duplicados.
 *
 * Tablas en conexión RRHH. SFH_NRO/SFI_NRO no son identity: SFI_NRO referencia al SFH_NRO de la
 * cabecera; el número nuevo es max(ambos)+1.
 */
class BancoSantaFeController extends Controller
{
    /** @route GET /api/bco-santa-fe/consultar */
    public function consultar(Request $request): JsonResponse
    {
        $d = $request->validate(['empresa' => 'required|integer|min:1', 'mes' => 'required|integer|min:1|max:12', 'anio' => 'required|integer']);
        $empresa = (int) $d['empresa']; $mes = (int) $d['mes']; $anio = (int) $d['anio'];
        $banco = (int) ($request->input('banco') ?: 2); // 2 = Nuevo Banco de Santa Fe
        $anticipos = (int) $request->input('anticipos', 0) === 1;

        $label = $this->etiquetaSueldo($request);

        // Empleados activos del filtro
        $q = DB::table('personal')->where('PER_AOP', 'A')->where('PER_EMP', $empresa)->where('PER_BAN', $banco);
        foreach (['contratista' => 'PER_CONTRA', 'lugar' => 'PER_LUGAR', 'convenio' => 'PER_CON', 'categoria' => 'PER_CAT', 'empleado' => 'PER_COD'] as $p => $col) {
            if ($v = (int) $request->input($p, 0)) $q->where($col, $v);
        }
        $empleados = $q->orderBy('PER_NOM')
            ->get(['PER_COD', 'PER_NOM', 'PER_TDO', 'PER_NDO', 'PER_CUI', 'PER_BSFSUC', 'PER_BSFNRO', 'PER_ANTI']);
        if ($empleados->isEmpty()) return response()->json(['empleados' => [], 'total' => 0]);

        $codigos = $empleados->pluck('PER_COD')->map(fn ($c) => (int) $c)->all();

        // Tipos de remuneración a sumar
        $tipos = null; // null = todos
        if ((int) $request->input('remuModo', 1) === 2 && ($tr = (int) $request->input('tipoRemu', 0))) $tipos = [$tr];

        // Neto por (empleado, tipo, liquidación) del período
        $liq = DB::table('LIQUIDAC as l')->join('LIQ_ITE as li', 'l.LIQ_NRO', '=', 'li.LIT_NRO')
            ->where('l.LIQ_MES', $mes)->where('l.LIQ_ANO', $anio)->whereIn('l.LIQ_COD', $codigos);
        if ($tipos) $liq->whereIn('l.LIQ_TIP', $tipos);
        $netos = $liq->groupBy('l.LIQ_COD', 'l.LIQ_TIP', 'l.LIQ_NRO')
            ->get(['l.LIQ_COD', 'l.LIQ_TIP', 'l.LIQ_NRO', DB::raw('SUM(li.LIT_HAB - li.LIT_DED) as neto')]);

        // Claves ya exportadas (control de duplicados): emp|round(monto)
        $yaExportado = [];
        foreach (DB::table('SFHABERES as a')->join('SFHAB_ITEM as b', 'a.SFH_NRO', '=', 'b.SFI_NRO')
            ->where('a.SFH_ANU', '<>', 'S')->where('a.SFH_MESLIQ', $mes)->where('a.SFH_ANOLIQ', $anio)
            ->whereRaw('YEAR(b.SFI_FIM) = ?', [$anio])->whereRaw('UPPER(LTRIM(RTRIM(a.SFH_SUE))) = ?', [$label])
            ->whereIn('b.SFI_EMP', $codigos)->get(['b.SFI_EMP', 'b.SFI_MON']) as $r) {
            $yaExportado[(int) $r->SFI_EMP . '|' . round((float) $r->SFI_MON, 0)] = true;
        }

        // Acumular monto por empleado
        $montoEmp = array_fill_keys($codigos, 0.0);
        foreach ($netos as $r) {
            $cod = (int) $r->LIQ_COD; $neto = round((float) $r->neto, 2);
            if (isset($yaExportado[$cod . '|' . round($neto, 0)])) continue; // ya depositado
            $montoEmp[$cod] += $neto;
        }

        $filas = [];
        $total = 0.0;
        foreach ($empleados as $e) {
            $cod = (int) $e->PER_COD;
            $monto = round($montoEmp[$cod] ?? 0, 2);
            if ($anticipos) $monto = round($monto + (float) $e->PER_ANTI, 2);
            $suc = (int) $e->PER_BSFSUC; $cta = (int) $e->PER_BSFNRO;
            $total += $monto;
            $filas[] = [
                'elegir'   => false,
                'cod'      => $cod,
                'nombre'   => trim((string) $e->PER_NOM),
                'tdoc'     => trim((string) $e->PER_TDO),
                'ndoc'     => trim((string) $e->PER_NDO),
                'cuil'     => trim((string) $e->PER_CUI),
                'suc'      => $suc,
                'cta'      => $cta,
                'monto'    => $monto,
                'anti'     => round((float) $e->PER_ANTI, 2),
                'sinCuenta' => $suc === 0 || $cta === 0,
            ];
        }

        return response()->json(['empleados' => $filas, 'total' => round($total, 2), 'etiqueta' => $label]);
    }

    /** @route POST /api/bco-santa-fe/generar */
    public function generar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa'         => 'required|integer|min:1',
            'mes'             => 'required|integer|min:1|max:12',
            'anio'            => 'required|integer',
            'fechaEnvio'      => 'required|date',
            'fechaImputacion' => 'required|date',
            'modalidad'       => 'required|string',
            'filas'             => 'required|array|min:1',
            'filas.*.cod'       => 'required|integer',
            'filas.*.nombre'    => 'required|string',
            'filas.*.suc'       => 'required|integer',
            'filas.*.cta'       => 'required|integer',
            'filas.*.monto'     => 'required|numeric',
        ]);

        $emp = DB::table('empresas')->where('EMP_COD', (int) $d['empresa'])->first();
        if (!$emp) return response()->json(['message' => 'Empresa inexistente.'], 422);
        $codEmpresa  = (int) $emp->EMP_BSFEMP;
        $codConvenio = (int) $emp->EMP_BSFCON;
        $modalidad   = trim((string) $d['modalidad']);

        $fEnvio = \Carbon\Carbon::parse($d['fechaEnvio']);
        $fImput = \Carbon\Carbon::parse($d['fechaImputacion']);
        $label  = $this->etiquetaSueldo($request);

        $mes = (int) $d['mes']; $anio = (int) $d['anio'];
        $usuario = strtoupper(substr((string) (optional($request->user())->name ?? 'RRHH.NET'), 0, 24));
        $total = round(array_sum(array_map(fn ($f) => (float) $f['monto'], $d['filas'])), 2);

        // ── Línea cabecera (119 caracteres) ──
        $lineas = [];
        $cab  = '9998';
        $cab .= $this->iz($codEmpresa, 4);
        $cab .= $this->iz($codConvenio, 4);
        $cab .= $fEnvio->format('Ymd');
        $cab .= '000001';
        $cab .= $this->iz((int) round($total * 1000), 14);
        $cab .= str_repeat(' ', 79);
        $lineas[] = $cab;

        $numeroSf = 0;
        DB::transaction(function () use ($d, $emp, $codEmpresa, $codConvenio, $modalidad, $fEnvio, $fImput, $label, $total, $mes, $anio, $usuario, &$lineas, &$numeroSf) {
            $numeroSf = (int) max((int) DB::table('SFHABERES')->max('SFH_NRO'), (int) DB::table('SFHAB_ITEM')->max('SFI_NRO')) + 1;

            DB::table('SFHABERES')->insert(Registro::completar('SFHABERES', [
                'SFH_NRO' => $numeroSf, 'SFH_EMP' => $codEmpresa, 'SFH_EMD' => trim((string) $emp->EMP_NOM),
                'SFH_CON' => $codConvenio, 'SFH_COD' => $modalidad, 'SFH_FEC' => $fImput->format('Y-m-d'),
                'SFH_MON' => $total, 'SFH_USU' => $fEnvio->format('d/m/Y') . ' ' . strtoupper($usuario),
                'SFH_TER' => 'RRHH.NET', 'SFH_SUE' => $label, 'SFH_MESLIQ' => $mes, 'SFH_ANOLIQ' => $anio,
            ]));

            foreach ($d['filas'] as $f) {
                $cod = (int) $f['cod']; $suc = (int) $f['suc']; $cta = (int) $f['cta']; $monto = round((float) $f['monto'], 2);

                $det  = $this->iz($codEmpresa, 4);
                $det .= '01';                              // sistema: 01 = caja de ahorros
                $det .= $this->iz($suc, 4);                // sucursal
                $det .= $this->iz($cta, 12);               // nro. de cuenta
                $det .= '02';                              // código de operación
                $det .= $this->iz((int) round($monto * 1000), 14); // importe (3 decimales)
                $det .= $fImput->format('Ymd');            // fecha imputación
                $det .= '00';                              // campo fijo
                $det .= '0000000000';                      // nro. comprobante
                $det .= $this->iz($codConvenio, 4);        // convenio
                $det .= '9999';                            // afinidad
                $det .= str_repeat(' ', 30);
                $det .= str_repeat(' ', 10);
                $det .= '00000000';                        // uso interno
                $det .= '00000';                           // respuesta
                $lineas[] = $det;

                DB::table('SFHAB_ITEM')->insert(Registro::completar('SFHAB_ITEM', [
                    'SFI_NRO' => $numeroSf, 'SFI_EMP' => $cod, 'SFI_EMD' => trim((string) $f['nombre']),
                    'SFI_CON' => $codConvenio, 'SFI_COD' => $modalidad, 'SFI_SUC' => $suc, 'SFI_CTA' => $cta,
                    'SFI_MON' => $monto, 'SFI_FIM' => $fImput->format('Y-m-d'),
                ]));
            }
        });

        $contenido = implode("\r\n", $lineas) . "\r\n ";
        $nombre = 'MOVI' . $this->iz($codEmpresa, 4) . $this->iz((int) $modalidad, 4) . '.TXT';

        return response()->json([
            'message'   => 'Archivo generado y lote registrado correctamente.',
            'filename'  => $nombre,
            'contenido' => $contenido,
            'lote'      => $numeroSf,
            'total'     => $total,
            'lineas'    => count($lineas),
        ]);
    }

    /** Etiqueta del tipo de sueldo del lote: "COMPLETO" o el nombre del tipo elegido. */
    private function etiquetaSueldo(Request $request): string
    {
        if ((int) $request->input('liquidacionModo', 1) === 2 && ($t = (int) $request->input('tipoSueldo', 0))) {
            $des = DB::table('sueldo_tip')->where('STI_COD', $t)->value('STI_DES');
            if ($des) return mb_strtoupper(trim((string) $des));
        }
        return 'COMPLETO';
    }

    /** Rellena con ceros a la izquierda hasta $n caracteres. */
    private function iz($valor, int $n): string
    {
        return substr(str_pad((string) $valor, $n, '0', STR_PAD_LEFT), -$n);
    }
}
