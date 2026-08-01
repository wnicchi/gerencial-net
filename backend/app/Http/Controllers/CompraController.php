<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CompraController — Consultar Compras. Lee de la base de Gestión (sqlSILCAR)
 * las compras de proveedores marcados como RRHH, y marca en amarillo las que
 * tienen documentación digital (DOCUMENTOS_DIGITALES.BIBLIOTECA_DIGITAL, proceso
 * que contiene 'COMPRAS'). Permite ver el detalle de una compra y su documento.
 */
class CompraController extends Controller
{
    /** @route GET /api/compras?mes=&anio=&proveedor=&nro= */
    public function index(Request $request): JsonResponse
    {
        try {
            $q = DB::connection('gestion')->table('compras as a')
                ->join('proveedo as b', 'a.COM_PVR', '=', 'b.PVR_COD')
                ->where('b.PVR_RRHH', 1);

            if ((int) $request->input('mes', 0) > 0)  $q->where('a.COM_IME', (int) $request->mes);
            if ((int) $request->input('anio', 0) > 0) $q->where('a.COM_IAN', (int) $request->anio);
            if ((int) $request->input('nro', 0) > 0)  $q->where('a.COM_NRO', (int) $request->nro);
            if (trim((string) $request->input('proveedor', '')) !== '') {
                $q->whereRaw('UPPER(a.COM_DPV) LIKE ?', ['%' . strtoupper(trim($request->proveedor)) . '%']);
            }

            $rows = $q->orderByDesc('a.COM_NRO')->get([
                'a.COM_NRO as nro',
                DB::raw('LTRIM(RTRIM(a.COM_DPV)) as proveedor'),
                'a.COM_FEC as fecha',
                DB::raw('LTRIM(RTRIM(a.COM_TFA)) as tipo'),
                DB::raw('LTRIM(RTRIM(a.COM_NFA)) as comprobante'),
                DB::raw('LTRIM(RTRIM(a.COM_NDE)) as despacho'),
                'a.COM_NET as importe',
                DB::raw('LTRIM(RTRIM(a.COM_ASN)) as asn'),
                DB::raw('LTRIM(RTRIM(a.COM_AUN)) as aun'),
            ]);

            // Compras con documentación digital
            $conDoc = $this->comprasConDocumento();

            $out = $rows->map(function ($r) use ($conDoc) {
                $comp = trim((string) $r->despacho) !== '' ? $r->despacho : $r->comprobante;
                return [
                    'nro'         => (int) $r->nro,
                    'proveedor'   => $r->proveedor,
                    'fecha'       => $this->dc($r->fecha),
                    'tipo'        => $r->tipo,
                    'comprobante' => $comp,
                    'importe'     => (float) $r->importe,
                    'con_doc'     => isset($conDoc[(int) $r->nro]),
                    'pendiente'   => strtoupper(trim((string) $r->asn)) !== 'S' && strtoupper(trim((string) $r->aun)) === 'S',
                ];
            });

            return response()->json(['rows' => $out->values(), 'total' => $out->count()]);
        } catch (\Throwable $e) {
            \Log::warning('[compras] ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo leer las compras del sistema de Gestión.'], 502);
        }
    }

    /** @route GET /api/compras/{nro}/detalle  — arma el detalle de texto. */
    public function detalle(int $nro): JsonResponse
    {
        try {
            $c = DB::connection('gestion')->table('compras')->where('COM_NRO', $nro)->first();
            if (!$c) return response()->json(['error' => 'Compra no encontrada.'], 404);

            $L = [];
            $m = fn ($v) => number_format((float) $v, 2, ',', '.');

            $L[] = '     Proveedor : ' . (int) $c->COM_PVR . ' - ' . trim((string) $c->COM_DPV) . ' (CUIT ' . trim((string) $c->COM_CUI) . ')';
            $L[] = '         Fecha : ' . $this->dc($c->COM_FEC);
            $L[] = '       IVA Mes : ' . (int) $c->COM_IME . '/' . (int) $c->COM_IAN;
            $L[] = '  Tipo Comprob.: ' . trim((string) $c->COM_TFA);
            $L[] = trim((string) $c->COM_NDE) !== ''
                ? '  Nro.Despacho : ' . trim((string) $c->COM_NDE)
                : '   Nro.Comprob.: ' . trim((string) $c->COM_NFA);

            if (strtoupper(trim((string) $c->COM_CVE)) === 'C') {
                $L[] = '   Cond.Compra : Contado';
            } elseif (!in_array(trim((string) $c->COM_TFA), ['NC A', 'NC B', 'NC C'], true)) {
                $ven = array_filter([(int) $c->COM_VE1, (int) $c->COM_VE2, (int) $c->COM_VE3, (int) $c->COM_VE4]);
                if ($ven) $L[] = '   Cond.Compra : ' . implode(' / ', $ven) . ' Días';
            }

            $L[] = '';
            $L[] = ' Importe Bruto : ' . $m($c->COM_BRU);
            foreach ([
                'COM_IIN' => '   IVA 21.00 %', 'COM_I10' => '   IVA 10.50 %', 'COM_I27' => '   IVA 27.00 %',
                'COM_I05' => '   IVA  5.00 %', 'COM_I25' => '   IVA  2.50 %', 'COM_INO' => '    No Gravado',
            ] as $col => $lbl) {
                if (isset($c->$col) && (float) $c->$col != 0) $L[] = "$lbl : " . $m($c->$col);
            }

            // Retenciones
            foreach (DB::connection('gestion')->table('com_rete')->where('RET_COM', $nro)->get(['RET_DES', 'RET_IMP']) as $r) {
                $L[] = '  ' . mb_substr(trim((string) $r->RET_DES), 0, 14) . ' : ' . $m($r->RET_IMP);
            }

            $L[] = '                 -----------';
            $L[] = ' Importe Total : ' . $m($c->COM_NET);
            $L[] = '';
            if ((int) $c->COM_AUT > 0) $L[] = '       Interno : ' . (int) $c->COM_AUT;
            $L[] = '  Rubro Compra : ' . (int) $c->COM_RUB . ' - ' . trim((string) $c->COM_RUD);
            if (trim((string) ($c->COM_MON ?? '')) !== '') {
                $L[] = '        Moneda : ' . trim((string) $c->COM_MON) . '    Cot Dolar ' . $m($c->COM_COTDOL);
            }
            if ((int) ($c->COS_TABCOD ?? 0) > 0) $L[] = ' Tabla Distrib : ' . (int) $c->COS_TABCOD . ' - ' . trim((string) $c->COS_TABNOM);
            if ((int) ($c->COM_COSMES ?? 0) != 0 || (int) ($c->COM_COSANO ?? 0) != 0) {
                $L[] = ' Periodo Costo : ' . (int) $c->COM_COSMES . '/' . (int) $c->COM_COSANO;
            }
            if ((int) $c->COM_LTS > 0) {
                $L[] = '       Dominio : ' . trim((string) $c->COM_DOM) . '   /   ' . (int) $c->COM_LTS . ' Litros   /   ' . trim((string) $c->COM_PED);
            }
            if (trim((string) ($c->COM_IMF ?? '')) !== '') $L[] = '        Visado : ' . trim((string) $c->COM_IMF);
            $L[] = '       Usuario : ' . (int) $c->COM_USU . '       Cargado : ' . $this->dc($c->COM_CAR);

            // Autorización
            if (strtoupper(trim((string) $c->COM_AUN)) === 'S') {
                $L[] = 'Requiere Autor : Si';
                $L[] = strtoupper(trim((string) $c->COM_ASN)) === 'S'
                    ? '    Autorizado : ' . trim((string) $c->COM_AUS)
                    : '  Autorización : Pendiente';
            } elseif (trim((string) $c->COM_AUS) !== '') {
                $L[] = '                 ' . trim((string) $c->COM_AUS);
            }

            // Centro de costo
            $cc = DB::connection('gestion')->table('CCOSTO_ITEM')->where('CCO_NRO', $nro)->where('CCO_TIP', 'C')
                ->get(['CCO_COD', 'CCO_DES', 'CCO_POR', 'CCO_IMP']);
            if ($cc->count()) {
                $L[] = '';
                $L[] = '            Centro Costo                 Porc        Importe';
                $L[] = '            ----------------------------------  -------  ----------';
                $tp = 0; $ti = 0;
                foreach ($cc as $x) {
                    $tp += (float) $x->CCO_POR; $ti += (float) $x->CCO_IMP;
                    $L[] = '            ' . str_pad((string) (int) $x->CCO_COD, 4) . '  '
                        . str_pad(mb_substr(trim((string) $x->CCO_DES), 0, 30), 30) . '  '
                        . str_pad($m($x->CCO_POR) . '%', 8, ' ', STR_PAD_LEFT) . '  '
                        . str_pad($m($x->CCO_IMP), 12, ' ', STR_PAD_LEFT);
                }
                $L[] = '            ----------------------------------  -------  ----------';
                $L[] = '            ' . str_pad($m($tp) . '%', 46, ' ', STR_PAD_LEFT) . '  ' . str_pad($m($ti), 12, ' ', STR_PAD_LEFT);
            }

            // Pagos relacionados
            $pg = DB::connection('gestion')->table('CTA_PAGO')->where('CTA_NRO', $nro)->get(['CTA_OPA', 'CTA_IMP']);
            if ($pg->count()) {
                $L[] = '';
                $L[] = '                    Ord.Pago        Importe';
                $L[] = '                    -----------------------';
                foreach ($pg as $p) {
                    $L[] = '                    ' . str_pad((string) (int) $p->CTA_OPA, 8) . '  ' . str_pad($m($p->CTA_IMP), 14, ' ', STR_PAD_LEFT);
                }
                $L[] = '                    -----------------------';
            }

            if ((int) $c->COM_PAG > 0) $L[] = '    Orden Pago : ' . (int) $c->COM_PAG;
            $L[] = ' Folio IVA Compras : ' . (int) $c->COM_FOLIO;
            if ((float) ($c->COM_TOTDOLAR ?? 0) > 0) {
                $L[] = '';
                $L[] = 'VALOR EN DOLARES DE ESTA COMPRA = U$S ' . $m($c->COM_TOTDOLAR);
            }

            return response()->json(['titulo' => 'CONSULTA DE COMPRA Nº ' . $nro, 'lineas' => $L]);
        } catch (\Throwable $e) {
            \Log::warning('[compras detalle] ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo leer el detalle de la compra.'], 502);
        }
    }

    /** @route GET /api/compras/{nro}/documento  — devuelve el archivo digital. */
    public function documento(int $nro)
    {
        try {
            $doc = DB::connection('documentos')->table('BIBLIOTECA_DIGITAL')
                ->where('nombre_proceso', 'like', '%COMPRAS%')
                ->whereRaw('LTRIM(RTRIM(Identificacion_del_Archivo)) = ?', [(string) $nro])
                ->first(['archivo_binario', 'extension']);

            if (!$doc || empty($doc->archivo_binario)) {
                return response()->json(['error' => 'La compra no tiene documentación digital.'], 404);
            }

            // El binario crudo; el driver ODBC lo convierte a UTF-8 al leer → revertir a CP1252.
            $bin = is_resource($doc->archivo_binario)
                ? stream_get_contents($doc->archivo_binario)
                : $doc->archivo_binario;
            $bin = mb_convert_encoding($bin, 'CP1252', 'UTF-8');

            $ext = strtolower(trim((string) $doc->extension)) ?: 'pdf';
            return response($bin, 200)
                ->header('Content-Type', $this->mime($ext))
                ->header('Content-Disposition', 'inline; filename="Compra_' . $nro . '.' . $ext . '"');
        } catch (\Throwable $e) {
            \Log::warning('[compras documento] ' . $e->getMessage());
            return response()->json(['error' => 'No se pudo recuperar el documento.'], 502);
        }
    }

    /** Set de números de compra que tienen documentación digital. */
    private function comprasConDocumento(): array
    {
        try {
            $set = [];
            $filas = DB::connection('documentos')->table('BIBLIOTECA_DIGITAL')
                ->where('nombre_proceso', 'like', '%COMPRAS%')
                ->whereRaw('ISNUMERIC(LTRIM(RTRIM(Identificacion_del_Archivo))) = 1')
                ->get([DB::raw('CAST(LTRIM(RTRIM(Identificacion_del_Archivo)) AS INT) as nro')]);
            foreach ($filas as $r) {
                $set[(int) $r->nro] = true;
            }
            return $set;
        } catch (\Throwable $e) {
            \Log::warning('[compras docs] ' . $e->getMessage());
            return [];
        }
    }

    private function dc($v): string
    {
        $s = substr((string) $v, 0, 10);
        if ($s === '' || $s === '1900-01-01') return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $x)) return "{$x[3]}/{$x[2]}/{$x[1]}";
        return $s;
    }

    private function mime(string $ext): string
    {
        return [
            'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif', 'tif' => 'image/tiff', 'tiff' => 'image/tiff',
        ][$ext] ?? 'application/octet-stream';
    }
}
