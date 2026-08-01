<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LogErrorController — Consulta del log centralizado de errores SQL.
 *
 * Solo accesible para administradores (el log contiene comandos SQL / esquema).
 * La escritura la hace App\Support\RegistroError; acá solo se lee y se purga.
 */
class LogErrorController extends Controller
{
    /** Lista errores con filtros: fecha, usuario, módulo y texto libre. */
    public function index(Request $request): JsonResponse
    {
        $q = DB::table('log_errores_sql');

        if ($d = $this->fecha($request->query('desde'))) {
            $q->where('ERR_FEC', '>=', $d->startOfDay());
        }
        if ($h = $this->fecha($request->query('hasta'))) {
            $q->where('ERR_FEC', '<=', $h->endOfDay());
        }
        if (($u = trim((string) $request->query('usuario', ''))) !== '') {
            $q->where('ERR_USU', 'like', "%{$u}%");
        }
        if (($m = trim((string) $request->query('modulo', ''))) !== '') {
            $q->where('ERR_MOD', 'like', "%{$m}%");
        }
        if (($t = trim((string) $request->query('q', ''))) !== '') {
            $q->where(function ($w) use ($t) {
                $w->where('ERR_DET', 'like', "%{$t}%")
                  ->orWhere('ERR_SQL', 'like', "%{$t}%");
            });
        }

        $total = (clone $q)->count();
        $limit = min(max((int) $request->query('limit', 200), 1), 1000);

        $rows = $q->orderByDesc('ERR_ID')->limit($limit)->get([
            'ERR_ID as id', 'ERR_FEC as fecha', 'ERR_TER as terminal',
            'ERR_USU as usuario', 'ERR_MOD as modulo', 'ERR_SQL as sql',
            'ERR_DET as detalle', 'ERR_COD as codigo',
        ]);

        return response()->json(['rows' => $rows, 'total' => $total, 'mostrados' => $rows->count()]);
    }

    /** Purga errores anteriores a N días (default 90). Con dias=0 borra todo. */
    public function purgar(Request $request): JsonResponse
    {
        $dias = (int) $request->query('dias', 90);
        $q = DB::table('log_errores_sql');
        if ($dias > 0) {
            $q->where('ERR_FEC', '<', Carbon::today()->subDays($dias));
        }
        $borrados = $q->delete();

        return response()->json(['borrados' => $borrados]);
    }

    private function fecha($v): ?Carbon
    {
        $s = trim((string) $v);
        if ($s === '') return null;
        try { return Carbon::parse($s); } catch (\Throwable $e) { return null; }
    }
}
