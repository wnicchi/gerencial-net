<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * UsuariosActivosController — Usuarios logueados en el sistema (en vivo).
 *
 * Un usuario está "logueado" mientras tenga al menos un token Sanctum vigente
 * (no expirado) en personal_access_tokens. Al cerrar sesión el token se elimina,
 * por lo que la lista refleja en tiempo real quién entra y sale.
 *
 * Se marca "activo ahora" si su última actividad (last_used_at) es muy reciente.
 */
class UsuariosActivosController extends Controller
{
    /** Minutos para considerar a un usuario "activo ahora" (en uso). */
    private const MIN_ACTIVO = 3;

    /** @route GET /api/usuarios-activos */
    public function index(): JsonResponse
    {
        $ahora  = now();
        $limite = $ahora->copy()->subMinutes(self::MIN_ACTIVO);

        // Tokens vigentes (sin expirar) del modelo Usuario, agrupados por usuario.
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', Usuario::class)
            ->where(function ($q) use ($ahora) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $ahora);
            })
            ->select(
                'tokenable_id',
                DB::raw('COUNT(*) as sesiones'),
                DB::raw('MAX(last_used_at) as ultima_actividad'),
                DB::raw('MIN(created_at) as ingreso')
            )
            ->groupBy('tokenable_id')
            ->get();

        if ($tokens->isEmpty()) {
            return response()->json(['ahora' => $ahora->toIso8601String(), 'usuarios' => []]);
        }

        $usuarios = Usuario::whereIn('CODIGO', $tokens->pluck('tokenable_id'))
            ->get(['CODIGO', 'NOMBRE', 'DATO1', 'NIVEL'])
            ->keyBy('CODIGO');

        $lista = $tokens->map(function ($t) use ($usuarios, $limite) {
            $u = $usuarios->get($t->tokenable_id);
            if (!$u) {
                return null;
            }
            $ultima = $t->ultima_actividad ? Carbon::parse($t->ultima_actividad) : null;
            return [
                'codigo'           => (int) $u->CODIGO,
                'nombre'           => trim((string) $u->NOMBRE),
                'login'            => trim((string) $u->DATO1),
                'sesiones'         => (int) $t->sesiones,
                'ultima_actividad' => $ultima ? $ultima->toIso8601String() : null,
                'ingreso'          => $t->ingreso ? Carbon::parse($t->ingreso)->toIso8601String() : null,
                'activo_ahora'     => $ultima ? $ultima->greaterThanOrEqualTo($limite) : false,
            ];
        })->filter()->sortByDesc(fn ($r) => $r['ultima_actividad'] ?? '')->values();

        return response()->json([
            'ahora'    => $ahora->toIso8601String(),
            'usuarios' => $lista,
        ]);
    }
}
