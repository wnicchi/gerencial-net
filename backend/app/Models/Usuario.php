<?php

/**
 * ============================================================
 * Usuario.php
 * ============================================================
 * Modelo Eloquent — Tabla: usuarios
 *
 * Representa a los usuarios del sistema RRHH.NET.
 * La tabla fue migrada desde SQL Server (sqlRRHH) y contiene
 * los datos originales de FoxPro más los campos de autenticación
 * agregados en la migración 2026_06_08_000001.
 *
 * Campos heredados de FoxPro (SQL Server):
 *   - CODIGO          : PK, entero autoincremental
 *   - DATO1           : login / nombre de usuario
 *   - DATO2           : contraseña vieja (encriptación FoxPro, ya no se usa)
 *   - NOMBRE          : nombre completo del usuario
 *   - ESTADO          : 1 = activo, 0 = inactivo
 *   - NIVEL           : nivel de acceso (controla permisos de menú)
 *   - EMPRESA         : empresa a la que pertenece
 *
 * Campos agregados para autenticación web:
 *   - email           : dirección de correo (única, nullable)
 *   - password        : contraseña hasheada con bcrypt
 *   - email_verified_at : fecha de verificación de email
 *   - primer_acceso   : true = aún no activó la cuenta (default)
 *   - remember_token  : token para "recordarme" (estándar Laravel)
 *
 * Traits:
 *   - HasApiTokens    : soporte para tokens Sanctum
 *   - Notifiable      : soporte para envío de notificaciones (email)
 *
 * @package  App\Models
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * GERENCIAL.NET: el login es contra el sistema de GESTIÓN (base sqlLOGIST,
     * conexión 'gestion'), NO contra los usuarios de RRHH (default = sqlRRHHlog).
     * Los datos del tablero (RRHH/WMS) usan sus propias conexiones.
     *
     * @var string
     */
    protected $connection = 'gestion';

    /**
     * Nombre de la tabla (USUARIOS en sqlLOGIST; SQL Server es case-insensitive).
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * Clave primaria de la tabla.
     * Los campos heredados de FoxPro usan MAYÚSCULAS.
     *
     * @var string
     */
    protected $primaryKey = 'CODIGO';

    /**
     * La tabla no tiene campos created_at / updated_at.
     * (La tabla original de FoxPro no los tenía)
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos que se pueden asignar masivamente.
     *
     * @var array<string>
     */
    protected $fillable = [
        'CODIGO',
        'DATO1',
        'NOMBRE',
        'email',
        'password',
        'email_verified_at',
        'primer_acceso',
        'remember_token',
        'ESTADO',
        'NIVEL',
        'ES_ADMIN',
        'DOMICILIO',
        'TELEFONO',
        'DNI',
        'NOTAS',
        'RENOVAR',
        'CADACUANTO',
        'CONTADOR',
        'CLA_VER',
    ];

    /**
     * Campos que se ocultan en las respuestas JSON.
     *
     * - password y remember_token : nunca se exponen
     * - DATO1 y DATO2             : el login y la contraseña vieja de FoxPro
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'DATO2',
        'CLA_VER',   // copia cifrada de la clave: nunca se serializa; solo se lee vía endpoint admin
    ];

    /**
     * Conversiones automáticas de tipos (casts).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',  // Convierte a objeto Carbon
            'primer_acceso'     => 'boolean',   // 0/1 → true/false
            'ESTADO'            => 'boolean',   // 0/1 → true/false
            'password'          => 'hashed',    // Hashea automáticamente al asignar
        ];
    }

    /**
     * Define el destino de las notificaciones por email.
     *
     * Sobrescribe el método estándar de Laravel porque el campo
     * de email en esta tabla no se llama 'email' en el modelo base,
     * aunque sí en nuestra tabla.
     *
     * @return string  Dirección de email del usuario
     */
    public function routeNotificationForMail(): string
    {
        return (string) $this->email;
    }

    /**
     * Mapeo email ↔ EMAIL.
     *
     * En sqlLOGIST (conexión 'gestion') la columna heredada de FoxPro se llama EMAIL
     * (MAYÚSCULAS) y el driver la devuelve con ese nombre; Laravel/el código usan
     * 'email' en minúscula. Se traduce acá (lectura y escritura) para que el email
     * se lea y se guarde bien (login, recuperar clave, alta/edición de usuarios).
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => isset($attributes['EMAIL'])
                ? (rtrim((string) $attributes['EMAIL']) ?: null)
                : ($attributes['email'] ?? null),
            set: fn ($value) => ['EMAIL' => $value !== null ? strtolower(trim($value)) : null],
        );
    }

    /**
     * Guarda una copia cifrada (reversible, con APP_KEY) de la clave en texto plano,
     * para que un administrador pueda verla luego. NO reemplaza al hash bcrypt de
     * 'password'; es solo la copia visible. Llamar cada vez que se define la clave.
     */
    public function guardarClaveVisible(string $plano): void
    {
        $this->CLA_VER = \Illuminate\Support\Facades\Crypt::encryptString($plano);
    }

    /**
     * Descifra la clave visible. Devuelve null si no hay copia (clave anterior al
     * cambio, o dato corrupto). Solo debe invocarse desde el endpoint de admin.
     */
    public function claveVisible(): ?string
    {
        if (empty($this->CLA_VER)) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($this->CLA_VER);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
