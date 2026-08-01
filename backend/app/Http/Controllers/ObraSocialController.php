<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ObraSocialController — ABM de Obras Sociales (obras_sociales) con 2 solapas:
 *  - Datos Generales: ABM de la obra social (estado A/P, nombre, domicilio, tel, email).
 *  - Comprobantes: movimientos de cuenta corriente (obras_comprobantes) con Debe/Haber/Saldo.
 *
 * Regla Debe/Haber: si OCOM_TDO == "N.C" (nota de crédito) el importe va al HABER; si no, al DEBE.
 */
class ObraSocialController extends Controller
{
    // ── Solapa Datos Generales (obras_sociales) ────────────────

    /** @route GET /api/obras-sociales?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('obras_sociales');
        if ($b = trim((string) $request->input('buscar', ''))) {
            $q->where('OBR_NOM', 'like', "%$b%");
        }
        $rows = $q->orderBy('OBR_NOM')->get()->map(fn ($o) => [
            'cod'       => (int) $o->OBR_COD,
            'nombre'    => trim((string) $o->OBR_NOM),
            'domicilio' => trim((string) $o->OBR_DOM),
            'telefono'  => trim((string) $o->OBR_TEL),
            'email'     => trim((string) $o->OBR_EMA),
            'estado'    => trim((string) $o->OBR_AOP) === 'P' ? 'P' : 'A',
        ]);
        return response()->json($rows);
    }

    /** @route POST /api/obras-sociales */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = ((int) DB::table('obras_sociales')->max('OBR_COD')) + 1;
        DB::table('obras_sociales')->insert(Registro::completar('obras_sociales', array_merge($this->vals($d), ['OBR_COD' => $cod])));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/obras-sociales/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        $d = $this->validar($request);
        $afect = DB::table('obras_sociales')->where('OBR_COD', $cod)->update($this->vals($d));
        if (!$afect) return response()->json(['message' => 'Obra social no encontrada.'], 404);
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/obras-sociales/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        if (DB::table('obras_comprobantes')->where('OCOM_OBR', $cod)->exists()) {
            return response()->json(['message' => 'No se puede eliminar: la obra social tiene comprobantes cargados.'], 422);
        }
        DB::table('obras_sociales')->where('OBR_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre'    => 'required|string|max:100',
            'domicilio' => 'nullable|string|max:100',
            'telefono'  => 'nullable|string|max:50',
            'email'     => 'nullable|string|max:100',
            'estado'    => 'required|in:A,P',
        ]);
    }

    private function vals(array $d): array
    {
        return [
            'OBR_NOM' => trim($d['nombre']),
            'OBR_DOM' => trim((string) ($d['domicilio'] ?? '')),
            'OBR_TEL' => trim((string) ($d['telefono'] ?? '')),
            'OBR_EMA' => trim((string) ($d['email'] ?? '')),
            'OBR_AOP' => $d['estado'],
        ];
    }

    // ── Solapa Comprobantes (obras_comprobantes) ───────────────

    /**
     * @route GET /api/obras-sociales/{cod}/comprobantes?periodo=0|1&fecha1=&fecha2=&tipo=&empresa=
     * Devuelve los comprobantes (con Debe/Haber) y el saldo (SUM Debe - Haber).
     */
    public function comprobantes(Request $request, int $cod): JsonResponse
    {
        $q = DB::table('obras_comprobantes')->where('OCOM_OBR', $cod);

        if ((int) $request->input('periodo', 0) === 1) {
            if ($f1 = $request->input('fecha1')) $q->whereDate('OCOM_FEC', '>=', $f1);
            if ($f2 = $request->input('fecha2')) $q->whereDate('OCOM_FEC', '<=', $f2);
        }
        if ($t = trim((string) $request->input('tipo', ''))) {
            $q->whereRaw('LTRIM(RTRIM(OCOM_TDO)) = ?', [$t]);
        }
        if ($e = (int) $request->input('empresa', 0)) {
            $q->where('OCOM_EMP', $e);
        }

        $rows = $q->orderBy('OCOM_FEC')->orderBy('UNICO')->get()->map(function ($r) {
            $imp = (float) $r->OCOM_IMP;
            $esNC = trim((string) $r->OCOM_TDO) === 'N.C';
            return [
                'unico'   => (int) $r->UNICO,
                'fecha'   => $r->OCOM_FEC ? \Carbon\Carbon::parse($r->OCOM_FEC)->format('Y-m-d') : '',
                'tipo'    => trim((string) $r->OCOM_TDO),
                'sucursal' => (int) $r->OCOM_SUC,
                'numero'  => (int) $r->OCOM_NRO,
                'debe'    => $esNC ? 0.0 : $imp,
                'haber'   => $esNC ? $imp : 0.0,
                'empresa_cod' => (int) $r->OCOM_EMP,
                'empresa' => trim((string) $r->OCOM_EMD),
            ];
        });

        $saldo = $rows->sum('debe') - $rows->sum('haber');
        return response()->json(['comprobantes' => $rows->values(), 'saldo' => round($saldo, 2)]);
    }

    /**
     * @route POST /api/obras-sociales/{cod}/comprobantes
     * body: { empresa, empresa_nombre, tipo, sucursal, numero, fecha, importe }
     */
    public function guardarComprobante(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'empresa'  => 'required|integer|min:1',
            'tipo'     => 'required|string|max:10',
            'sucursal' => 'nullable|integer',
            'numero'   => 'required|integer|min:1',
            'fecha'    => 'required|date',
            'importe'  => 'required|numeric',
        ], [
            'empresa.required' => 'Debe seleccionar la empresa a la que aplica el comprobante.',
            'tipo.required'    => 'Debe seleccionar el tipo de comprobante.',
            'numero.required'  => 'Debe ingresar el número de comprobante.',
            'fecha.required'   => 'Debe ingresar la fecha del comprobante.',
        ]);

        $obra = DB::table('obras_sociales')->where('OBR_COD', $cod)->first();
        if (!$obra) return response()->json(['message' => 'Obra social no encontrada.'], 404);
        $tipo = mb_strtoupper(trim($d['tipo']));
        $suc  = (int) ($d['sucursal'] ?? 0);

        // Duplicado: misma obra + fecha + tipo + sucursal + número
        $dup = DB::table('obras_comprobantes')->where('OCOM_OBR', $cod)
            ->whereDate('OCOM_FEC', $d['fecha'])->whereRaw('LTRIM(RTRIM(OCOM_TDO)) = ?', [$tipo])
            ->where('OCOM_SUC', $suc)->where('OCOM_NRO', (int) $d['numero'])->exists();
        if ($dup) {
            return response()->json(['message' => 'Comprobante existente.'], 422);
        }

        $emp = DB::table('empresas')->where('EMP_COD', $d['empresa'])->first();
        DB::table('obras_comprobantes')->insert(Registro::completar('obras_comprobantes', [
            'OCOM_OBR' => $cod,
            'OCOM_OBD' => trim((string) $obra->OBR_NOM),
            'OCOM_SUC' => $suc,
            'OCOM_NRO' => (int) $d['numero'],
            'OCOM_FEC' => $d['fecha'],
            'OCOM_TDO' => $tipo,
            'OCOM_IMP' => (float) $d['importe'],
            'OCOM_EMP' => (int) $d['empresa'],
            'OCOM_EMD' => $emp ? trim((string) $emp->EMP_NOM) : '',
        ]));

        return response()->json(['ok' => true], 201);
    }

    /** @route DELETE /api/obras-sociales/{cod}/comprobantes  body: { unicos:[...] } */
    public function eliminarComprobantes(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['unicos' => 'required|array|min:1', 'unicos.*' => 'integer']);
        $n = DB::table('obras_comprobantes')->where('OCOM_OBR', $cod)
            ->whereIn('UNICO', array_map('intval', $d['unicos']))->delete();
        return response()->json(['ok' => true, 'eliminados' => $n]);
    }

    // ── Importar Excel Pagos Aportes (obras_sociales_importar → obras_aportes) ──

    /**
     * Importa pagos de aportes y contribuciones desde Excel a obras_aportes, por mes/año.
     * Cada fila trae CUIL + total contribución + total aporte. Se busca el empleado por CUIL
     * (personal.PER_CUI); si no existe, se descarta. Si ya había un registro para ese CUIL en el
     * período, se ACTUALIZA; si no, se inserta.
     * @route POST /api/obras-sociales/importar-aportes
     * body: { anio, mes, rows:[{cuil, tcontrib, taporte}] }
     */
    public function importarAportes(Request $request): JsonResponse
    {
        $d = $request->validate([
            'anio' => 'required|integer',
            'mes'  => 'required|integer|min:1|max:12',
            'rows' => 'required|array|min:1',
        ]);
        $anio = (int) $d['anio'];
        $mes  = (int) $d['mes'];
        $usuario = substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30);

        // Mapa de empleados por CUIL (solo dígitos) → datos
        $personal = [];
        foreach (DB::table('personal')->get(['PER_COD', 'PER_CUI', 'PER_NOM', 'PER_COS']) as $p) {
            $k = preg_replace('/\D/', '', (string) $p->PER_CUI);
            if ($k !== '') $personal[$k] = $p;
        }

        $ins = 0; $upd = 0; $noHallados = 0;
        DB::transaction(function () use ($d, $anio, $mes, $usuario, $personal, &$ins, &$upd, &$noHallados) {
            foreach ($d['rows'] as $r) {
                $cuil = preg_replace('/\D/', '', (string) ($r['cuil'] ?? ''));
                if ($cuil === '' || (int) $cuil === 0) continue;
                if (!isset($personal[$cuil])) { $noHallados++; continue; }

                $emp = $personal[$cuil];
                $tcon = $this->aNumero($r['tcontrib'] ?? 0);
                $tapo = $this->aNumero($r['taporte'] ?? 0);
                $total = round($tcon + $tapo, 2);

                $existe = DB::table('obras_aportes')
                    ->where('AYC_CUI', (int) $cuil)->where('AYC_ANIO', $anio)->where('AYC_MES', $mes)->exists();

                if ($existe) {
                    DB::table('obras_aportes')
                        ->where('AYC_CUI', (int) $cuil)->where('AYC_ANIO', $anio)->where('AYC_MES', $mes)
                        ->update([
                            'AYC_TCON' => $tcon, 'AYC_TAPO' => $tapo, 'AYC_TOTAL' => $total, 'AYC_RECON' => $total,
                            'AYC_FIMP' => now(), 'AYC_UIMP' => $usuario,
                        ]);
                    $upd++;
                } else {
                    DB::table('obras_aportes')->insert(Registro::completar('obras_aportes', [
                        'AYC_ANIO' => $anio, 'AYC_MES' => $mes, 'AYC_CUI' => (int) $cuil,
                        'AYC_EMP'  => (int) $emp->PER_COD, 'AYC_NOM' => trim((string) $emp->PER_NOM),
                        'AYC_OSO'  => (int) $emp->PER_COS, 'AYC_TCON' => $tcon, 'AYC_TAPO' => $tapo,
                        'AYC_TOTAL' => $total, 'AYC_RECON' => $total,
                        'AYC_FIMP' => now(), 'AYC_UIMP' => $usuario,
                    ]));
                    $ins++;
                }
            }
        });

        return response()->json(['ok' => true, 'insertados' => $ins, 'actualizados' => $upd, 'no_hallados' => $noHallados]);
    }

    // ── Planilla Contribuciones y Aportes (obras_sociales_aportes) ──

    /**
     * Planilla editable de un período/empresa. Asegura que TODOS los empleados activos de la
     * empresa estén en obras_aportes (da de alta en 0 los que falten) y devuelve la planilla.
     * @route GET /api/obras-sociales/aportes?anio=&mes=&empresa=
     */
    public function aportesIndex(Request $request): JsonResponse
    {
        $d = $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12', 'empresa' => 'required|integer|min:1']);
        $anio = (int) $d['anio']; $mes = (int) $d['mes']; $emp = (int) $d['empresa'];
        $usuario = substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30);

        // Empleados activos de la empresa, indexados por CUIL (dígitos)
        $byCuil = [];
        foreach (DB::table('personal')->where('PER_EMP', $emp)->where('PER_AOP', 'A')->get(['PER_CUI', 'PER_COD', 'PER_NOM', 'PER_COS']) as $e) {
            $k = (int) preg_replace('/\D/', '', (string) $e->PER_CUI);
            if ($k > 0) $byCuil[$k] = $e;
        }
        $cuils = array_keys($byCuil);
        if (!$cuils) return response()->json(['planilla' => [], 'tmedife' => 0, 'tmedicus' => 0]);

        // Alta en cero de los que falten en el período
        $existentes = DB::table('obras_aportes')->where('AYC_ANIO', $anio)->where('AYC_MES', $mes)
            ->whereIn('AYC_CUI', $cuils)->pluck('AYC_CUI')->map(fn ($v) => (int) $v)->flip();
        DB::transaction(function () use ($byCuil, $existentes, $anio, $mes, $usuario) {
            foreach ($byCuil as $cuil => $e) {
                if (isset($existentes[$cuil])) continue;
                DB::table('obras_aportes')->insert(Registro::completar('obras_aportes', [
                    'AYC_ANIO' => $anio, 'AYC_MES' => $mes, 'AYC_CUI' => $cuil,
                    'AYC_EMP' => (int) $e->PER_COD, 'AYC_NOM' => trim((string) $e->PER_NOM), 'AYC_OSO' => (int) $e->PER_COS,
                    'AYC_FIMP' => now(), 'AYC_UIMP' => $usuario,
                ]));
            }
        });

        // Cargar planilla (solo empleados activos de la empresa)
        $tmedife = 0.0; $tmedicus = 0.0;
        $rows = DB::table('obras_aportes')->where('AYC_ANIO', $anio)->where('AYC_MES', $mes)
            ->whereIn('AYC_CUI', $cuils)->orderBy('AYC_NOM')->get()->map(function ($r) use (&$tmedife, &$tmedicus) {
                $recon = (float) $r->AYC_RECON; $medife = (float) $r->AYC_MEDIFE; $medicus = (float) $r->AYC_MEDICUS;
                $dmedife = $medife > 0 ? round($medife - $recon, 2) : 0.0;
                $dmedicus = $medicus > 0 ? round($medicus - $recon, 2) : 0.0;
                $tmedife += $dmedife; $tmedicus += $dmedicus;
                return [
                    'cuil' => (string) (int) $r->AYC_CUI, 'nombre' => trim((string) $r->AYC_NOM),
                    'obra_social' => (int) $r->AYC_OSO,
                    'tcon' => (float) $r->AYC_TCON, 'tapo' => (float) $r->AYC_TAPO, 'total' => (float) $r->AYC_TOTAL,
                    'recon' => $recon, 'medife' => $medife, 'dmedife' => $dmedife, 'medicus' => $medicus, 'dmedicus' => $dmedicus,
                ];
            });

        return response()->json(['planilla' => $rows->values(), 'tmedife' => round($tmedife, 2), 'tmedicus' => round($tmedicus, 2)]);
    }

    /**
     * Confirma los cambios de la planilla (valores editados de Reconocido / FC medife / FC medicus).
     * Recalcula las diferencias en el servidor.
     * @route POST /api/obras-sociales/aportes  body: { anio, mes, rows:[{cuil, recon, medife, medicus}] }
     */
    public function aportesGuardar(Request $request): JsonResponse
    {
        $d = $request->validate(['anio' => 'required|integer', 'mes' => 'required|integer|min:1|max:12', 'rows' => 'present|array']);
        $anio = (int) $d['anio']; $mes = (int) $d['mes'];
        $usuario = substr((string) ($request->user()->name ?? $request->user()->NOMBRE ?? 'RRHH.NET'), 0, 30);

        DB::transaction(function () use ($d, $anio, $mes, $usuario) {
            foreach ($d['rows'] as $r) {
                $cuil = (int) preg_replace('/\D/', '', (string) ($r['cuil'] ?? ''));
                if ($cuil === 0) continue;
                $recon = $this->aNumero($r['recon'] ?? 0);
                $medife = $this->aNumero($r['medife'] ?? 0);
                $medicus = $this->aNumero($r['medicus'] ?? 0);
                DB::table('obras_aportes')->where('AYC_ANIO', $anio)->where('AYC_MES', $mes)->where('AYC_CUI', $cuil)
                    ->update([
                        'AYC_RECON' => $recon, 'AYC_MEDIFE' => $medife, 'AYC_MEDICUS' => $medicus,
                        'AYC_DMEDIFE' => $medife > 0 ? round($medife - $recon, 2) : 0,
                        'AYC_DMEDICUS' => $medicus > 0 ? round($medicus - $recon, 2) : 0,
                        'AYC_FACT' => now(), 'AYC_UACT' => $usuario,
                    ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Convierte a número aceptando ya-numérico o string en formato argentino ("1.000,50"). */
    private function aNumero($v): float
    {
        if (is_int($v) || is_float($v)) {
            return round((float) $v, 2);
        }
        $s = trim((string) $v);
        if ($s === '') return 0.0;
        // Si tiene coma decimal (formato AR): quitar puntos de miles y pasar coma a punto.
        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        $s = preg_replace('/[^\d.\-]/', '', $s);
        return round((float) $s, 2);
    }
}
