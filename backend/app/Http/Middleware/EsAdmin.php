<?php

/**
 * ============================================================
 * EsAdmin.php — Middleware de Administrador
 * ============================================================
 * Verifica que el usuario autenticado tenga NIVEL = 1
 * (Administrador total del sistema).
 *
 * Se aplica sobre las rutas del grupo /api/admin.
 * Se registra en bootstrap/app.php con el alias 'admin'.
 *
 * Si el usuario no es administrador retorna HTTP 403 Forbidden.
 * Si no está autenticado (sin token), Sanctum retorna HTTP 401
 * antes de que este middleware llegue a ejecutarse.
 *
 * @package  App\Http\Middleware
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EsAdmin
{
    /**
     * Verificar que el usuario autenticado sea Administrador (NIVEL = 1).
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        // Verificar que el usuario sea Administrador total (ES_ADMIN = 1)
        if (!$usuario || (int)$usuario->ES_ADMIN !== 1) {
            return response()->json([
                'message' => 'Acceso denegado. Se requieren privilegios de administrador.',
            ], 403);
        }

        return $next($request);
    }
}
