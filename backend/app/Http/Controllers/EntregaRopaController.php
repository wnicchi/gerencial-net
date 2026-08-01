<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EntregaRopaController — Entrega de Ropa/EPP a un empleado (ropa_entrega.scx).
 *
 * Registra la entrega de prendas/EPP: por cada ítem inserta en entregaropa,
 * descuenta stock (ropa_stock) y, salvo "no descontar de stock", registra el
 * movimiento de egreso (ropa_movi "E"). Devuelve el payload para imprimir el
 * recibo Resolución 299/2011 (con variante "obsequios" si algún ítem lo es).
 *
 * Tablas: entregaropa (ENR_*), ropa_stock (SEPP_*, col. computada "unico" → no
 * se inserta), ropa_movi (RST_*).
 */
class EntregaRopaController extends Controller
{
    /** Texto por defecto del compromiso (INFORMACION ADICIONAL en FoxPro). */
    public const COMPROMISO_DEFECTO = 'Me comprometo a usar los elementos recibidos en forma obligatoria durante todo el horario de trabajo y exclusivamente dentro de la empresa. El no uso de los mismos permitirá se me apliquen las sanciones disciplinarias correspondientes por no cumplir con las normas de seguridad. Asimismo me comprometo a extremar las medidas necesarias para conservar y mantener en buen estado esos elementos, informando a la supervisión en forma inmediata cualquier anormalidad o extravío.';

    /** Convierte 'aaaa-mm-dd' (o cualquier fecha) a 'dd/mm/aaaa'; vacío si no hay. */
    private function fmtFecha(string $f): string
    {
        $f = trim($f);
        if ($f === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $f, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        return $f;
    }

    /** @route GET /api/entrega-ropa/init — combos (empresas, depósitos, marcas, talles) + compromiso. */
    public function init(): JsonResponse
    {
        return response()->json([
            'empresas'  => DB::table('empresas')->orderBy('EMP_NOM')->get(['EMP_COD', 'EMP_NOM'])
                ->map(fn ($e) => ['cod' => (int) $e->EMP_COD, 'nombre' => trim((string) $e->EMP_NOM)])->values(),
            'depositos' => DB::table('ropadepo')->orderBy('RDE_DES')->get(['RDE_COD', 'RDE_DES'])
                ->map(fn ($d) => ['cod' => (int) $d->RDE_COD, 'nombre' => trim((string) $d->RDE_DES)])->values(),
            'marcas'    => DB::table('ropamarca')->orderBy('RMA_DES')->get(['RMA_COD', 'RMA_DES'])
                ->map(fn ($m) => ['cod' => (int) $m->RMA_COD, 'nombre' => trim((string) $m->RMA_DES)])->values(),
            'talles'    => DB::table('talles')->orderBy('TAL_DES')->get(['TAL_COD', 'TAL_DES'])
                ->map(fn ($t) => ['cod' => (int) $t->TAL_COD, 'nombre' => trim((string) $t->TAL_DES)])->values(),
            'compromiso' => self::COMPROMISO_DEFECTO,
        ]);
    }

    /** @route GET /api/entrega-ropa/empleado/{cod} — datos del empleado. */
    public function empleado(int $cod): JsonResponse
    {
        $p = DB::table('personal')->where('PER_COD', $cod)->first(['PER_NOM', 'PER_CUI', 'PER_NDO']);
        if (!$p) {
            return response()->json(['message' => 'El código del empleado no es válido.'], 404);
        }
        return response()->json([
            'nombre' => trim((string) $p->PER_NOM),
            'cuil'   => trim((string) $p->PER_CUI),
            'dni'    => trim((string) $p->PER_NDO),
        ]);
    }

    /** @route GET /api/entrega-ropa/ropa/{cod} — descripción + obsequio de una prenda. */
    public function ropa(int $cod): JsonResponse
    {
        $r = DB::table('ropa')->where('ROP_COD', $cod)->first(['ROP_COD', 'ROP_DES', 'ROP_OBSEQUIO']);
        if (!$r) {
            return response()->json(['message' => 'Código de prenda/EPP inexistente.'], 404);
        }
        return response()->json([
            'cod'      => (int) $r->ROP_COD,
            'des'      => trim((string) $r->ROP_DES),
            'obsequio' => (bool) $r->ROP_OBSEQUIO,
        ]);
    }

    /** @route POST /api/entrega-ropa — registra la entrega y devuelve datos del recibo. */
    public function entregar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'empresa'        => 'required|integer',
            'deposito'       => 'required|integer|min:1',
            'empleado'       => 'required|integer',
            'motivo'         => 'nullable|string',
            'no_stock'       => 'boolean',
            'items'          => 'present|array',
            'items.*.rcod'   => 'required|integer',
            'items.*.rdes'   => 'nullable|string|max:50',
            'items.*.mcod'   => 'nullable|integer',
            'items.*.mdes'   => 'nullable|string|max:30',
            'items.*.tcod'   => 'nullable|integer',
            'items.*.tdes'   => 'nullable|string|max:20',
            'items.*.cantidad' => 'nullable|integer',
            'items.*.certifica' => 'boolean',
            'items.*.obsequio'  => 'boolean',
            'items.*.fecha'  => 'nullable|date',
        ], [
            'deposito.min' => 'No ha seleccionado el depósito donde egresarán los elementos.',
        ]);

        $emp = DB::table('empresas')->where('EMP_COD', $d['empresa'])->first();
        if (!$emp) {
            return response()->json(['message' => 'Empresa no válida.'], 422);
        }
        $per = DB::table('personal')->where('PER_COD', $d['empleado'])->first(['PER_NOM', 'PER_CUI', 'PER_NDO']);
        if (!$per) {
            return response()->json(['message' => 'El código del empleado no es válido.'], 422);
        }
        if (!\App\Support\Empleado::activo((int) $d['empleado'])) {
            return response()->json(['message' => 'El empleado está dado de baja: no se pueden cargar registros.'], 422);
        }
        $depo = DB::table('ropadepo')->where('RDE_COD', $d['deposito'])->first(['RDE_COD', 'RDE_DES']);
        if (!$depo) {
            return response()->json(['message' => 'Depósito no válido.'], 422);
        }

        // Sólo ítems válidos: código > 0, detalle no vacío, cantidad > 0.
        $validos = array_values(array_filter($d['items'], fn ($it) => (int) ($it['rcod'] ?? 0) > 0
            && trim((string) ($it['rdes'] ?? '')) !== '' && (int) ($it['cantidad'] ?? 0) > 0));
        if (count($validos) === 0) {
            return response()->json(['message' => 'No ha ingresado ningún ítem válido, verifique códigos y cantidades por favor.'], 422);
        }

        $cuil    = trim((string) $per->PER_CUI);
        $noStock = (bool) ($d['no_stock'] ?? false);
        $motivo  = trim((string) ($d['motivo'] ?? '')) ?: self::COMPROMISO_DEFECTO;
        $hoy     = now()->format('Y-m-d');
        $correspondeObsequios = false;

        try {
        DB::transaction(function () use ($validos, $emp, $per, $depo, $cuil, $noStock, $motivo, $hoy, &$correspondeObsequios) {
            foreach ($validos as $it) {
                $rcod = (int) $it['rcod'];
                $rdes = mb_substr(trim((string) ($it['rdes'] ?? '')), 0, 50);
                $mcod = (int) ($it['mcod'] ?? 0);
                $mdes = mb_substr(trim((string) ($it['mdes'] ?? '')), 0, 30);
                $tcod = (int) ($it['tcod'] ?? 0);
                $tdes = mb_substr(trim((string) ($it['tdes'] ?? '')), 0, 20);
                $can  = (int) ($it['cantidad'] ?? 0);
                $cert = (bool) ($it['certifica'] ?? false);
                $obse = (bool) ($it['obsequio'] ?? false);
                $fec  = !empty($it['fecha']) ? substr((string) $it['fecha'], 0, 10) : $hoy;
                if ($obse) {
                    $correspondeObsequios = true;
                }

                // entregaropa
                $detMarca = $rdes . ($mdes !== '' ? ' / ' . $mdes : '');
                DB::table('entregaropa')->insert(Registro::completar('entregaropa', [
                    'ENR_ROP' => $rcod,
                    'ENR_DES' => mb_substr($detMarca, 0, 80),
                    'ENR_CUIL' => $cuil,
                    'ENR_FEC' => $fec,
                    'ENR_CAN' => $can,
                    'ENR_MOT' => $motivo,
                    'ENR_CER' => $cert ? 'S' : 'N',
                    'ENR_STK' => $noStock ? 0 : 1,
                    'ENR_MAC' => $mcod,
                    'ENR_MAD' => $mdes,
                    'ENR_TAL' => $tcod,
                    'ENR_TAD' => $tdes,
                    'ENR_DEP' => (int) $depo->RDE_COD,
                    'ENR_DED' => trim((string) $depo->RDE_DES),
                    'ENR_OBSEQUIO' => $obse ? 1 : 0,
                ]));

                // ropa_stock: descuenta (insert con stock negativo si no existe, o resta). No se toca "unico".
                $existe = DB::table('ropa_stock')
                    ->where('SEPP_COD', $rcod)->where('SEPP_MAR', $mcod)
                    ->where('SEPP_TAL', $tcod)->where('SEPP_DEP', (int) $depo->RDE_COD)->exists();
                if ($existe) {
                    DB::table('ropa_stock')
                        ->where('SEPP_COD', $rcod)->where('SEPP_MAR', $mcod)
                        ->where('SEPP_TAL', $tcod)->where('SEPP_DEP', (int) $depo->RDE_COD)
                        ->decrement('SEPP_STOCK', $can);
                } else {
                    DB::table('ropa_stock')->insert([
                        'SEPP_COD' => $rcod, 'SEPP_DES' => $rdes, 'SEPP_MAR' => $mcod, 'SEPP_MAD' => $mdes,
                        'SEPP_TAL' => $tcod, 'SEPP_TAD' => $tdes, 'SEPP_DEP' => (int) $depo->RDE_COD,
                        'SEPP_DED' => trim((string) $depo->RDE_DES), 'SEPP_STOCK' => -$can,
                    ]);
                }

                // ropa_movi: egreso (sólo si descuenta stock).
                if (!$noStock) {
                    DB::table('ropa_movi')->insert(Registro::completar('ropa_movi', [
                        'RST_IOE' => 'E',
                        'RST_ROC' => $rcod,
                        'RST_ROD' => $rdes,
                        'RST_MAC' => $mcod,
                        'RST_MAD' => $mdes,
                        'RST_TAL' => $tcod,
                        'RST_TAD' => $tdes,
                        'RST_CAN' => $can,
                        'RST_FEC' => now()->format('Y-m-d H:i:s'),
                        'RST_DET' => trim((string) $per->PER_NOM) . ' ' . trim((string) $per->PER_NDO),
                        'RST_DEP' => (int) $depo->RDE_COD,
                        'RST_DES' => trim((string) $depo->RDE_DES),
                    ]));
                }
            }
        });
        } catch (\Throwable $e) {
            // El error SQL ya queda en log_errores_sql (capa de conexión). Acá solo damos el mensaje al usuario.
            Log::error('[entregaRopa] empleado ' . $d['empleado'] . ': ' . $e->getMessage());
            return response()->json(['message' => 'No se pudo registrar la entrega: ' . $e->getMessage()], 500);
        }

        // Puestos y elementos por puesto (para el recibo). Si fallan, la entrega YA quedó registrada:
        // no rompemos el proceso, solo el recibo sale sin esos datos.
        $puestos = []; $elementos = [];
        try {
            $puestos = DB::table('puestoempleado as pe')
                ->join('puestos as p', DB::raw('LTRIM(RTRIM(p.PUE_COD))'), '=', DB::raw('LTRIM(RTRIM(pe.PEM_PUE))'))
                ->where(DB::raw('LTRIM(RTRIM(pe.PEM_CUIL))'), $cuil)
                ->orderByDesc('pe.PEM_ACT')->orderByDesc('pe.PEM_FDES')
                ->pluck('p.PUE_DES')->map(fn ($x) => trim((string) $x))->filter()->values()->all();

            $elementos = DB::table('puestoempleado as pe')
                ->join('puestos as p', DB::raw('LTRIM(RTRIM(p.PUE_COD))'), '=', DB::raw('LTRIM(RTRIM(pe.PEM_PUE))'))
                ->join('elementos as el', DB::raw('LTRIM(RTRIM(el.ELE_PUE))'), '=', DB::raw('LTRIM(RTRIM(p.PUE_COD))'))
                ->where(DB::raw('LTRIM(RTRIM(pe.PEM_CUIL))'), $cuil)
                ->orderBy('el.ELE_DES')
                ->distinct()->pluck('el.ELE_DES')->map(fn ($x) => trim((string) $x))->filter()->unique()->values()->all();
        } catch (\Throwable $e) {
            Log::warning('[entregaRopa] recibo sin puestos/elementos: ' . $e->getMessage());
        }

        return response()->json([
            'ok'        => true,
            'obsequios' => $correspondeObsequios,
            'empresa'   => [
                'razon' => trim((string) $emp->EMP_NOM),
                'cuit'  => trim((string) ($emp->EMP_CUI ?? '')),
                'dom'   => trim((string) ($emp->EMP_DOM ?? '')),
                'loc'   => trim((string) ($emp->EMP_LOC ?? '')),
                'cpo'   => trim((string) ($emp->EMP_CPO ?? '')),
                'prv'   => trim((string) ($emp->EMP_PRV ?? '')),
            ],
            'empleado'  => [
                'nombre' => trim((string) $per->PER_NOM),
                'dni'    => trim((string) $per->PER_NDO),
            ],
            'items'     => array_map(fn ($it) => [
                'codigo'    => $it['rcod'] ?? '',
                'detalle'   => trim((string) ($it['rdes'] ?? '')),
                'marca'     => trim((string) ($it['mdes'] ?? '')),
                'talle'     => trim((string) ($it['tdes'] ?? '')),
                'cantidad'  => (int) ($it['cantidad'] ?? 0),
                'certifica' => (bool) ($it['certifica'] ?? false),
                'fecha'     => $this->fmtFecha((string) ($it['fecha'] ?? '')),
            ], $validos),
            'puestos'   => $puestos,
            'elementos' => $elementos,
            'motivo'    => $motivo,
        ]);
    }
}
