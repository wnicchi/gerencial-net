<?php

/**
 * ============================================================
 * UsuarioPermiso.php
 * ============================================================
 * Modelo Eloquent — Tabla: usuario_permisos
 *
 * Representa un permiso de acceso asignado a un usuario específico.
 * Cada fila indica que el usuario puede ver el ítem de menú
 * identificado por 'permiso_clave'.
 *
 * Relación con otras tablas:
 *   usuario_permisos.usuario_codigo → usuarios.CODIGO
 *
 * Métodos de uso frecuente:
 *
 *   // Obtener todas las claves de un usuario
 *   UsuarioPermiso::clavesDeUsuario($codigo)
 *
 *   // Verificar si tiene un permiso específico
 *   UsuarioPermiso::tienePermiso($codigo, 'empleados-abm')
 *
 *   // Reemplazar todos los permisos de un usuario
 *   UsuarioPermiso::reemplazar($codigo, ['empleados-abm', 'vacaciones-agregar'])
 *
 * @package  App\Models
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * @see      App\Http\Controllers\AdminController
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class UsuarioPermiso extends Model
{
    /**
     * Nombre de la tabla en MySQL.
     *
     * @var string
     */
    protected $table = 'usuario_permisos';

    /**
     * Campos que se pueden asignar masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'usuario_codigo',
        'permiso_clave',
    ];

    // ══════════════════════════════════════════════════════════
    // MÉTODOS ESTÁTICOS DE CONSULTA
    // ══════════════════════════════════════════════════════════

    /**
     * Retorna el array de claves de permisos de un usuario.
     *
     * Array vacío = usuario sin restricciones (ve todo el menú).
     *
     * @param  int    $usuarioCodigo  Valor del campo CODIGO en la tabla usuarios
     * @return array<string>          Lista de claves permitidas (ej: ['empleados-abm', 'vacaciones'])
     */
    public static function clavesDeUsuario(int $usuarioCodigo): array
    {
        return static::where('usuario_codigo', $usuarioCodigo)
            ->pluck('permiso_clave')
            ->toArray();
    }

    /**
     * Verifica si un usuario tiene un permiso específico.
     *
     * @param  int    $usuarioCodigo
     * @param  string $clave          Clave del ítem de menú
     * @return bool
     */
    public static function tienePermiso(int $usuarioCodigo, string $clave): bool
    {
        return static::where('usuario_codigo', $usuarioCodigo)
            ->where('permiso_clave', $clave)
            ->exists();
    }

    /**
     * Verifica si el usuario tiene permisos configurados.
     *
     * Si no tiene ninguno → sin restricciones (acceso total al menú).
     *
     * @param  int  $usuarioCodigo
     * @return bool  true = tiene restricciones configuradas
     */
    public static function tieneRestricciones(int $usuarioCodigo): bool
    {
        return static::where('usuario_codigo', $usuarioCodigo)->exists();
    }

    // ══════════════════════════════════════════════════════════
    // MÉTODOS DE ESCRITURA
    // ══════════════════════════════════════════════════════════

    /**
     * Reemplaza completamente los permisos de un usuario.
     *
     * Elimina todos los permisos existentes del usuario e inserta
     * los nuevos en una transacción. Si el array está vacío,
     * queda sin restricciones (ve todo el menú).
     *
     * @param  int          $usuarioCodigo
     * @param  array<string> $claves         Array de claves permitidas
     * @return int                           Cantidad de permisos guardados
     */
    public static function reemplazar(int $usuarioCodigo, array $claves): int
    {
        // Eliminar todos los permisos actuales del usuario
        static::where('usuario_codigo', $usuarioCodigo)->delete();

        if (empty($claves)) {
            return 0; // Sin restricciones
        }

        // Insertar nuevos permisos (evitar duplicados con array_unique)
        $registros = array_map(
            fn($clave) => [
                'usuario_codigo' => $usuarioCodigo,
                'permiso_clave'  => $clave,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            array_unique($claves)
        );

        static::insert($registros);

        return count($registros);
    }
}
