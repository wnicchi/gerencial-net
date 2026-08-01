<?php

namespace App\Http\Middleware;

use App\Models\UsuarioPermiso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerificarPermisoModulo — Autorización por módulo en el backend.
 *
 * Refleja en el servidor la lista blanca de permisos que el frontend ya usa para
 * mostrar/ocultar el menú (misma semántica que el store `auth.puedoVer`):
 *   - Usuario admin (ES_ADMIN) → acceso total.
 *   - Usuario sin claves asignadas (lista vacía) → sin restricciones (ve todo).
 *   - Resto → necesita tener la clave del módulo.
 *
 * El mapeo prefijo-de-ruta → clave de menú vive en config/permisos.php. Las rutas
 * NO mapeadas quedan permitidas (fail-open), de modo que poblar el mapa es seguro e
 * incremental y nunca deja usuarios afuera de rutas todavía sin mapear.
 *
 * Como la clave usada acá es la misma `id` de menu.ts que el frontend usa para
 * ocultar el ítem, un usuario restringido nunca llega por navegación a una ruta que
 * no puede ver: esto sólo bloquea el acceso directo por API.
 */
class VerificarPermisoModulo
{
    /** Cache por request de las claves del usuario (evita reconsultar en cada middleware). */
    private static array $clavesCache = [];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request); // auth:sanctum ya se encarga de exigir sesión
        }

        // Admin: acceso total.
        if ((int) ($user->ES_ADMIN ?? 0) === 1) {
            return $next($request);
        }

        // ¿La ruta actual está mapeada a una (o varias) claves de módulo?
        $requerido = $this->claveDeRuta($request->path());
        if ($requerido === null) {
            return $next($request); // ruta no mapeada → permitida
        }

        // Claves del usuario (vacío = sin restricciones).
        $codigo = (int) ($user->CODIGO ?? 0);
        if (!isset(self::$clavesCache[$codigo])) {
            self::$clavesCache[$codigo] = UsuarioPermiso::clavesDeUsuario($codigo);
        }
        $claves = self::$clavesCache[$codigo];

        // El valor mapeado puede ser una clave (string) o varias (array = any-of,
        // para prefijos compartidos por varios ítems de menú).
        $requeridas = (array) $requerido;
        if (count($claves) === 0 || count(array_intersect($requeridas, $claves)) > 0) {
            return $next($request);
        }

        return response()->json(['message' => 'No tiene permiso para acceder a este módulo.'], 403);
    }

    /**
     * Devuelve la(s) clave(s) de menú asociada(s) a la ruta (prefijo más largo que
     * matchee) — string, array (any-of) o null si la ruta no está mapeada.
     *
     * @return string|array<string>|null
     */
    private function claveDeRuta(string $path): string|array|null
    {
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }
        $mapa = config('permisos.rutas', []);

        $mejor = null;
        $largo = -1;
        foreach ($mapa as $prefijo => $clave) {
            if (($path === $prefijo || str_starts_with($path, $prefijo . '/')) && strlen($prefijo) > $largo) {
                $mejor = $clave;
                $largo = strlen($prefijo);
            }
        }
        return $mejor;
    }
}
