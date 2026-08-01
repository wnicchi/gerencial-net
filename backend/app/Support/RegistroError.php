<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RegistroError — Log centralizado de errores de base de datos.
 *
 * Guarda en la tabla log_errores_sql: fecha/hora, terminal, usuario, módulo,
 * el comando SQL que se intentó ejecutar y el detalle completo del error.
 *
 * Uso automático: enganchado en bootstrap/app.php para toda QueryException no
 * atrapada. Uso manual (en un try/catch propio):
 *
 *     try { ... }
 *     catch (\Throwable $e) { RegistroError::registrar($e, $request, 'MI MODULO'); ... }
 *
 * Nunca lanza: si el logueo falla, se traga el error para no romper el flujo.
 */
class RegistroError
{
    /**
     * Evita la recursión: el logger escribe en `log_errores_sql`; si ESE insert falla,
     * volvería a entrar acá (porque los errores SQL se capturan en la capa de conexión).
     */
    private static bool $registrando = false;

    public static function registrar(Throwable $e, ?Request $request = null, string $modulo = ''): void
    {
        if (self::$registrando) {
            return;
        }
        self::$registrando = true;

        try {
            $request = $request ?? request();

            // Comando SQL (solo si es un error de consulta) + sus bindings.
            $sql = '';
            if ($e instanceof QueryException) {
                try {
                    $sql = (string) $e->getSql();
                    $bind = $e->getBindings();
                    if (!empty($bind)) {
                        $sql .= '  |  bindings: ' . json_encode($bind, JSON_UNESCAPED_UNICODE);
                    }
                } catch (Throwable $x) {
                    $sql = '';
                }
            }

            // Módulo: si no se pasó, se deriva de la ruta (método + path + acción).
            if ($modulo === '' && $request) {
                $accion = optional($request->route())->getActionName();
                $modulo = trim($request->method() . ' ' . $request->path()
                    . ($accion ? "  ($accion)" : ''));
            }

            DB::table('log_errores_sql')->insert([
                'ERR_FEC' => now(),
                'ERR_TER' => substr(self::terminal($request), 0, 60),
                'ERR_USU' => substr(self::usuario($request), 0, 60),
                'ERR_MOD' => substr($modulo, 0, 200),
                'ERR_SQL' => $sql,
                'ERR_DET' => (string) $e->getMessage(),
                'ERR_COD' => substr((string) $e->getCode(), 0, 20),
            ]);
        } catch (Throwable $x) {
            // El logger jamás debe romper el flujo principal.
        } finally {
            self::$registrando = false;
        }
    }

    /** Usuario logueado (NOMBRE) o 'RRHH.NET' si no hay sesión. */
    private static function usuario(?Request $request): string
    {
        $u = $request ? $request->user() : null;
        return trim((string) ($u->NOMBRE ?? $u->DATO1 ?? 'RRHH.NET'));
    }

    /** Nombre de la terminal cliente (header X-Terminal, DNS inverso o IP). */
    private static function terminal(?Request $request): string
    {
        return Terminal::nombre($request);   // header X-Terminal o DNS inverso cacheado (App\Support\Terminal)
    }
}
