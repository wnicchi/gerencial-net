<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ValeController — Agregar Vales de efectivo (adelantos).
 *
 * Registra un vale de dinero en efectivo para un empleado: lo numera, lo graba
 * en `valeefectivo` y registra el movimiento en la cuenta corriente del fondo
 * fijo (`ccfondo`), todo en una transacción. Devuelve los datos para imprimir
 * el comprobante (con el importe en letras).
 */
class ValeController extends Controller
{
    /** @route POST /api/vales */
    public function crear(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'codigo'  => 'required|integer',
            'fecha'   => 'required|date',
            'hora'    => 'nullable|integer',      // HHMM (ej. 2147)
            'detalle' => 'nullable|string|max:200',
            'importe' => 'required|numeric|min:0.01',
            'fondo'   => 'required|integer|in:1,2', // 1=SILCAR/Autoelevadores, 2=Logística
            'autorizo' => 'nullable|string|max:100',
        ]);

        $emp = DB::table('personal')->where('PER_COD', (int) $datos['codigo'])->first(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_EMP']);
        if (!$emp) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }
        if (!\App\Support\Empleado::activo((int) $datos['codigo'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }

        $usuario = mb_strtoupper((string) ($request->user()->NOMBRE ?? $request->user()->name ?? ''));
        $terminal = mb_strtoupper(mb_substr((string) ($request->user()->name ?? gethostname()), 0, 8));
        $hora = (int) ($datos['hora'] ?? 0);
        $fechaHora = Carbon::parse($datos['fecha'])->setTime(intdiv($hora, 100), $hora % 100, 0);
        $importe = (float) $datos['importe'];
        $nombre = trim((string) $emp->PER_NOM);

        try {
            $nro = DB::transaction(function () use ($datos, $emp, $usuario, $terminal, $fechaHora, $importe, $nombre) {
                $nro = ((int) DB::table('valeefectivo')->max('VLE_NRO')) + 1;

                DB::table('valeefectivo')->insert(Registro::completar('valeefectivo', [
                    'VLE_NRO' => $nro,
                    'VLE_FEC' => $fechaHora,
                    'VLE_EMC' => (int) $emp->PER_COD,
                    'VLE_EMD' => mb_substr($nombre, 0, 50),
                    'VLE_IMP' => $importe,
                    'VLE_RAZ' => trim((string) ($datos['detalle'] ?? '')),
                    'VLE_AUT' => trim((string) ($datos['autorizo'] ?? '')),
                    'VLE_USU' => $usuario,
                    'VLE_TER' => $terminal,
                    'VLE_FON' => (int) $datos['fondo'],
                ]));

                DB::table('ccfondo')->insert(Registro::completar('ccfondo', [
                    'CCF_FEC' => $fechaHora,
                    'CCF_DET' => 'Vale Nro. ' . $nro . ' entregado a (' . (int) $emp->PER_COD . ') ' . $nombre,
                    'CCF_DEB' => 0,
                    'CCF_HAB' => $importe,
                    'CCF_USU' => $usuario,
                    'CCF_TER' => $terminal,
                    'CCF_VAL' => $nro,
                    'CCF_EMP' => (int) $datos['fondo'],
                ]));

                return $nro;
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo grabar el vale: ' . $e->getMessage()], 500);
        }

        $empresa = DB::table('empresas')->where('EMP_COD', (int) $emp->PER_EMP)->first(['EMP_NOM', 'EMP_DOM', 'EMP_CUI']);

        return response()->json([
            'ok'        => true,
            'numero'    => $nro,
            'vale'      => [
                'numero'            => $nro,
                'empresa_nombre'    => trim((string) ($empresa->EMP_NOM ?? '')),
                'empresa_domicilio' => trim((string) ($empresa->EMP_DOM ?? '')),
                'empresa_cuit'      => trim((string) ($empresa->EMP_CUI ?? '')),
                'personal_nombre'   => mb_strtoupper($nombre),
                'personal_cuit'     => trim((string) $emp->PER_CUI),
                'importe'           => $importe,
                'en_letras'         => 'PESOS ' . $this->enLetras($importe),
                'fecha'             => Carbon::parse($datos['fecha'])->format('d/m/Y'),
                'observaciones'     => trim((string) ($datos['detalle'] ?? '')),
                'fondo_salida'      => ((int) $datos['fondo']) === 1 ? 'F.AUTOELEVADORES' : 'F.LOGISTICA',
            ],
        ]);
    }

    /** Fecha de corte: un vale está "abierto" (sin cerrar) si VLE_FCI < esta fecha. */
    private const SIN_CERRAR = '1950-01-01';

    /**
     * Lista de vales (usada por Consultar / Borrar / Cerrar / Imprimir).
     * @route GET /api/vales/lista?rango=&fecha1=&fecha2=&sin_cerrar=&codigo=
     *   rango: 1=histórico (todo), 2=período [fecha1,fecha2] · sin_cerrar: 1=solo abiertos, 0=todos
     *   codigo: 0=todos los empleados, >0=ese empleado
     */
    public function lista(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rango'      => 'nullable|integer|in:1,2',
            'fecha1'     => 'nullable|date',
            'fecha2'     => 'nullable|date',
            'sin_cerrar' => 'nullable|integer|in:0,1',
            'codigo'     => 'nullable|integer',
        ]);
        $q = DB::table('valeefectivo');
        if ((int) ($datos['rango'] ?? 1) === 2 && !empty($datos['fecha1']) && !empty($datos['fecha2'])) {
            $q->whereRaw('CAST(VLE_FEC AS DATE) BETWEEN ? AND ?',
                [Carbon::parse($datos['fecha1'])->toDateString(), Carbon::parse($datos['fecha2'])->toDateString()]);
        }
        if ((int) ($datos['sin_cerrar'] ?? 1) === 1) {
            $q->where(fn ($w) => $w->whereNull('VLE_FCI')->orWhereRaw('CAST(VLE_FCI AS DATE) < ?', [self::SIN_CERRAR]));
        }
        if ((int) ($datos['codigo'] ?? 0) > 0) $q->where('VLE_EMC', (int) $datos['codigo']);

        $rows = $q->orderByDesc('VLE_NRO')->get()->map(fn ($v) => $this->filaVale($v));
        return response()->json($rows);
    }

    /** Lista de vales BORRADOS. @route GET /api/vales/borrados?rango=&fecha1=&fecha2= */
    public function listaBorrados(Request $request): JsonResponse
    {
        $datos = $request->validate(['rango' => 'nullable|integer|in:1,2', 'fecha1' => 'nullable|date', 'fecha2' => 'nullable|date']);
        $q = DB::table('valeborrados');
        if ((int) ($datos['rango'] ?? 1) === 2 && !empty($datos['fecha1']) && !empty($datos['fecha2'])) {
            $q->whereRaw('CAST(VLE_FEC AS DATE) BETWEEN ? AND ?',
                [Carbon::parse($datos['fecha1'])->toDateString(), Carbon::parse($datos['fecha2'])->toDateString()]);
        }
        $rows = $q->orderByDesc('VLE_NRO')->get()->map(fn ($v) => [
            'numero'   => (int) $v->VLE_NRO,
            'fecha'    => $v->VLE_FEC ? Carbon::parse($v->VLE_FEC)->format('d/m/Y h:i A') : '',
            'codemp'   => (int) $v->VLE_EMC,
            'nombre'   => trim((string) $v->VLE_EMD),
            'importe'  => (float) $v->VLE_IMP,
            'razon'    => trim((string) $v->VLE_RAZ),
            'autorizo' => trim((string) $v->VLE_AUT),
            'borrado_razon' => trim((string) ($v->VLE_BRA ?? '')),
            'borrado_por'   => trim((string) ($v->VLE_RES ?? '')),
        ]);
        return response()->json($rows);
    }

    /** Borra vales seleccionados (los archiva en valeborrados). @route POST /api/vales/borrar */
    public function borrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'numeros'   => 'required|array|min:1',
            'numeros.*' => 'integer',
            'razon'     => 'required|string|max:200',
        ]);
        $usuario = mb_strtoupper((string) ($request->user()->NOMBRE ?? $request->user()->name ?? ''));
        $sello = $usuario . ' / ' . Carbon::now()->format('d/m/Y H:i:s');

        DB::transaction(function () use ($datos, $usuario, $sello) {
            foreach ($datos['numeros'] as $nro) {
                $v = DB::table('valeefectivo')->where('VLE_NRO', (int) $nro)->first();
                if (!$v) continue;
                DB::table('valeborrados')->insert(Registro::completar('valeborrados', [
                    'VLE_NRO' => $v->VLE_NRO, 'VLE_FEC' => $v->VLE_FEC, 'VLE_EMC' => $v->VLE_EMC, 'VLE_EMD' => $v->VLE_EMD,
                    'VLE_IMP' => $v->VLE_IMP, 'VLE_RAZ' => $v->VLE_RAZ, 'VLE_AUT' => $v->VLE_AUT, 'VLE_TER' => $v->VLE_TER,
                    'VLE_USU' => $v->VLE_USU, 'VLE_FCI' => $v->VLE_FCI, 'VLE_GAS' => $v->VLE_GAS, 'VLE_AJU' => $v->VLE_AJU,
                    'VLE_VUE' => $v->VLE_VUE, 'VLE_COM' => $v->VLE_COM, 'VLE_BRA' => trim($datos['razon']), 'VLE_RES' => $sello,
                ]));
                DB::table('valeefectivo')->where('VLE_NRO', (int) $nro)->delete();
                DB::table('ccfondo')->where('CCF_VAL', (int) $nro)->delete();
            }
        });
        return response()->json(['ok' => true, 'borrados' => count($datos['numeros'])]);
    }

    /** Datos de un vale para imprimir su comprobante. @route GET /api/vales/{nro}/impresion */
    public function impresion(int $nro): JsonResponse
    {
        $v = DB::table('valeefectivo')->where('VLE_NRO', $nro)->first();
        if (!$v) return response()->json(['message' => 'Vale no encontrado.'], 404);
        $p = DB::table('personal')->where('PER_COD', (int) $v->VLE_EMC)->first(['PER_NOM', 'PER_CUI', 'PER_EMP']);
        $e = $p ? DB::table('empresas')->where('EMP_COD', (int) $p->PER_EMP)->first(['EMP_NOM', 'EMP_DOM', 'EMP_CUI']) : null;
        return response()->json(['vale' => [
            'numero'            => (int) $v->VLE_NRO,
            'empresa_nombre'    => trim((string) ($e->EMP_NOM ?? '')),
            'empresa_domicilio' => trim((string) ($e->EMP_DOM ?? '')),
            'empresa_cuit'      => trim((string) ($e->EMP_CUI ?? '')),
            'personal_nombre'   => mb_strtoupper(trim((string) ($p->PER_NOM ?? $v->VLE_EMD))),
            'personal_cuit'     => trim((string) ($p->PER_CUI ?? '')),
            'importe'           => (float) $v->VLE_IMP,
            'en_letras'         => 'PESOS ' . $this->enLetras((float) $v->VLE_IMP),
            'fecha'             => $v->VLE_FEC ? Carbon::parse($v->VLE_FEC)->format('d/m/Y') : '',
            'observaciones'     => trim((string) $v->VLE_RAZ),
            'fondo_salida'      => ((int) $v->VLE_FON) === 1 ? 'F.AUTOELEVADORES' : 'F.LOGISTICA',
        ]]);
    }

    /** Cierra un vale (rendición). @route POST /api/vales/cerrar */
    public function cerrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'numero'      => 'required|integer',
            'fecha_cierre' => 'required|date',
            'entregado'   => 'required|numeric',
            'repuestos'   => 'nullable|numeric',
            'gastos'      => 'nullable|numeric',
            'combustible' => 'nullable|numeric',
            'ajuste'      => 'nullable|numeric',
            'vuelto'      => 'nullable|numeric',
            'comprobantes' => 'nullable|numeric',
        ]);
        $v = DB::table('valeefectivo')->where('VLE_NRO', (int) $datos['numero'])->first();
        if (!$v) return response()->json(['message' => 'Vale no encontrado.'], 404);

        $fc = Carbon::parse($datos['fecha_cierre']);
        $vuelto = (float) ($datos['vuelto'] ?? 0);
        $usuario = mb_strtoupper((string) ($request->user()->NOMBRE ?? $request->user()->name ?? ''));

        DB::transaction(function () use ($datos, $v, $fc, $vuelto, $usuario) {
            DB::table('valeefectivo')->where('VLE_NRO', (int) $datos['numero'])->update([
                'VLE_FCI' => $fc, 'VLE_REP' => (float) ($datos['repuestos'] ?? 0), 'VLE_GAS' => (float) ($datos['gastos'] ?? 0),
                'VLE_CBU' => (float) ($datos['combustible'] ?? 0), 'VLE_AJU' => (float) ($datos['ajuste'] ?? 0),
                'VLE_VUE' => $vuelto, 'VLE_COM' => (float) ($datos['comprobantes'] ?? 0),
            ]);
            if ($vuelto != 0) {
                $esVuelto = $vuelto > 0;
                DB::table('ccfondo')->insert(Registro::completar('ccfondo', [
                    'CCF_FEC' => $fc,
                    'CCF_DET' => ($esVuelto ? 'Vuelto VALE Nro.' : 'Compensación VALE Nro.') . $datos['numero'],
                    'CCF_DEB' => $esVuelto ? $vuelto : 0,
                    'CCF_HAB' => $esVuelto ? 0 : abs($vuelto),
                    'CCF_USU' => $usuario, 'CCF_TER' => $usuario, 'CCF_VAL' => (int) $datos['numero'], 'CCF_EMP' => (int) $v->VLE_FON,
                ]));
            }
        });
        return response()->json(['ok' => true, 'compensacion' => $vuelto < 0 ? abs($vuelto) : 0]);
    }

    /** Ingreso de tesorería al fondo fijo. @route POST /api/vales/tesoreria */
    public function tesoreria(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha'   => 'required|date',
            'hora'    => 'nullable|integer',
            'detalle' => 'required|string|max:200',
            'importe' => 'required|numeric|min:0.01',
            'fondo'   => 'required|integer|in:1,2',
            'autorizo' => 'nullable|string|max:100',
        ]);
        $hora = (int) ($datos['hora'] ?? 0);
        $fechaHora = Carbon::parse($datos['fecha'])->setTime(intdiv($hora, 100), $hora % 100, 0);
        $usuario = mb_strtoupper((string) ($request->user()->NOMBRE ?? $request->user()->name ?? ''));

        DB::table('ccfondo')->insert(Registro::completar('ccfondo', [
            'CCF_FEC' => $fechaHora,
            'CCF_DET' => 'Ingreso de Tesorería: ' . trim($datos['detalle']) . ($datos['autorizo'] ? ' (Aut: ' . trim($datos['autorizo']) . ')' : ''),
            'CCF_DEB' => (float) $datos['importe'],   // ingreso = DEBE (entra al fondo)
            'CCF_HAB' => 0,
            'CCF_USU' => $usuario, 'CCF_TER' => $usuario, 'CCF_VAL' => 0, 'CCF_EMP' => (int) $datos['fondo'],
        ]));
        return response()->json(['ok' => true]);
    }

    /** Vales pendientes (abiertos). @route GET /api/vales/pendientes?opcion=&empresa_id=&codigo= */
    public function pendientes(Request $request): JsonResponse
    {
        $datos = $request->validate(['opcion' => 'required|integer|in:1,2,3', 'empresa_id' => 'nullable|integer', 'codigo' => 'nullable|integer']);
        $q = DB::table('valeefectivo as v')
            ->join('personal as p', 'v.VLE_EMC', '=', 'p.PER_COD')
            ->join('empresas as e', 'p.PER_EMP', '=', 'e.EMP_COD')
            ->where(fn ($w) => $w->whereNull('v.VLE_FCI')->orWhereRaw('CAST(v.VLE_FCI AS DATE) < ?', [self::SIN_CERRAR]));
        if ((int) $datos['opcion'] === 2 && (int) ($datos['empresa_id'] ?? 0) > 0) $q->where('p.PER_EMP', (int) $datos['empresa_id']);
        if ((int) $datos['opcion'] === 3 && (int) ($datos['codigo'] ?? 0) > 0) $q->where('v.VLE_EMC', (int) $datos['codigo']);

        $filas = $q->orderBy('e.EMP_NOM')->orderBy('v.VLE_EMD')->orderBy('v.VLE_FEC')
            ->get(['v.VLE_NRO', 'v.VLE_FEC', 'v.VLE_IMP', 'v.VLE_EMC', 'v.VLE_EMD', 'v.VLE_RAZ',
                'e.EMP_COD', DB::raw('LTRIM(RTRIM(e.EMP_NOM)) as EMP_NOM')]);

        $empresas = [];
        foreach ($filas as $r) {
            $emp = (int) $r->EMP_COD;
            $empresas[$emp]['emp_nom'] = trim((string) $r->EMP_NOM);
            $empresas[$emp]['rows'][] = [
                'numero' => (int) $r->VLE_NRO, 'fecha' => $r->VLE_FEC ? Carbon::parse($r->VLE_FEC)->format('d/m/Y') : '',
                'codemp' => (int) $r->VLE_EMC, 'nombre' => trim((string) $r->VLE_EMD), 'importe' => (float) $r->VLE_IMP, 'razon' => trim((string) $r->VLE_RAZ),
            ];
        }
        $out = [];
        foreach ($empresas as $g) {
            $out[] = ['emp_nom' => $g['emp_nom'], 'total' => array_sum(array_column($g['rows'], 'importe')), 'rows' => $g['rows']];
        }
        usort($out, fn ($a, $b) => strcmp($a['emp_nom'], $b['emp_nom']));
        return response()->json(['empresas' => $out, 'total' => (float) $filas->sum('VLE_IMP')]);
    }

    /** Consulta del Fondo Fijo (movimientos de ccfondo + saldos). @route GET /api/vales/fondo-fijo?fondo=&fecha1=&fecha2= */
    public function fondoFijo(Request $request): JsonResponse
    {
        $datos = $request->validate(['fondo' => 'required|integer|in:1,2', 'fecha1' => 'required|date', 'fecha2' => 'required|date']);
        $fondo = (int) $datos['fondo'];
        $f1 = Carbon::parse($datos['fecha1'])->toDateString();
        $f2 = Carbon::parse($datos['fecha2'])->toDateString();

        $base = fn () => DB::table('ccfondo')->where('CCF_EMP', $fondo);
        // saldo = SUM(DEBE) - SUM(HABER)
        $saldoHasta = fn ($fecha) => (float) ((clone $base())->whereRaw('CAST(CCF_FEC AS DATE) <= ?', [$fecha])->sum('CCF_DEB'))
            - (float) ((clone $base())->whereRaw('CAST(CCF_FEC AS DATE) <= ?', [$fecha])->sum('CCF_HAB'));

        $saldoAnterior = $saldoHasta(Carbon::parse($datos['fecha1'])->subDay()->toDateString());
        $saldoFinal = $saldoHasta($f2);
        $totalPeriodo = $saldoFinal - $saldoAnterior;

        $movs = (clone $base())->whereRaw('CAST(CCF_FEC AS DATE) BETWEEN ? AND ?', [$f1, $f2])
            ->orderBy('CCF_FEC')->get(['CCF_FEC', 'CCF_DET', 'CCF_DEB', 'CCF_HAB', 'CCF_TER'])
            ->map(fn ($m) => [
                'fecha'   => $m->CCF_FEC ? Carbon::parse($m->CCF_FEC)->format('d/m/Y') : '',
                'detalle' => trim((string) $m->CCF_DET),
                'debe'    => (float) $m->CCF_DEB,
                'haber'   => (float) $m->CCF_HAB,
                'terminal' => trim((string) $m->CCF_TER),
            ]);

        return response()->json([
            'movimientos'   => $movs,
            'total_debe'    => (float) (clone $base())->whereRaw('CAST(CCF_FEC AS DATE) BETWEEN ? AND ?', [$f1, $f2])->sum('CCF_DEB'),
            'total_haber'   => (float) (clone $base())->whereRaw('CAST(CCF_FEC AS DATE) BETWEEN ? AND ?', [$f1, $f2])->sum('CCF_HAB'),
            'saldo_anterior' => round($saldoAnterior, 2),
            'total_periodo' => round($totalPeriodo, 2),
            'saldo_final'   => round($saldoFinal, 2),
        ]);
    }

    /** Arma la fila de un vale para las grillas. */
    private function filaVale(object $v): array
    {
        $cerrado = $v->VLE_FCI && Carbon::parse($v->VLE_FCI)->gte(Carbon::parse(self::SIN_CERRAR));
        return [
            'numero'    => (int) $v->VLE_NRO,
            'fecha'     => $v->VLE_FEC ? Carbon::parse($v->VLE_FEC)->format('d/m/Y h:i A') : '',
            'fecha_iso' => $v->VLE_FEC ? Carbon::parse($v->VLE_FEC)->format('Y-m-d') : '',
            'hora'      => $v->VLE_FEC ? Carbon::parse($v->VLE_FEC)->format('H:i') : '',
            'codemp'    => (int) $v->VLE_EMC,
            'nombre'    => trim((string) $v->VLE_EMD),
            'importe'   => (float) $v->VLE_IMP,
            'razon'     => trim((string) $v->VLE_RAZ),
            'autorizo'  => trim((string) $v->VLE_AUT),
            'terminal'  => trim((string) $v->VLE_TER),
            'usuario'   => trim((string) $v->VLE_USU),
            'cerrado'   => $cerrado,
            'fondo'     => (int) $v->VLE_FON,
        ];
    }

    /** Convierte un importe a su expresión en letras (mayúsculas), con centavos NN/100. */
    private function enLetras(float $n): string
    {
        $entero = (int) floor($n);
        $cent = (int) round(($n - $entero) * 100);
        $palabras = $entero === 0 ? 'CERO' : mb_strtoupper(trim($this->grupo($entero)));
        return $palabras . ' CON ' . str_pad((string) $cent, 2, '0', STR_PAD_LEFT) . '/100';
    }

    private function grupo(int $n): string
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
            'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
        $decenas = ['', '', 'VEINTI', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        if ($n === 0) return '';
        if ($n === 100) return 'CIEN';
        if ($n < 0) return $this->grupo(-$n);

        if ($n >= 1000000) {
            $mill = intdiv($n, 1000000); $resto = $n % 1000000;
            $pref = $mill === 1 ? 'UN MILLON' : trim($this->grupo($mill)) . ' MILLONES';
            return trim($pref . ' ' . $this->grupo($resto));
        }
        if ($n >= 1000) {
            $miles = intdiv($n, 1000); $resto = $n % 1000;
            $pref = $miles === 1 ? 'MIL' : trim($this->grupo($miles)) . ' MIL';
            return trim($pref . ' ' . $this->grupo($resto));
        }
        if ($n >= 100) {
            $c = intdiv($n, 100); $resto = $n % 100;
            return trim($centenas[$c] . ' ' . $this->grupo($resto));
        }
        if ($n <= 20) return $unidades[$n];
        if ($n < 30) return 'VEINTI' . $unidades[$n - 20];   // VEINTIUNO, VEINTIDOS, ...
        $d = intdiv($n, 10); $u = $n % 10;
        return trim($decenas[$d] . ($u ? ' Y ' . $unidades[$u] : ''));
    }
}
