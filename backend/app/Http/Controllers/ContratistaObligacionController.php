<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ContratistaObligacionController — Presentación de Obligaciones (contra_obligaciones).
 *
 * Por contratista, permite registrar la presentación y vencimiento de cada exigencia
 * legal asignada (contexi) y administrar el acceso de cada empleado asociado (contemp).
 */
class ContratistaObligacionController extends Controller
{
    /** @route GET /api/contratistas-externos/{cod}/obligaciones */
    public function index(int $cod): JsonResponse
    {
        $hoy = now()->startOfDay();

        $obligaciones = DB::table('contexi as x')->join('exigencias as e', 'x.CEX_EXI', '=', 'e.EXI_COD')
            ->where('x.CEX_CON', $cod)->orderBy('e.EXI_DET')
            ->get()->map(fn ($x) => [
                'codigo'     => (int) $x->EXI_COD,
                'detalle'    => trim((string) $x->EXI_DET),
                'presentada' => trim((string) $x->CEX_PRE) === 'S',
                'fecha_pres' => $this->fecha($x->CEX_FPR),
                'vence'      => $this->fecha($x->CEX_VEN),
                'tipo'       => trim((string) $x->CEX_TIP) ?: 'O',
                'relativo'   => (int) $x->CEX_REL,
                'obs'        => trim((string) $x->CEX_OBS),
                'unica'      => (int) $x->EXI_UNI === 1,
                'vig_meses'  => (int) $x->EXI_MES,
                'art'        => (int) $x->EXI_ART === 1,
            ]);

        // Primer vencimiento del contratista = mínima fecha de vencimiento entre las exigencias presentadas
        $primerVenc = '';
        foreach ($obligaciones as $o) {
            if ($o['presentada'] && $o['vence'] && ($primerVenc === '' || $o['vence'] < $primerVenc)) {
                $primerVenc = $o['vence'];
            }
        }

        // Empleados ocupados en obra sin finalizar
        $ocupados = DB::table('obras_emp as oe')->join('obras as o', 'o.OBR_COD', '=', 'oe.OEM_OBR')
            ->whereDate('o.OBR_FCI', '<=', '1900-01-01')
            ->pluck('oe.OEM_EMP')->map(fn ($v) => (int) $v)->unique()->flip();

        $empleados = DB::table('contemp')->where('CEM_CON', $cod)->orderBy('CEM_NOM')
            ->get()->map(fn ($r) => [
                'unico'    => (int) $r->unico,
                'nombre'   => trim((string) $r->CEM_NOM),
                'dni'      => trim((string) $r->CEM_DNI),
                'cuil'     => (string) (int) $r->CEM_CUI,
                'telefono' => trim((string) $r->CEM_TEL),
                'obs'      => trim((string) $r->CEM_OBS),
                'venc_art' => $this->fecha($r->CEM_VART),
                'acceso'   => trim((string) $r->CEM_ACC) === 'S' ? 'S' : 'N',
                'ocupado'  => isset($ocupados[(int) $r->CEM_COD]),
            ]);

        return response()->json([
            'obligaciones' => $obligaciones,
            'empleados'    => $empleados,
            'primer_venc'  => $primerVenc,
        ]);
    }

    /**
     * Guarda cambios de obligaciones (contexi) y de accesos de empleados (contemp).
     * @route POST /api/contratistas-externos/{cod}/obligaciones
     * body: { obligaciones:[{codigo,presentada,fecha_pres,vence,tipo,relativo,obs}], empleados:[{unico,acceso,venc_art,obs}] }
     */
    public function guardar(Request $request, int $cod): JsonResponse
    {
        $request->validate(['obligaciones' => 'present|array', 'empleados' => 'present|array']);

        DB::transaction(function () use ($request, $cod) {
            foreach ($request->input('obligaciones', []) as $o) {
                DB::table('contexi')
                    ->where('CEX_CON', $cod)->where('CEX_EXI', (int) $o['codigo'])
                    ->update([
                        'CEX_PRE' => !empty($o['presentada']) ? 'S' : 'N',
                        'CEX_FPR' => $this->fechaSql($o['fecha_pres'] ?? ''),
                        'CEX_REL' => (int) ($o['relativo'] ?? 0),
                        'CEX_TIP' => trim((string) ($o['tipo'] ?? 'O')) ?: 'O',
                        'CEX_OBS' => trim((string) ($o['obs'] ?? '')),
                        'CEX_VEN' => $this->fechaSql($o['vence'] ?? ''),
                    ]);
            }

            foreach ($request->input('empleados', []) as $e) {
                if (empty($e['unico'])) {
                    continue;
                }
                DB::table('contemp')->where('unico', (int) $e['unico'])->update([
                    'CEM_ACC'  => trim((string) ($e['acceso'] ?? 'N')) === 'S' ? 'S' : 'N',
                    'CEM_VART' => $this->fechaSql($e['venc_art'] ?? ''),
                    'CEM_OBS'  => trim((string) ($e['obs'] ?? '')),
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function fecha($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('Y-m-d');
    }

    private function fechaSql(string $v): string
    {
        return $v !== '' ? $v . ' 00:00:00' : '1900-01-01 00:00:00';
    }
}
