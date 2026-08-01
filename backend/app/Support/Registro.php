<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Helper para armar registros completos al insertar/actualizar.
 *
 * Regla del proyecto: ningún registro debe quedar con columnas en NULL.
 * `completar()` toma los valores que se quieren guardar y rellena TODAS las
 * demás columnas de la tabla con un valor por defecto según su tipo
 * (0 para numéricos/bit, '' para textos, 1900-01-01 para fechas). Las columnas
 * autoincrementales (identity, ej. UNICO) se omiten para que las asigne la base.
 */
class Registro
{
    /** Caché de columnas por (conexión|tabla) para no consultar el esquema cada vez. */
    private static array $cache = [];

    /**
     * Devuelve $valores completado con defaults para toda columna faltante o nula.
     */
    public static function completar(string $tabla, array $valores, ?string $conexion = null): array
    {
        $cols = self::columnas($tabla, $conexion);
        $out = [];
        foreach ($cols as $c) {
            $name = $c['name'];
            if (!empty($c['auto_increment'])) {
                continue;   // identity (ej. UNICO): la asigna la base
            }
            if (array_key_exists($name, $valores) && $valores[$name] !== null) {
                $v = $valores[$name];
                // Truncar strings al largo de la columna (evita "String or binary data would be truncated")
                if (is_string($v)) {
                    $len = self::longitud((string) ($c['type'] ?? ''));
                    if ($len > 0) $v = mb_substr($v, 0, $len);
                }
                $out[$name] = $v;
            } else {
                $out[$name] = self::porDefecto((string) ($c['type_name'] ?? ''));
            }
        }
        // Conservar claves provistas que no figuren en el esquema (por las dudas)
        foreach ($valores as $k => $v) {
            if (!array_key_exists($k, $out) && $v !== null) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function columnas(string $tabla, ?string $conexion): array
    {
        $key = ($conexion ?? 'default') . '|' . $tabla;
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $conexion
                ? Schema::connection($conexion)->getColumns($tabla)
                : Schema::getColumns($tabla);
        }
        return self::$cache[$key];
    }

    /** Largo máximo de una columna de texto a partir del tipo (ej. "char(10)" → 10). */
    private static function longitud(string $tipo): int
    {
        $t = strtolower($tipo);
        if (!str_contains($t, 'char') && !str_contains($t, 'text')) return 0;   // solo strings
        return preg_match('/\((\d+)\)/', $t, $m) ? (int) $m[1] : 0;
    }

    /** Valor por defecto (no nulo) según el tipo de columna. */
    private static function porDefecto(string $tipo): mixed
    {
        $t = strtolower($tipo);
        return match (true) {
            str_contains($t, 'int'), str_contains($t, 'dec'), str_contains($t, 'num'),
            str_contains($t, 'float'), str_contains($t, 'real'), str_contains($t, 'money'),
            str_contains($t, 'bit') => 0,
            str_contains($t, 'date'), str_contains($t, 'time') => '1900-01-01 00:00:00',
            default => '',
        };
    }
}
