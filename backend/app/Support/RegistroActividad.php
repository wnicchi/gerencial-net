<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RegistroActividad — Auditoría de lo que hacen los usuarios.
 *
 * Escucha TODAS las consultas de escritura (INSERT/UPDATE/DELETE) que ejecuta la
 * app y las guarda en log_actividad con un texto simple ("Agregó/Modificó/Eliminó
 * un registro en X") y el comando SQL técnico. Se registra solo en HTTP (no en
 * comandos/migraciones). Excluye tablas internas (los propios logs, sesiones, etc.).
 */
class RegistroActividad
{
    private static bool $ocupado = false;

    /** Tablas que NO se auditan (framework + logs internos). */
    private const EXCLUIR = [
        'log_actividad', 'log_errores_sql', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
        'personal_access_tokens', 'migrations', 'codigo_reserva',
    ];

    /** Nombre amigable por tabla (módulo). Si no está, se usa el nombre de la tabla. */
    private const MODULOS = [
        'personal' => 'Empleados',
        'puestoempleado' => 'Puestos del empleado',
        'puestos' => 'Puestos',
        'viajes' => 'Viajes',
        'vacadias' => 'Vacaciones',
        'examenes' => 'Exámenes médicos',
        'celulares_equipos' => 'Celulares',
        'celular_empleados' => 'Celulares (asignación)',
        'siniestros' => 'Siniestros ART',
        'per_apercibimientos' => 'Apercibimientos',
        'per_hist' => 'Historial de empleados',
        'nivel_permisos' => 'Roles / permisos',
        'usuario_permisos' => 'Permisos de usuario',
        'usuarios' => 'Usuarios',
        'niveles' => 'Roles',
        'convenios' => 'Convenios',
        'categorias' => 'Categorías',
        'sector' => 'Sectores',
        'subsector' => 'Subsectores',
        'empresas' => 'Empresas',
        'lugares' => 'Lugares',
        'adelantos' => 'Adelantos',
        'novedades' => 'Novedades',
        'almuerzos' => 'Almuerzos',
        'capacitacion' => 'Capacitación',
        'entrega_ropa' => 'Entrega de ropa',
        'reloj_faltas_diarias' => 'Faltas diarias',
        'reloj_capturas' => 'Reloj de personal',
        'siniestros_seguimiento' => 'Seguimiento ART',
    ];

    /**
     * Cómo describir el registro afectado por tabla: columna clave + columna con
     * un dato legible (ej. el nombre). Sirve para que el texto diga "…de Fulano".
     */
    private const DESCRIBIR = [
        'personal'   => ['key' => 'PER_COD', 'campo' => 'PER_NOM'],
        // El historial guarda el código del empleado; el nombre está en 'personal'.
        'per_hist'   => ['key' => 'hla_cod', 'campo' => 'PER_NOM', 'tabla' => 'personal', 'tablaKey' => 'PER_COD'],
        'viajes'     => ['key' => 'UNICO',   'campo' => 'PVI_NOM'],
        'examenes'   => ['key' => 'EXA_EMP', 'campo' => 'PER_NOM', 'tabla' => 'personal', 'tablaKey' => 'PER_COD'],
        'celular_empleados' => ['key' => 'cem_emp', 'campo' => 'PER_NOM', 'tabla' => 'personal', 'tablaKey' => 'PER_COD'],
        'siniestros' => ['key' => 'SIN_NRO', 'campo' => 'SIN_EMN'],
        'usuarios'   => ['key' => 'CODIGO',  'campo' => 'NOMBRE'],
        'convenios'  => ['key' => 'CON_COD', 'campo' => 'CON_DES'],
        'categorias' => ['key' => 'CAT_COD', 'campo' => 'CAT_DES'],
        'puestos'    => ['key' => 'PUE_COD', 'campo' => 'PUE_DES'],
        'empresas'   => ['key' => 'emp_cod', 'campo' => 'emp_nom'],
        'niveles'    => ['key' => 'CODNIV',  'campo' => 'DESCRIBE'],
    ];

    /** Registra el escucha global de consultas (llamar en boot() solo para HTTP). */
    public static function escuchar(): void
    {
        DB::listen(function (QueryExecuted $q) {
            self::registrar($q->sql, $q->bindings);
        });
    }

    private static function registrar(string $sql, array $bindings): void
    {
        if (self::$ocupado) return;

        $op = self::operacion($sql);
        if ($op === null) return;

        $tabla = self::tabla($sql);
        if ($tabla === '' || in_array(strtolower($tabla), self::EXCLUIR, true)) return;

        self::$ocupado = true;
        try {
            $req = request();
            $modulo = self::MODULOS[strtolower($tabla)] ?? $tabla;
            $texto = match ($op) {
                'INSERT' => "Agregó un registro en $modulo",
                'UPDATE' => "Modificó datos en $modulo",
                'DELETE' => "Eliminó un registro en $modulo",
                default  => "Operación en $modulo",
            };
            $detalle = self::descripcion($op, $tabla, $sql, $bindings);
            if ($detalle !== '') $texto .= " de $detalle";
            $sqlFull = $sql;
            if (!empty($bindings)) {
                $sqlFull .= '  |  bindings: ' . json_encode($bindings, JSON_UNESCAPED_UNICODE);
            }

            DB::table('log_actividad')->insert([
                'ACT_FEC' => now(),
                'ACT_USU' => substr(self::usuario($req), 0, 60),
                'ACT_TER' => substr(self::terminal($req), 0, 60),
                'ACT_MOD' => substr((string) $modulo, 0, 200),
                'ACT_OP'  => $op,
                'ACT_TXT' => substr($texto, 0, 500),
                'ACT_SQL' => $sqlFull,
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe romper el flujo principal.
        } finally {
            self::$ocupado = false;
        }
    }

    /**
     * Dato legible del registro afectado (ej. el nombre del empleado), o '' si no
     * se puede determinar. En altas lo saca del propio comando; en modificaciones
     * y bajas lo busca por su clave.
     */
    private static function descripcion(string $op, string $tabla, string $sql, array $bindings): string
    {
        $cfg = self::DESCRIBIR[strtolower($tabla)] ?? null;
        if (!$cfg) return '';

        try {
            // 1) Valor de la clave del registro afectado.
            if ($op === 'INSERT') {
                $cols = self::columnasInsert($sql);
                // Si el dato descriptivo está en la MISMA tabla y viene en el INSERT, usarlo directo.
                if (empty($cfg['tabla'])) {
                    $iCampo = array_search(strtolower($cfg['campo']), $cols, true);
                    if ($iCampo !== false && array_key_exists($iCampo, $bindings)) {
                        return self::limpiar($bindings[$iCampo]);
                    }
                }
                $iKey = array_search(strtolower($cfg['key']), $cols, true);
                $valorClave = ($iKey !== false && array_key_exists($iKey, $bindings)) ? $bindings[$iKey] : null;
            } else {
                $valorClave = self::valorClave($sql, $bindings, $cfg['key']);
            }
            if ($valorClave === null) return '';

            // 2) Buscar el dato legible (en la misma tabla o en una relacionada).
            $tablaDesc = $cfg['tabla'] ?? $tabla;
            $keyDesc   = $cfg['tablaKey'] ?? $cfg['key'];
            $dato = DB::table($tablaDesc)->where($keyDesc, $valorClave)->value($cfg['campo']);
            return $dato !== null ? self::limpiar($dato) : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    /** Columnas (en orden) de un INSERT, en minúscula. */
    private static function columnasInsert(string $sql): array
    {
        if (preg_match('/insert\s+into\s+\[?[a-z0-9_]+\]?\s*\((.*?)\)\s*values/is', $sql, $m)) {
            return array_map(fn ($c) => strtolower(trim($c, " \t\r\n[]")), explode(',', $m[1]));
        }
        return [];
    }

    /** Valor del binding correspondiente a "[key] = ?" en el SQL (o null). */
    private static function valorClave(string $sql, array $bindings, string $key): mixed
    {
        if (!preg_match('/\[?' . preg_quote($key, '/') . '\]?\s*=\s*\?/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $offset = $m[0][1];
        $idx = substr_count(substr($sql, 0, $offset), '?');   // cuántos ? hay antes
        return array_key_exists($idx, $bindings) ? $bindings[$idx] : null;
    }

    private static function limpiar(mixed $v): string
    {
        return mb_substr(trim((string) $v), 0, 60);
    }

    private static function operacion(string $sql): ?string
    {
        $s = ltrim(strtolower($sql));
        if (str_starts_with($s, 'insert')) return 'INSERT';
        if (str_starts_with($s, 'update')) return 'UPDATE';
        if (str_starts_with($s, 'delete')) return 'DELETE';
        return null;
    }

    private static function tabla(string $sql): string
    {
        if (preg_match('/^\s*insert\s+into\s+\[?([a-zA-Z0-9_]+)\]?/i', $sql, $m)) return $m[1];
        if (preg_match('/^\s*update\s+\[?([a-zA-Z0-9_]+)\]?/i', $sql, $m)) return $m[1];
        if (preg_match('/^\s*delete\s+from\s+\[?([a-zA-Z0-9_]+)\]?/i', $sql, $m)) return $m[1];
        return '';
    }

    private static function usuario($request): string
    {
        $u = $request ? $request->user() : null;
        return trim((string) ($u->NOMBRE ?? $u->DATO1 ?? 'SISTEMA'));
    }

    private static function terminal($request): string
    {
        // Delegado en Terminal::nombre, que cachea el DNS inverso: acá se entra
        // por CADA escritura auditada y gethostbyaddr() repetido colgaba el request.
        return Terminal::nombre($request);
    }
}
