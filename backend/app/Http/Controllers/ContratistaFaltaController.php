<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ContratistaFaltaController — Listado Exigencias y Faltantes (contra_lista_faltas).
 *
 * Lista, por contratista activo, las exigencias legales (faltantes o todas) y los
 * empleados con acceso permitido pero ART vencida.
 */
class ContratistaFaltaController extends Controller
{
    /**
     * @route GET /api/contratistas-faltas?tipo=1|2&contratista=0
     *   tipo 1 = solo obligaciones faltantes; tipo 2 = todas las obligaciones asignadas.
     *   contratista 0 = todos; >0 = uno.
     */
    public function index(Request $request): JsonResponse
    {
        $tipo = (int) $request->input('tipo', 1) === 2 ? 2 : 1;
        $cod  = (int) $request->input('contratista', 0);
        $hoy  = now()->startOfDay();

        $contratistas = DB::table('contnom')->where('CTR_AOP', 'A')
            ->when($cod > 0, fn ($q) => $q->where('CTR_COD', $cod))
            ->orderBy('CTR_NOM')->get(['CTR_COD', 'CTR_NOM']);

        $resultado = [];
        foreach ($contratistas as $c) {
            $exigencias = $this->exigencias((int) $c->CTR_COD, $tipo, $hoy);

            // Empleados con acceso permitido pero ART vencida
            $empleados = DB::table('contemp')->where('CEM_CON', $c->CTR_COD)
                ->where('CEM_ACC', '!=', 'N')->whereDate('CEM_VART', '<', $hoy->toDateString())
                ->orderBy('CEM_NOM')->get(['CEM_NOM', 'CEM_DNI', 'CEM_VART'])
                ->map(fn ($e) => [
                    'nombre'   => trim((string) $e->CEM_NOM),
                    'dni'      => trim((string) $e->CEM_DNI),
                    'venc_art' => \Carbon\Carbon::parse($e->CEM_VART)->format('d/m/Y'),
                ])->values();

            // Solo se incluye el contratista si tiene algo para mostrar
            if (count($exigencias) === 0 && count($empleados) === 0) {
                continue;
            }
            $resultado[] = [
                'contratista' => trim((string) $c->CTR_NOM),
                'exigencias'  => $exigencias,
                'empleados'   => $empleados,
            ];
        }

        $titulo = ($tipo === 1 ? 'Obligaciones Faltantes' : 'Obligaciones Exigidas') . ' al ' . $hoy->format('d/m/Y');
        if ($cod > 0 && $contratistas->count() === 1) {
            $titulo .= ' de ' . mb_strtoupper(trim((string) $contratistas[0]->CTR_NOM));
        }

        return response()->json(['titulo' => $titulo, 'contratistas' => $resultado]);
    }

    /** Exigencias del contratista: faltantes (tipo 1) o todas (tipo 2), con optativas relacionadas resueltas. */
    private function exigencias(int $cod, int $tipo, $hoy): array
    {
        $q = DB::table('contexi as x')->join('exigencias as e', 'x.CEX_EXI', '=', 'e.EXI_COD')
            ->where('x.CEX_CON', $cod);
        if ($tipo === 1) {
            $q->where('x.CEX_TIP', '!=', 'N')
              ->where(fn ($w) => $w->whereDate('x.CEX_VEN', '<', $hoy->toDateString())->orWhere('x.CEX_PRE', 'N'));
        }
        $filas = $q->orderBy('e.EXI_DET')->get([
            'e.EXI_COD', 'e.EXI_DET', 'x.CEX_TIP', 'x.CEX_REL', 'x.CEX_VEN', 'x.CEX_PRE',
        ]);

        $out = [];
        foreach ($filas as $f) {
            // Optativa relacionada cumplimentada: la exigencia relacionada está presentada y vigente
            if ($tipo === 1 && trim((string) $f->CEX_TIP) === 'R' && (int) $f->CEX_REL > 0) {
                $cumple = DB::table('contexi')->where('CEX_CON', $cod)->where('CEX_EXI', (int) $f->CEX_REL)
                    ->whereDate('CEX_VEN', '>=', $hoy->toDateString())->where('CEX_PRE', 'S')->exists();
                if ($cumple) {
                    continue;
                }
            }
            $ven = \Carbon\Carbon::parse($f->CEX_VEN);
            $out[] = [
                'detalle'    => trim((string) $f->EXI_DET),
                'tipo'       => trim((string) $f->CEX_TIP) === 'O' ? 'Obligatoria' : (trim((string) $f->CEX_TIP) === 'R' ? 'Optativa' : 'No obligatoria'),
                'presentada' => trim((string) $f->CEX_PRE) === 'S',
                'vence'      => $ven->year <= 1900 ? '' : $ven->format('d/m/Y'),
            ];
        }
        return $out;
    }
}
