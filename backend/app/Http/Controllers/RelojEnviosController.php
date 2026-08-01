<?php

namespace App\Http\Controllers;

use App\Support\Registro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RelojEnviosController — Reloj / Envíos Automáticos de Partes Diarios (reloj_envios.scx).
 *
 * ABM de programas de envío automático del parte diario (tabla reloj_envios). Cada programa tiene una
 * descripción, si está activo, los días de la semana habilitados con hasta 3 horarios de envío cada uno,
 * y hasta 5 emails destinatarios. Una segunda solapa asocia empleados al programa (personal.PARTE_ENV /
 * PARTE_PRG).
 *
 * Columnas por día n (1=Lunes … 7=Domingo): rdp_d{n} (activo), rdp_h{n} (1ª hora), rdp_h{n}b (2ª),
 * rdp_h{n}c (3ª); rdp_dt{n} = fecha/hora del último envío (solo lectura). rdp_cod no es identity.
 */
class RelojEnviosController extends Controller
{
    private const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    /** @route GET /api/reloj/envios — lista de programas. */
    public function index(): JsonResponse
    {
        $filas = DB::table('reloj_envios')->orderBy('rdp_cod')->get()
            ->map(fn ($r) => $this->fila($r))->values();
        return response()->json(['envios' => $filas]);
    }

    /** @route GET /api/reloj/envios/{cod} */
    public function show(int $cod): JsonResponse
    {
        $r = DB::table('reloj_envios')->where('rdp_cod', $cod)->first();
        if (!$r) return response()->json(['message' => 'Programa inexistente.'], 404);
        return response()->json($this->fila($r));
    }

    /** @route POST /api/reloj/envios — crea un programa. */
    public function store(Request $request): JsonResponse
    {
        $d = $this->validar($request);
        $cod = (int) DB::table('reloj_envios')->max('rdp_cod') + 1;
        DB::table('reloj_envios')->insert(Registro::completar('reloj_envios', $this->columnas($d) + ['rdp_cod' => $cod]));
        return response()->json(['message' => 'Programa creado correctamente.', 'cod' => $cod], 201);
    }

    /** @route PUT /api/reloj/envios/{cod} */
    public function update(Request $request, int $cod): JsonResponse
    {
        if (!DB::table('reloj_envios')->where('rdp_cod', $cod)->exists()) return response()->json(['message' => 'Programa inexistente.'], 404);
        $d = $this->validar($request);
        DB::table('reloj_envios')->where('rdp_cod', $cod)->update($this->columnas($d));
        return response()->json(['message' => 'Programa actualizado correctamente.']);
    }

    /** @route DELETE /api/reloj/envios/{cod} */
    public function destroy(int $cod): JsonResponse
    {
        $n = DB::table('reloj_envios')->where('rdp_cod', $cod)->delete();
        if (!$n) return response()->json(['message' => 'Programa inexistente.'], 404);
        // Desasociar empleados de ese programa.
        DB::table('personal')->where('PARTE_PRG', $cod)->update(['PARTE_ENV' => 0]);
        return response()->json(['message' => 'Programa eliminado correctamente.']);
    }

    /** @route GET /api/reloj/envios/{cod}/asociados — personal con su tilde de asociación. */
    public function asociados(int $cod): JsonResponse
    {
        $filas = DB::table('personal')->whereDate('PER_BAJ', '1900-01-01')->orderBy('PER_NOM')
            ->get(['PER_COD', 'PER_NOM', 'PER_LEG', 'PER_DOM', 'PARTE_ENV', 'PARTE_PRG'])
            ->map(fn ($p) => [
                'cod' => (int) $p->PER_COD, 'nombre' => trim((string) $p->PER_NOM), 'legajo' => (int) $p->PER_LEG,
                'domicilio' => trim((string) $p->PER_DOM),
                'elegir' => ((int) $p->PARTE_ENV === 1 && (int) $p->PARTE_PRG === $cod),
            ])->values();
        return response()->json(['total' => $filas->count(), 'asociados' => $filas]);
    }

    /** @route POST /api/reloj/envios/{cod}/asociados — graba la asociación de empleados. */
    public function guardarAsociados(Request $request, int $cod): JsonResponse
    {
        $d = $request->validate(['elegidos' => 'present|array', 'elegidos.*' => 'integer']);
        $elegidos = array_map('intval', $d['elegidos']);

        DB::transaction(function () use ($cod, $elegidos) {
            // Quitar los que estaban en este programa y ya no se eligen.
            $q = DB::table('personal')->where('PARTE_PRG', $cod)->where('PARTE_ENV', 1);
            if ($elegidos) $q->whereNotIn('PER_COD', $elegidos);
            $q->update(['PARTE_ENV' => 0]);
            // Asociar los elegidos.
            if ($elegidos) DB::table('personal')->whereIn('PER_COD', $elegidos)->update(['PARTE_ENV' => 1, 'PARTE_PRG' => $cod]);
        });

        return response()->json(['message' => 'Empleados asociados grabados correctamente.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function fila($r): array
    {
        $dias = [];
        for ($n = 1; $n <= 7; $n++) {
            $dias[] = [
                'nombre' => self::DIAS[$n - 1], 'activo' => (bool) $r->{"rdp_d$n"},
                'h1' => (int) round((float) $r->{"rdp_h$n"}), 'h2' => (int) round((float) $r->{"rdp_h{$n}b"}), 'h3' => (int) round((float) $r->{"rdp_h{$n}c"}),
                'ultimo' => $this->fechaHora($r->{"rdp_dt$n"} ?? null),
            ];
        }
        return [
            'cod' => (int) $r->rdp_cod, 'des' => trim((string) $r->rdp_des), 'act' => (bool) $r->rdp_act,
            'emails' => [trim((string) $r->rdp_em1), trim((string) $r->rdp_em2), trim((string) $r->rdp_em3), trim((string) $r->rdp_em4), trim((string) $r->rdp_em5)],
            'dias' => $dias,
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'des'      => 'required|string|max:100',
            'act'      => 'nullable|boolean',
            'emails'   => 'nullable|array|max:5', 'emails.*' => 'nullable|string|max:100',
            'dias'     => 'required|array|size:7',
            'dias.*.activo' => 'nullable|boolean',
            'dias.*.h1' => 'nullable|integer', 'dias.*.h2' => 'nullable|integer', 'dias.*.h3' => 'nullable|integer',
        ]);
    }

    private function columnas(array $d): array
    {
        $cols = [
            'rdp_des' => trim((string) $d['des']), 'rdp_act' => ($d['act'] ?? false) ? 1 : 0,
        ];
        $em = $d['emails'] ?? [];
        for ($i = 1; $i <= 5; $i++) $cols["rdp_em$i"] = trim((string) ($em[$i - 1] ?? ''));
        for ($n = 1; $n <= 7; $n++) {
            $dia = $d['dias'][$n - 1] ?? [];
            $cols["rdp_d$n"] = ($dia['activo'] ?? false) ? 1 : 0;
            $cols["rdp_h$n"] = (int) ($dia['h1'] ?? 0);
            $cols["rdp_h{$n}b"] = (int) ($dia['h2'] ?? 0);
            $cols["rdp_h{$n}c"] = (int) ($dia['h3'] ?? 0);
        }
        return $cols;
    }

    private function fechaHora($v): string
    {
        if (!$v) return '';
        $c = \Carbon\Carbon::parse($v);
        return $c->year <= 1900 ? '' : $c->format('d/m/Y H:i');
    }
}
