<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CapacitacionController — Jornadas de Capacitación (capacitacion.scx).
 *
 * ABM de jornadas de capacitación. Combos de disertante (disertantes) y área
 * temática (tema). Modalidad y duración son texto libre (con sugerencias de los
 * valores ya cargados). El código se asigna como max+1.
 */
class CapacitacionController extends Controller
{
    /** @route GET /api/capacitaciones/init — combos (disertantes, temas, modalidades, duraciones). */
    public function init(): JsonResponse
    {
        return response()->json([
            'disertantes' => DB::table('disertantes')->orderBy('dis_nom')->get(['dis_cod', 'dis_nom'])
                ->map(fn ($d) => ['cod' => (int) $d->dis_cod, 'nombre' => trim((string) $d->dis_nom)])->values(),
            'temas' => DB::table('tema')->orderBy('TEM_DES')->get(['TEM_COD', 'TEM_DES'])
                ->map(fn ($t) => ['cod' => (int) $t->TEM_COD, 'nombre' => trim((string) $t->TEM_DES)])->values(),
            'modalidades' => DB::table('capacitacion')->whereRaw("LTRIM(RTRIM(CAP_MODALIDAD)) <> ''")
                ->distinct()->orderBy('CAP_MODALIDAD')->pluck('CAP_MODALIDAD')
                ->map(fn ($x) => trim((string) $x))->filter()->unique()->values(),
            'duraciones' => DB::table('capacitacion')->whereRaw("LTRIM(RTRIM(CAP_DURA)) <> ''")
                ->distinct()->orderBy('CAP_DURA')->pluck('CAP_DURA')
                ->map(fn ($x) => trim((string) $x))->filter()->unique()->values(),
        ]);
    }

    /** @route GET /api/capacitaciones?buscar= */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('capacitacion');
        if ($b = trim((string) $request->query('buscar', ''))) {
            $q->where(function ($w) use ($b) {
                $w->where('CAP_CAPA', 'like', "%{$b}%")
                  ->orWhere('CAP_DISER_NOM', 'like', "%{$b}%")
                  ->orWhere('CAP_TEM_DET', 'like', "%{$b}%");
            });
        }
        return response()->json($q->orderBy('CAP_CAPA')->get()->map(fn ($r) => $this->fila($r))->values());
    }

    /** @route POST /api/capacitaciones */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('capacitacion')->max('CAP_COD') + 1;
        DB::table('capacitacion')->insert(Registro::completar('capacitacion', $this->datos($d, $cod)));
        return response()->json(['ok' => true, 'cod' => $cod], 201);
    }

    /** @route PUT /api/capacitaciones/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('capacitacion')->where('CAP_COD', $cod)->exists()) {
            return response()->json(['message' => 'Capacitación inexistente.'], 404);
        }
        $d = $this->validar($request);
        DB::table('capacitacion')->where('CAP_COD', $cod)->update($this->datos($d, $cod));
        return response()->json(['ok' => true]);
    }

    /** @route DELETE /api/capacitaciones/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        DB::table('capacitacion')->where('CAP_COD', $cod)->delete();
        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'capacitacion' => 'required|string|max:100',
            'fecha'        => 'nullable|date',
            'desde'        => 'nullable|date',
            'hasta'        => 'nullable|date',
            'disertante_cod' => 'nullable|integer',
            'modalidad'    => 'nullable|string|max:40',
            'duracion'     => 'nullable|string|max:40',
            'objetivo'     => 'nullable|string|max:200',
            'tema_cod'     => 'nullable|integer',
            'cerrada'      => 'boolean',
        ]);
    }

    private function datos(array $d, int $cod): array
    {
        $disNom = '';
        if ((int) ($d['disertante_cod'] ?? 0) > 0) {
            $disNom = trim((string) DB::table('disertantes')->where('dis_cod', (int) $d['disertante_cod'])->value('dis_nom'));
        }
        $temDet = '';
        if ((int) ($d['tema_cod'] ?? 0) > 0) {
            $temDet = trim((string) DB::table('tema')->where('TEM_COD', (int) $d['tema_cod'])->value('TEM_DES'));
        }
        return [
            'CAP_COD'       => $cod,
            'CAP_CAPA'      => mb_substr(trim($d['capacitacion']), 0, 100),
            'CAP_FEC'       => !empty($d['fecha']) ? substr((string) $d['fecha'], 0, 10) : '1900-01-01',
            'CAP_PER_INI'   => !empty($d['desde']) ? substr((string) $d['desde'], 0, 10) : '1900-01-01',
            'CAP_PER_FIN'   => !empty($d['hasta']) ? substr((string) $d['hasta'], 0, 10) : '1900-01-01',
            'CAP_DISER_COD' => (int) ($d['disertante_cod'] ?? 0),
            'CAP_DISER_NOM' => $disNom,
            'CAP_MODALIDAD' => mb_substr(trim((string) ($d['modalidad'] ?? '')), 0, 40),
            'CAP_DURA'      => mb_substr(trim((string) ($d['duracion'] ?? '')), 0, 40),
            'CAP_OBJE'      => mb_substr(trim((string) ($d['objetivo'] ?? '')), 0, 200),
            'CAP_TEM_COD'   => (int) ($d['tema_cod'] ?? 0),
            'CAP_TEM_DET'   => $temDet,
            'CAP_CERRADA'   => (int) ($d['cerrada'] ?? false),
        ];
    }

    private function fila(object $r): array
    {
        $fecha = fn ($v) => ($v && substr((string) $v, 0, 4) !== '1900') ? substr((string) $v, 0, 10) : '';
        return [
            'cod'            => (int) $r->CAP_COD,
            'capacitacion'   => trim((string) $r->CAP_CAPA),
            'fecha'          => $fecha($r->CAP_FEC),
            'desde'          => $fecha($r->CAP_PER_INI),
            'hasta'          => $fecha($r->CAP_PER_FIN),
            'disertante_cod' => (int) $r->CAP_DISER_COD,
            'disertante'     => trim((string) $r->CAP_DISER_NOM),
            'modalidad'      => trim((string) $r->CAP_MODALIDAD),
            'duracion'       => trim((string) $r->CAP_DURA),
            'objetivo'       => trim((string) $r->CAP_OBJE),
            'tema_cod'       => (int) $r->CAP_TEM_COD,
            'tematica'       => trim((string) $r->CAP_TEM_DET),
            'cerrada'        => (bool) $r->CAP_CERRADA,
        ];
    }
}
