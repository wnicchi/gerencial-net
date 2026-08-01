<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CapacitacionResultadoController — Resultados de la capacitación
 * (capacitacion_resultados.scx).
 *
 * Sobre los cursantes (cap_emp) de una jornada permite: aplicar resultado
 * (disertante, duración, modalidad y eficacia) a los seleccionados, resetear ese
 * estado, marcar "no participó", dar por finalizada la jornada y generar la
 * planilla de asistencia. eficacia: EFI_COD/EFI_DES.
 */
class CapacitacionResultadoController extends Controller
{
    /** @route GET /api/cap-resultado/init — combos (eficacias, disertantes, modalidades, duraciones). */
    public function init(): JsonResponse
    {
        return response()->json([
            'eficacias'   => DB::table('eficacia')->orderBy('EFI_DES')->get(['EFI_COD', 'EFI_DES'])
                ->map(fn ($e) => ['cod' => (int) $e->EFI_COD, 'nombre' => trim((string) $e->EFI_DES)])->values(),
            'disertantes' => DB::table('disertantes')->orderBy('dis_nom')->get(['dis_cod', 'dis_nom'])
                ->map(fn ($d) => ['cod' => (int) $d->dis_cod, 'nombre' => trim((string) $d->dis_nom)])->values(),
            'modalidades' => DB::table('capacitacion')->whereRaw("LTRIM(RTRIM(CAP_MODALIDAD)) <> ''")
                ->distinct()->orderBy('CAP_MODALIDAD')->pluck('CAP_MODALIDAD')->map(fn ($x) => trim((string) $x))->filter()->unique()->values(),
            'duraciones'  => DB::table('capacitacion')->whereRaw("LTRIM(RTRIM(CAP_DURA)) <> ''")
                ->distinct()->orderBy('CAP_DURA')->pluck('CAP_DURA')->map(fn ($x) => trim((string) $x))->filter()->unique()->values(),
        ]);
    }

    /** @route GET /api/cap-resultado/capacitacion/{cod} — cabecera + empresa + alumnos. */
    public function capacitacion(int $cod): JsonResponse
    {
        $c = DB::table('capacitacion')->where('CAP_COD', $cod)->first();
        if (!$c) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        $emp = DB::table('empresas')->orderBy('EMP_COD')->first();

        return response()->json([
            'cabecera' => [
                'cod'            => (int) $c->CAP_COD,
                'capacitacion'   => trim((string) $c->CAP_CAPA),
                'fecha'          => $fecha($c->CAP_FEC),
                'desde'          => $fecha($c->CAP_PER_INI),
                'hasta'          => $fecha($c->CAP_PER_FIN),
                'disertante'     => trim((string) $c->CAP_DISER_NOM),
                'disertante_cod' => (int) $c->CAP_DISER_COD,
                'duracion'       => trim((string) $c->CAP_DURA),
                'modalidad'      => trim((string) $c->CAP_MODALIDAD),
                'objetivo'       => trim((string) $c->CAP_OBJE),
                'tematica'       => trim((string) $c->CAP_TEM_DET),
                'cerrada'        => (bool) $c->CAP_CERRADA,
            ],
            'empresa' => [
                'razon' => trim((string) ($emp->EMP_NOM ?? '')),
                'cuit'  => trim((string) ($emp->EMP_CUI ?? '')),
            ],
            'alumnos' => $this->alumnos($cod),
        ]);
    }

    /** @route POST /api/cap-resultado/{cod}/resetear — limpia disertante/duración/modalidad/eficacia. */
    public function resetear(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['empleados' => 'required|array|min:1', 'empleados.*' => 'integer']);
        DB::table('cap_emp')->where('CAP_COD', $cod)->whereIn('PER_COD', array_map('intval', $d['empleados']))
            ->update(['DISER_COD' => 0, 'DISER_NOM' => '', 'DURACION' => '', 'MODALIDAD' => '', 'EFI_COD' => 0]);
        return response()->json(['ok' => true, 'alumnos' => $this->alumnos($cod)]);
    }

    /** @route POST /api/cap-resultado/{cod}/confirmar — aplica resultado a los seleccionados. */
    public function confirmar(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate([
            'empleados'          => 'required|array|min:1',
            'empleados.*.cod'    => 'required|integer',
            'empleados.*.no_participo' => 'boolean',
            'disertante_cod'     => 'nullable|integer',
            'duracion'           => 'nullable|string|max:40',
            'modalidad'          => 'nullable|string|max:40',
            'efi_cod'            => 'nullable|integer',
        ]);
        $disNom = '';
        if ((int) ($d['disertante_cod'] ?? 0) > 0) {
            $disNom = mb_strtoupper(trim((string) DB::table('disertantes')->where('dis_cod', (int) $d['disertante_cod'])->value('dis_nom')));
        }
        $base = [
            'DISER_COD' => (int) ($d['disertante_cod'] ?? 0),
            'DISER_NOM' => $disNom,
            'DURACION'  => mb_substr(trim((string) ($d['duracion'] ?? '')), 0, 40),
            'MODALIDAD' => mb_substr(trim((string) ($d['modalidad'] ?? '')), 0, 40),
            'EFI_COD'   => (int) ($d['efi_cod'] ?? 0),
        ];
        DB::transaction(function () use ($cod, $d, $base) {
            foreach ($d['empleados'] as $e) {
                DB::table('cap_emp')->where('CAP_COD', $cod)->where('PER_COD', (int) $e['cod'])
                    ->update($base + ['NO_PARTICIPO' => (int) ($e['no_participo'] ?? false)]);
            }
        });
        return response()->json(['ok' => true, 'alumnos' => $this->alumnos($cod)]);
    }

    /** @route POST /api/cap-resultado/{cod}/finalizar — marca la jornada como cerrada. */
    public function finalizar(int $cod): JsonResponse
    {
        if (!DB::table('capacitacion')->where('CAP_COD', $cod)->exists()) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }
        DB::table('capacitacion')->where('CAP_COD', $cod)->update(['CAP_CERRADA' => 1]);
        return response()->json(['ok' => true]);
    }

    /** Alumnos (cap_emp join personal, eficacia desnormalizada). */
    private function alumnos(int $cod): array
    {
        return DB::table('cap_emp as ce')
            ->join('personal as p', 'ce.PER_COD', '=', 'p.PER_COD')
            ->leftJoin('eficacia as ef', 'ce.EFI_COD', '=', 'ef.EFI_COD')
            ->where('ce.CAP_COD', $cod)
            ->orderBy('p.PER_NOM')->orderBy('p.PER_COD')
            ->get(['p.PER_COD', 'p.PER_NOM', 'p.PER_CUI', 'p.PER_TEL', 'p.PER_CEL', 'p.PER_AOP',
                'ce.EFI_COD', 'ef.EFI_DES', 'ce.NO_PARTICIPO', 'ce.DISER_NOM'])
            ->map(fn ($r) => [
                'codigo'      => (int) $r->PER_COD,
                'nombre'      => trim((string) $r->PER_NOM),
                'cuil'        => trim((string) $r->PER_CUI),
                'telefono'    => trim(trim((string) $r->PER_TEL) . ' ' . trim((string) $r->PER_CEL)),
                'activo'      => trim((string) $r->PER_AOP) === 'A',
                'efi_cod'     => (int) $r->EFI_COD,
                'eficacia'    => trim((string) ($r->EFI_DES ?? '')),
                'no_participo' => (bool) $r->NO_PARTICIPO,
                'disertante'  => trim((string) $r->DISER_NOM),
            ])->values()->all();
    }
}
