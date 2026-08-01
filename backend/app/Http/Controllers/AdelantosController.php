<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdelantosController — Adelantos / Anticipos al personal.
 *
 * Réplica de adelantos_agregar.scx y adelantos_borrar.scx (FoxPro RRHHWINSQL).
 * Cada adelanto graba:
 *   - una cabecera de liquidación en `liquidac` (LIQ_TIP 7=ADELANTOS / 12=ADELANTOS SAC),
 *   - su ítem en `liq_ite` (LIT_COD 999),
 *   - el registro en `anticipo` (ANT_LIQ apunta a la liquidación generada),
 * todo en una transacción. Devuelve los datos para imprimir el comprobante (vale).
 */
class AdelantosController extends Controller
{
    private const COD_ADELANTOS = 999;       // LIT_COD para adelantos
    private const TIP_SUELDO     = 7;         // LIQ/LIT_TIP — ADELANTOS
    private const TIP_SAC        = 12;        // LIQ/LIT_TIP — ADELANTOS SAC

    /** Datos del empleado para el formulario. @route GET /api/adelantos/empleado/{cod} */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)
            ->first(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_JOM', 'PER_BAJ', 'PER_EMP']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 404);
        }
        return response()->json([
            'codigo'       => (int) $p->PER_COD,
            'nombre'       => trim((string) $p->PER_NOM),
            'cuit'         => trim((string) $p->PER_CUI),
            'es_jornalero' => trim((string) $p->PER_JOM) === 'J',
        ]);
    }

    /** Adelantos (anticipos) ya cargados de un empleado. @route GET /api/adelantos/empleado/{cod}/lista */
    public function lista(int $cod): JsonResponse
    {
        $rows = DB::table('anticipo')->where('ANT_PER', $cod)->orderByDesc('ANT_NRO')->get()
            ->map(fn ($a) => [
                'numero'    => (int) $a->ANT_NRO,
                'fecha'     => $a->ANT_FEC ? Carbon::parse($a->ANT_FEC)->format('d/m/Y') : '',
                'quincena'  => (int) $a->ANT_QUI,
                'mes'       => (int) $a->ANT_MES,
                'anio'      => (int) $a->ANT_ANO,
                'detalle'   => trim((string) $a->ANT_DET),
                'importe'   => (float) $a->ANT_IMP,
                'liq'       => (int) $a->ANT_LIQ,
                'forma_pago' => (int) $a->ANT_TIP,
            ])->values();
        return response()->json($rows);
    }

    /**
     * Listado de adelantos otorgados (agrupado por empleado).
     * @route GET /api/adelantos/listado?empleado=&periodo=&tipo_periodo=&fecha1=&fecha2=
     *   empleado: 0=todos, >0=ese empleado
     *   periodo: 1=histórico (todo), 2=rango
     *   tipo_periodo: 1=por emisión (ANT_FEC), 2=por imputación (ANT_MES/ANT_ANO)  (solo si rango)
     */
    public function listado(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empleado'     => 'nullable|integer',
            'periodo'      => 'required|integer|in:1,2',
            'tipo_periodo' => 'nullable|integer|in:1,2',
            'fecha1'       => 'nullable|date',
            'fecha2'       => 'nullable|date',
        ]);

        $q = DB::table('anticipo')->where('ANT_PER', '>', 0);
        if ((int) ($d['empleado'] ?? 0) > 0) {
            $q->where('ANT_PER', (int) $d['empleado']);
        }

        $subtitulo = '';
        if ((int) $d['periodo'] === 2 && !empty($d['fecha1']) && !empty($d['fecha2'])) {
            $f1 = Carbon::parse($d['fecha1']); $f2 = Carbon::parse($d['fecha2']);
            if ((int) ($d['tipo_periodo'] ?? 1) === 2) {
                // Por imputación (mes/año)
                $desde = $f1->year * 100 + $f1->month;
                $hasta = $f2->year * 100 + $f2->month;
                $q->whereRaw('(ANT_ANO * 100 + ANT_MES) BETWEEN ? AND ?', [$desde, $hasta]);
            } else {
                // Por emisión (fecha)
                $q->whereRaw('CAST(ANT_FEC AS DATE) BETWEEN ? AND ?', [$f1->toDateString(), $f2->toDateString()]);
            }
            $subtitulo = 'Desde el ' . $f1->format('d/m/Y') . ' al ' . $f2->format('d/m/Y');
        }

        $rows = $q->orderBy('ANT_PER')->orderBy('ANT_FEC')->get();

        $grupos = [];
        $totalGeneral = 0.0;
        foreach ($rows as $a) {
            $cod = (int) $a->ANT_PER;
            if (!isset($grupos[$cod])) {
                $grupos[$cod] = ['codigo' => $cod, 'nombre' => trim((string) $a->ANT_DEP), 'subtotal' => 0.0, 'rows' => []];
            }
            $grupos[$cod]['rows'][] = [
                'numero'  => (int) $a->ANT_NRO,
                'fecha'   => $a->ANT_FEC ? Carbon::parse($a->ANT_FEC)->format('d/m/Y') : '',
                'mes'     => (int) $a->ANT_MES,
                'anio'    => (int) $a->ANT_ANO,
                'detalle' => trim((string) $a->ANT_DET),
                'importe' => (float) $a->ANT_IMP,
                'liq'     => (int) $a->ANT_LIQ,
            ];
            $grupos[$cod]['subtotal'] += (float) $a->ANT_IMP;
            $totalGeneral += (float) $a->ANT_IMP;
        }

        if ((int) ($d['empleado'] ?? 0) > 0 && $grupos) {
            $g = reset($grupos);
            $subtitulo = trim($subtitulo . ' Empleado: ' . $g['nombre']);
        }

        return response()->json([
            'titulo'        => 'ADELANTOS ENTREGADOS',
            'subtitulo'     => trim($subtitulo),
            'grupos'        => array_values($grupos),
            'total_general' => round($totalGeneral, 2),
        ]);
    }

    /**
     * Grilla plana de todos los adelantos para el ABM unificado.
     * @route GET /api/adelantos/grilla?emp=   (emp opcional: acota a un empleado — uso futuro desde la ficha)
     */
    public function grilla(Request $request): JsonResponse
    {
        $formas = [1 => 'Efectivo', 2 => 'Banco', 3 => 'Otros'];
        $q = DB::table('anticipo')->where('ANT_PER', '>', 0);
        if ($emp = (int) $request->input('emp', 0)) {
            $q->where('ANT_PER', $emp);
        }
        $rows = $q->orderByDesc('ANT_NRO')->get()->map(fn ($a) => [
            'numero'          => (int) $a->ANT_NRO,
            'empleado'        => (int) $a->ANT_PER,
            'empleado_nombre' => trim((string) $a->ANT_DEP),
            'fecha'           => $a->ANT_FEC ? Carbon::parse($a->ANT_FEC)->format('d/m/Y') : '',
            'quincena'        => (int) $a->ANT_QUI,
            'mes'             => (int) $a->ANT_MES,
            'anio'            => (int) $a->ANT_ANO,
            'imputa'          => str_pad((string) $a->ANT_MES, 2, '0', STR_PAD_LEFT) . '/' . $a->ANT_ANO,
            'detalle'         => trim((string) $a->ANT_DET),
            'importe'         => (float) $a->ANT_IMP,
            'forma_pago'      => (int) $a->ANT_TIP,
            'forma_pago_txt'  => $formas[(int) $a->ANT_TIP] ?? '—',
            'liq'             => (int) $a->ANT_LIQ,
        ])->values();
        return response()->json($rows);
    }

    /** Graba un adelanto. @route POST /api/adelantos */
    public function crear(Request $request): JsonResponse
    {
        $d = $request->validate([
            'codigo'        => 'required|integer',
            'fecha_adelanto' => 'required|date',
            'mes'           => 'required|integer|min:1|max:12',
            'anio'          => 'required|integer|min:2000|max:2100',
            'tipo'          => 'required|integer|in:1,2',  // 1=SUELDO, 2=SAC
            'forma_pago'    => 'required|integer|in:1,2,3', // 1=EFECTIVO, 2=BANCO, 3=OTROS
            'quincena'      => 'nullable|integer|in:0,1,2',
            'detalle'       => 'nullable|string|max:100',
            'importe'       => 'required|numeric',
        ]);

        // ── Validaciones de negocio (réplica del FoxPro) ──
        if ((float) $d['importe'] <= 0) {
            return response()->json(['message' => 'Verifique el importe del anticipo.'], 422);
        }
        $hoy = Carbon::today();
        $fechaAdel = Carbon::parse($d['fecha_adelanto'])->startOfDay();
        if ($fechaAdel->lt($hoy)) {
            return response()->json(['message' => 'No puede ingresar un adelanto de fecha anterior a la presente.'], 422);
        }
        $avisos = [];
        if ((int) $d['mes'] !== (int) $hoy->month) {
            $avisos[] = 'El mes imputado difiere del mes en curso.';
        }
        if ((int) $d['anio'] !== (int) $hoy->year) {
            $avisos[] = 'El año imputado difiere del año en curso.';
        }

        $p = DB::table('personal')->where('PER_COD', (int) $d['codigo'])
            ->first(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_ING', 'PER_EMP', 'PER_CON', 'PER_CAT']);
        if (!$p) {
            return response()->json(['message' => 'Código de empleado erróneo.'], 422);
        }

        if (!\App\Support\Empleado::activo((int) $d['codigo'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }

        $esSac   = (int) $d['tipo'] === 2;
        $tipLiq  = $esSac ? self::TIP_SAC : self::TIP_SUELDO;
        $tidLiq  = $esSac ? 'ADELANTOS SAC' : 'ADELANTOS';
        $importe = (float) $d['importe'];
        $nombre  = trim((string) $p->PER_NOM);
        $cuitFmt = $this->formatearCuit((string) $p->PER_CUI);
        $fechaAplicada = $hoy->copy()->startOfMonth();   // Date(año, mes, 1) del mes en curso

        try {
            $antNro = DB::transaction(function () use ($p, $d, $importe, $nombre, $cuitFmt, $tipLiq, $tidLiq, $hoy, $fechaAplicada, $fechaAdel) {
                // 1) Cabecera de liquidación
                $liqNro = ((int) DB::table('liquidac')->max('LIQ_NRO')) + 1;
                DB::table('liquidac')->insert(Registro::completar('liquidac', [
                    'LIQ_NRO' => $liqNro,
                    'LIQ_COD' => (int) $p->PER_COD,
                    'LIQ_DEP' => mb_substr($nombre, 0, 50),
                    'LIQ_CUI' => $cuitFmt,
                    'LIQ_ING' => $p->PER_ING,
                    'LIQ_EMP' => (int) $p->PER_EMP,
                    'LIQ_CON' => $p->PER_CON,
                    'LIQ_CAT' => $p->PER_CAT,
                    'LIQ_TIP' => $tipLiq,
                    'LIQ_TID' => $tidLiq,
                    'LIQ_MES' => (int) $hoy->month,
                    'LIQ_ANO' => (int) $hoy->year,
                    'LIQ_FEC' => $fechaAplicada,
                    'LIQ_PAG' => $hoy,
                ]));

                // 2) Ítem de la liquidación
                DB::table('liq_ite')->insert(Registro::completar('liq_ite', [
                    'LIT_NRO' => $liqNro,
                    'LIT_PER' => (int) $p->PER_COD,
                    'LIT_MES' => (int) $hoy->month,
                    'LIT_ANO' => (int) $hoy->year,
                    'LIT_FEC' => $fechaAplicada,
                    'LIT_TIP' => $tipLiq,
                    'LIT_COD' => self::COD_ADELANTOS,
                    'LIT_DES' => $tidLiq,
                    'LIT_CAN' => 1,
                    'LIT_HAB' => $importe,
                    'LIT_DED' => 0,
                ]));

                // 3) Registro del anticipo
                $antNro = ((int) DB::table('anticipo')->max('ANT_NRO')) + 1;
                DB::table('anticipo')->insert(Registro::completar('anticipo', [
                    'ANT_NRO' => $antNro,
                    'ANT_PER' => (int) $p->PER_COD,
                    'ANT_DEP' => mb_substr($nombre, 0, 50),
                    'ANT_FEC' => $fechaAdel,
                    'ANT_QUI' => (int) ($d['quincena'] ?? 0),
                    'ANT_MES' => (int) $d['mes'],
                    'ANT_ANO' => (int) $d['anio'],
                    'ANT_DET' => trim((string) ($d['detalle'] ?? '')),
                    'ANT_IMP' => $importe,
                    'ANT_LIQ' => $liqNro,
                    'ANT_TIP' => (int) $d['forma_pago'],
                ]));

                return $antNro;
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se puede ingresar el adelanto: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'ok'         => true,
            'numero'     => $antNro,
            'avisos'     => $avisos,
            'comprobante' => $this->comprobante($antNro),
        ]);
    }

    /** Datos del comprobante para imprimir. @route GET /api/adelantos/{nro}/comprobante */
    public function comprobanteEndpoint(int $nro): JsonResponse
    {
        $c = $this->comprobante($nro);
        if (!$c) return response()->json(['message' => 'Adelanto no encontrado.'], 404);
        return response()->json(['comprobante' => $c]);
    }

    /** Borra un adelanto y su liquidación asociada. @route DELETE /api/adelantos/{nro} */
    public function borrar(int $nro): JsonResponse
    {
        $a = DB::table('anticipo')->where('ANT_NRO', $nro)->first();
        if (!$a) {
            return response()->json(['message' => 'Adelanto no encontrado.'], 404);
        }
        try {
            DB::transaction(function () use ($a) {
                DB::table('liquidac')->where('LIQ_NRO', (int) $a->ANT_LIQ)->delete();
                DB::table('liq_ite')->where('LIT_NRO', (int) $a->ANT_LIQ)->delete();
                DB::table('anticipo')->where('ANT_NRO', (int) $a->ANT_NRO)->delete();
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se puede borrar el adelanto: ' . $e->getMessage()], 500);
        }
        return response()->json(['ok' => true]);
    }

    /** Arma los datos del comprobante (vale) de un anticipo. */
    private function comprobante(int $nro): ?array
    {
        $a = DB::table('anticipo')->where('ANT_NRO', $nro)->first();
        if (!$a) return null;
        $p = DB::table('personal')->where('PER_COD', (int) $a->ANT_PER)->first(['PER_NOM', 'PER_CUI']);

        $tip = (int) $a->ANT_TIP;
        $linea = $tip < 2 ? 'ADELANTO DE DINERO EN EFECTIVO'
            : ($tip === 2 ? 'ADELANTO DE DINERO POR BANCO' : 'ADELANTO DE DINERO POR OTROS MEDIOS DE PAGO');

        return [
            'numero'          => (int) $a->ANT_NRO,
            'personal_nombre' => mb_strtoupper(trim((string) ($p->PER_NOM ?? $a->ANT_DEP))),
            'personal_cuit'   => trim((string) ($p->PER_CUI ?? '')),
            'linea'           => $linea,
            'importe'         => (float) $a->ANT_IMP,
            'en_letras'       => 'PESOS ' . $this->enLetras((float) $a->ANT_IMP),
            'fecha'           => $a->ANT_FEC ? Carbon::parse($a->ANT_FEC)->format('d/m/Y') : '',
            'observaciones'   => trim((string) $a->ANT_DET),
            'aplicar_mes'     => str_pad((string) $a->ANT_MES, 2, '0', STR_PAD_LEFT) . '/' . substr((string) $a->ANT_ANO, -2),
        ];
    }

    /** Formatea un CUIT 11 dígitos como NN-NNNNNNNN-N. */
    private function formatearCuit(string $cui): string
    {
        $c = preg_replace('/\D/', '', $cui);
        if (strlen($c) < 11) return trim($cui);
        return substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10, 1);
    }

    /** Importe en letras (mayúsculas) con centavos NN/100. */
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
        if ($n < 30) return 'VEINTI' . $unidades[$n - 20];
        $dd = intdiv($n, 10); $u = $n % 10;
        return trim($decenas[$dd] . ($u ? ' Y ' . $unidades[$u] : ''));
    }
}
