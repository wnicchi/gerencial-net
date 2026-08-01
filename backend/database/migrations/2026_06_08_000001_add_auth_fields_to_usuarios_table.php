<?php

/**
 * ============================================================
 * 2026_06_08_000001_add_auth_fields_to_usuarios_table.php
 * ============================================================
 * Migración: Agregar campos de autenticación a la tabla usuarios
 *
 * La tabla 'usuarios' fue migrada desde SQL Server con su estructura
 * original de FoxPro. Esta migración le agrega los campos necesarios
 * para el sistema de autenticación web con Laravel Sanctum.
 *
 * Campos agregados:
 *
 *   email             (string, nullable, unique)
 *                     Dirección de email del usuario. Se registra
 *                     durante el primer acceso al sistema. Único por usuario.
 *
 *   password          (string, nullable)
 *                     Contraseña hasheada con bcrypt. Es nullable porque
 *                     los usuarios nuevos no tienen contraseña hasta completar
 *                     el flujo de primer acceso.
 *
 *   email_verified_at (timestamp, nullable)
 *                     Fecha y hora en que el usuario verificó su email
 *                     (Paso 3 del primer acceso). NULL = no verificado.
 *
 *   primer_acceso     (boolean, default: true)
 *                     Indica si el usuario todavía no activó su cuenta.
 *                     true  = nunca completó el flujo de activación.
 *                     false = cuenta completamente activa con contraseña.
 *
 *   remember_token    (string(100), nullable)
 *                     Token estándar de Laravel para la funcionalidad
 *                     "Recordarme". Requerido por Authenticatable.
 *
 * ⚠️  IMPORTANTE: En producción usar el script scripts/deploy_produccion.php
 *     en lugar de php artisan migrate, ya que verifica si los campos
 *     existen antes de agregarlos (ejecución segura y repetible).
 *
 * @package  Database\Migrations
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración: agrega los campos de autenticación.
     *
     * Los campos se insertan después de NOMBRE para mantener
     * un orden lógico en la tabla.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            // Email único por usuario (se registra en primer acceso)
            $table->string('email')->nullable()->unique()->after('NOMBRE');

            // Contraseña bcrypt (se crea en el paso 4 del primer acceso)
            $table->string('password')->nullable()->after('email');

            // Fecha de verificación de email (null = no verificado)
            $table->timestamp('email_verified_at')->nullable()->after('password');

            // Flag de primer acceso (true = cuenta sin activar)
            $table->boolean('primer_acceso')->default(true)->after('email_verified_at');

            // Token estándar Laravel para "Recordarme"
            $table->string('remember_token', 100)->nullable()->after('primer_acceso');
        });
    }

    /**
     * Revierte la migración: elimina los campos de autenticación.
     *
     * ⚠️  Precaución: ejecutar esto en producción eliminaría los emails
     *     y contraseñas de todos los usuarios registrados.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'password',
                'email_verified_at',
                'primer_acceso',
                'remember_token',
            ]);
        });
    }
};
