<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SueldosImportarController — Importar del Estudio (sueldos_importar.scx).
 *
 * Importa una liquidación desde un Excel del estudio contable. El frontend lee el Excel, deja que el
 * operador mapee las columnas (CUIL, Código, Detalle, Cantidad, Haberes, Deducciones) y envía las filas
 * ya resueltas junto al tipo de liquidación, mes, año y fecha de pago.
 *
 * Para cada empleado (identificado por CUIL contra personal.PER_CUI, comparando solo dígitos):
 *  - se BORRA la liquidación previa del mismo tipo + mes + año + CUIL (cabecera LIQUIDAC y sus ítems LIQ_ITE),
 *  - se crea una nueva cabecera LIQUIDAC y un ítem LIQ_ITE por cada renglón del Excel,
 *  - se da de alta el concepto en sueldo_con si el código no existía.
 *
 * Si algún CUIL del Excel no existe en el padrón, NO se importa NADA (igual que el FoxPro original) y se
 * devuelven los CUIL no hallados para corregir el archivo.
 *
 * LIQ_NRO/LIT_NRO no son identity: LIT_NRO es el número de la cabecera padre (LIQUIDAC.LIQ_NRO). El número
 * se asigna acá tomando el máximo vigente + 1.
 */
class SueldosImportarController extends Controller
{
    /** @route POST /api/sueldos-importar */
    public function importar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'tipo'        => 'required|integer|min:1',
            'mes'         => 'required|integer|min:1|max:12',
            'anio'        => 'required|integer|min:1900|max:2999',
            'fechaPago'   => 'nullable|date',
            'rows'                => 'required|array|min:1',
            'rows.*.cuil'         => 'nullable',
            'rows.*.codigo'       => 'nullable',
            'rows.*.detalle'      => 'nullable|string',
            'rows.*.cantidad'     => 'nullable',
            'rows.*.haber'        => 'nullable',
            'rows.*.deduce'       => 'nullable',
        ]);

        $tipo = (int) $d['tipo'];
        $mes  = (int) $d['mes'];
        $anio = (int) $d['anio'];
        $fechaPago = !empty($d['fechaPago']) ? \Carbon\Carbon::parse($d['fechaPago'])->format('Y-m-d') : '1900-01-01';
        $fecLiq = sprintf('%04d-%02d-01', $anio, $mes);

        $tipoDesc = trim((string) (DB::table('sueldo_tip')->where('STI_COD', $tipo)->value('STI_DES') ?? ''));

        // Padrón por CUIL (solo dígitos)
        $personal = [];
        foreach (DB::table('personal')->get(['PER_COD', 'PER_NOM', 'PER_CUI', 'PER_ING', 'PER_EMP', 'PER_CON', 'PER_CAT']) as $p) {
            $k = preg_replace('/\D/', '', (string) $p->PER_CUI);
            if ($k !== '') $personal[ltrim($k, '0') ?: '0'] = $p;
        }

        // Normalizo filas (descarto totalmente vacías) y valido CUIL contra el padrón
        $filas = [];
        $noHallados = [];
        foreach ($d['rows'] as $r) {
            $cuilDig = preg_replace('/\D/', '', (string) ($r['cuil'] ?? ''));
            $det = trim((string) ($r['detalle'] ?? ''));
            $cod = (int) $this->aNumero($r['codigo'] ?? 0);
            $hab = $this->aNumero($r['haber'] ?? 0);
            $ded = $this->aNumero($r['deduce'] ?? 0);
            $can = (int) $this->aNumero($r['cantidad'] ?? 0);
            // Sin ningún dígito en el CUIL no es un renglón de datos: es el encabezado del
            // Excel (la celda trae el nombre de la columna), un separador o un subtotal.
            // Se saltea en silencio; antes se informaba como "(vacío)" y abortaba TODA la importación.
            if ($cuilDig === '') continue;

            $key = ltrim($cuilDig, '0') ?: '0';
            if (!isset($personal[$key])) { $noHallados[] = $cuilDig; continue; }

            $filas[] = ['emp' => $personal[$key], 'cod' => $cod, 'det' => mb_strtoupper($det), 'can' => $can, 'hab' => $hab, 'ded' => $ded];
        }

        if ($noHallados) {
            return response()->json([
                'message'     => 'No se importó nada: hay CUIL del Excel que no existen en el padrón de empleados.',
                'no_hallados' => array_values(array_unique($noHallados)),
            ], 422);
        }
        if (!$filas) {
            return response()->json(['message' => 'El archivo no contiene renglones válidos para importar.'], 422);
        }

        // Agrupo por empleado (PER_COD)
        $porEmpleado = [];
        foreach ($filas as $f) {
            $cod = (int) $f['emp']->PER_COD;
            $porEmpleado[$cod][] = $f;
        }

        $headers = 0; $items = 0; $total = 0.0;
        DB::transaction(function () use ($porEmpleado, $tipo, $tipoDesc, $mes, $anio, $fecLiq, $fechaPago, &$headers, &$items, &$total) {
            $nextNro = (int) max(
                (int) DB::table('LIQUIDAC')->max('LIQ_NRO'),
                (int) DB::table('LIQ_ITE')->max('LIT_NRO')
            ) + 1;

            foreach ($porEmpleado as $cod => $renglones) {
                $emp = $renglones[0]['emp'];
                $cuilFmt = $this->formatoCuil($emp->PER_CUI);

                // Borrar liquidación previa (mismo tipo + mes + año + CUIL) y sus ítems
                $previas = DB::table('LIQUIDAC')
                    ->where('LIQ_TIP', $tipo)->where('LIQ_MES', $mes)->where('LIQ_ANO', $anio)
                    ->where('LIQ_CUI', $cuilFmt)->pluck('LIQ_NRO');
                if ($previas->isNotEmpty()) {
                    DB::table('LIQ_ITE')->whereIn('LIT_NRO', $previas)->delete();
                    DB::table('LIQUIDAC')->whereIn('LIQ_NRO', $previas)->delete();
                }

                $nro = $nextNro++;
                DB::table('LIQUIDAC')->insert(Registro::completar('LIQUIDAC', [
                    'LIQ_NRO' => $nro, 'LIQ_COD' => (int) $emp->PER_COD, 'LIQ_DEP' => trim((string) $emp->PER_NOM),
                    'LIQ_CUI' => $cuilFmt, 'LIQ_ING' => $emp->PER_ING, 'LIQ_EMP' => (int) $emp->PER_EMP,
                    'LIQ_CON' => (int) $emp->PER_CON, 'LIQ_CAT' => (int) $emp->PER_CAT,
                    'LIQ_TIP' => $tipo, 'LIQ_TID' => $tipoDesc, 'LIQ_MES' => $mes, 'LIQ_ANO' => $anio,
                    'LIQ_FEC' => $fecLiq, 'LIQ_PAG' => $fechaPago,
                ]));
                $headers++;

                foreach ($renglones as $r) {
                    DB::table('LIQ_ITE')->insert(Registro::completar('LIQ_ITE', [
                        'LIT_NRO' => $nro, 'LIT_PER' => (int) $emp->PER_COD, 'LIT_MES' => $mes, 'LIT_ANO' => $anio,
                        'LIT_FEC' => $fecLiq, 'LIT_TIP' => $tipo, 'LIT_COD' => $r['cod'], 'LIT_DES' => substr($r['det'], 0, 100),
                        'LIT_CAN' => $r['can'], 'LIT_HAB' => $r['hab'], 'LIT_DED' => $r['ded'],
                    ]));
                    $items++;
                    $total += $r['hab'] - $r['ded'];

                    // Alta de concepto en sueldo_con si no existe
                    if ($r['cod'] > 0 && !DB::table('sueldo_con')->where('SCO_COD', $r['cod'])->exists()) {
                        DB::table('sueldo_con')->insert(Registro::completar('sueldo_con', [
                            'SCO_COD' => $r['cod'], 'SCO_DES' => substr($r['det'], 0, 100),
                        ]));
                    }
                }
            }
        });

        return response()->json([
            'message'       => 'Importación procesada correctamente.',
            'liquidaciones' => $headers,
            'items'         => $items,
            'total'         => round($total, 2),
        ]);
    }

    /** CUIL a formato 99-99999999-9 a partir de los dígitos. */
    private function formatoCuil($cui): string
    {
        $dig = preg_replace('/\D/', '', (string) $cui);
        $dig = str_pad(substr($dig, 0, 11), 11, '0', STR_PAD_LEFT);
        return substr($dig, 0, 2) . '-' . substr($dig, 2, 8) . '-' . substr($dig, 10, 1);
    }

    /** Convierte un valor con formato AR ("1.234,56") o estándar a float. */
    private function aNumero($v): float
    {
        if (is_numeric($v)) return (float) $v;
        $s = preg_replace('/[^\d.,-]/', '', (string) $v);
        if ($s === '' || $s === '-') return 0.0;
        // Si tiene coma decimal (formato AR), saco puntos de miles y paso coma a punto
        if (strpos($s, ',') !== false) $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }
}
