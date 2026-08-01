<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AccesoEmpresaController — Listado de Accesos a la Empresa (contra_accesos).
 *
 * Para cada contratista ACTIVO se determina si está "en falta" (tiene exigencias
 * legales vencidas o no presentadas). Un empleado tiene acceso PERMITIDO solo si
 * tiene el acceso habilitado (CEM_ACC='S') Y su contratista NO está en falta.
 */
class AccesoEmpresaController extends Controller
{
    /** @route GET /api/accesos-empresa?contratista=  (vacío = todos) */
    public function index(Request $request): JsonResponse
    {
        $cod = (int) $request->input('contratista', 0);
        $hoy = now()->startOfDay();

        $contratistas = DB::table('contnom')->where('CTR_AOP', 'A')
            ->when($cod > 0, fn ($q) => $q->where('CTR_COD', $cod))
            ->orderBy('CTR_NOM')->get(['CTR_COD', 'CTR_NOM']);

        $filas = [];
        foreach ($contratistas as $c) {
            $enFalta = $this->contratistaEnFalta((int) $c->CTR_COD, $hoy);

            $emps = DB::table('contemp')->where('CEM_CON', $c->CTR_COD)
                ->orderBy('CEM_NOM')->get(['CEM_NOM', 'CEM_DNI', 'CEM_ACC']);

            foreach ($emps as $e) {
                $permitido = trim((string) $e->CEM_ACC) === 'S' && !$enFalta;
                $filas[] = [
                    'contratista' => trim((string) $c->CTR_NOM),
                    'nombre'      => trim((string) $e->CEM_NOM),
                    'dni'         => trim((string) $e->CEM_DNI),
                    'acceso'      => $permitido ? 'PERMITIDO' : 'DENEGADO',
                ];
            }
        }

        // Orden final por contratista, nombre (como el informe original)
        usort($filas, fn ($a, $b) => [$a['contratista'], $a['nombre']] <=> [$b['contratista'], $b['nombre']]);

        return response()->json($filas);
    }

    /**
     * Un contratista está "en falta" si le queda al menos una exigencia obligatoria
     * u optativa (CEX_TIP != 'N') vencida o no presentada, salvo que una optativa
     * relacionada (CEX_REL) esté presentada y vigente, que la cumplimenta.
     */
    private function contratistaEnFalta(int $cod, $hoy): bool
    {
        // Exigencias del contratista que están en falta (vencidas o no presentadas)
        $faltantes = DB::table('contexi as x')
            ->join('exigencias as e', 'x.CEX_EXI', '=', 'e.EXI_COD')
            ->where('x.CEX_CON', $cod)
            ->where('x.CEX_TIP', '!=', 'N')
            ->where(fn ($q) => $q->whereDate('x.CEX_VEN', '<', $hoy->toDateString())->orWhere('x.CEX_PRE', 'N'))
            ->get(['x.CEX_EXI', 'x.CEX_TIP', 'x.CEX_REL']);

        $cuenta = 0;
        foreach ($faltantes as $f) {
            // Optativa relacionada: si la exigencia relacionada está presentada y vigente, queda cumplimentada
            if (trim((string) $f->CEX_TIP) === 'R' && (int) $f->CEX_REL > 0) {
                $cumple = DB::table('contexi')
                    ->where('CEX_CON', $cod)
                    ->where('CEX_EXI', (int) $f->CEX_REL)
                    ->whereDate('CEX_VEN', '>=', $hoy->toDateString())
                    ->where('CEX_PRE', 'S')
                    ->exists();
                if ($cumple) {
                    continue; // cumplimentada, no cuenta como falta
                }
            }
            $cuenta++;
        }

        return $cuenta > 0;
    }
}
