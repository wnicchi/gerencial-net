<?php

/**
 * ============================================================
 * 2026_06_08_000002_create_usuario_permisos_table.php
 * ============================================================
 * Migración: Crear tabla usuario_permisos
 *
 * Esta tabla implementa el sistema de control de acceso al menú
 * del Sistema RRHH.NET. Permite definir exactamente qué ítems
 * del menú lateral puede ver cada usuario.
 *
 * Modelo de permisos:
 * ───────────────────
 *  • NIVEL = 1 (Administrador): ve TODO siempre, ignora esta tabla.
 *  • Usuarios SIN filas en esta tabla: ven TODO (compatibilidad
 *    con usuarios existentes, sin restricciones).
 *  • Usuarios CON filas en esta tabla: ven SOLO los ítems cuya
 *    clave (permiso_clave) esté registrada. Lista blanca.
 *
 * El campo permiso_clave corresponde al campo 'id' de cada ítem
 * definido en el archivo frontend/src/config/menu.ts
 * Ejemplos: 'empleados-abm', 'vacaciones-agregar', 'reloj-capturas'
 *
 * Flujo de uso:
 *  1. Admin abre el módulo Administración → Permisos de Usuario.
 *  2. Selecciona un usuario de la lista.
 *  3. Activa/desactiva ítems del menú con checkboxes.
 *  4. Guarda: se reemplaza completo el set de permisos del usuario.
 *  5. Al hacer login, /api/auth/me retorna el array de permisos.
 *  6. El frontend filtra el menú según ese array.
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
     * Crea la tabla usuario_permisos.
     */
    public function up(): void
    {
        Schema::create('usuario_permisos', function (Blueprint $table) {
            $table->id();

            // FK al usuario (campo CODIGO de la tabla usuarios)
            $table->unsignedInteger('usuario_codigo');

            // Clave del ítem de menú (coincide con el 'id' en menu.ts del frontend)
            // Ejemplos: 'empleados-abm', 'vacaciones-agregar', 'reloj'
            $table->string('permiso_clave', 100);

            $table->timestamps();

            // Un usuario no puede tener el mismo permiso duplicado
            $table->unique(['usuario_codigo', 'permiso_clave'], 'uq_usuario_permiso');

            // Índice para consultas rápidas por usuario
            $table->index('usuario_codigo', 'idx_usuario_codigo');
        });
    }

    /**
     * Elimina la tabla usuario_permisos.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_permisos');
    }
};
