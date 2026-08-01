<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LogActividadController — Consulta del log de actividad (auditoría).
 *
 * Solo administradores. La escritura la hace App\Support\RegistroActividad;
 * acá solo se lee y se purga.
 */
class LogActividadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('log_actividad');

        if ($d = $this->fecha($request->query('desde'))) {
            $q->where('ACT_FEC', '>=', $d->startOfDay());
        }
        if ($h = $this->fecha($request->query('hasta'))) {
            $q->where('ACT_FEC', '<=', $h->endOfDay());
        }
        if (($u = trim((string) $request->query('usuario', ''))) !== '') {
            $q->where('ACT_USU', 'like', "%{$u}%");
        }
        if (($t = trim((string) $request->query('terminal', ''))) !== '') {
            $q->where('ACT_TER', 'like', "%{$t}%");
        }
        if (($m = trim((string) $request->query('modulo', ''))) !== '') {
            $q->where('ACT_MOD', 'like', "%{$m}%");
        }
        if (($op = strtoupper(trim((string) $request->query('operacion', '')))) !== '' && in_array($op, ['INSERT', 'UPDATE', 'DELETE'], true)) {
            $q->where('ACT_OP', $op);
        }
        if (($tx = trim((string) $request->query('q', ''))) !== '') {
            $q->where(function ($w) use ($tx) {
                $w->where('ACT_TXT', 'like', "%{$tx}%")->orWhere('ACT_SQL', 'like', "%{$tx}%");
            });
        }

        $total = (clone $q)->count();
        $limit = min(max((int) $request->query('limit', 300), 1), 2000);

        $rows = $q->orderByDesc('ACT_ID')->limit($limit)->get([
            'ACT_ID as id', 'ACT_FEC as fecha', 'ACT_USU as usuario', 'ACT_TER as terminal',
            'ACT_MOD as modulo', 'ACT_OP as operacion', 'ACT_TXT as texto', 'ACT_SQL as sql',
        ]);

        return response()->json(['rows' => $rows, 'total' => $total, 'mostrados' => $rows->count()]);
    }

    /** Purga actividad anterior a N días (default 180). Con dias=0 borra todo. */
    public function purgar(Request $request): JsonResponse
    {
        $dias = (int) $request->query('dias', 180);
        $q = DB::table('log_actividad');
        if ($dias > 0) {
            $q->where('ACT_FEC', '<', Carbon::today()->subDays($dias));
        }
        return response()->json(['borrados' => $q->delete()]);
    }

    private function fecha($v): ?Carbon
    {
        $s = trim((string) $v);
        if ($s === '') return null;
        try { return Carbon::parse($s); } catch (\Throwable $e) { return null; }
    }
}
