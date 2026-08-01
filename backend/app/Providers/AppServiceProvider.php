<?php

/**
 * ============================================================
 * AppServiceProvider.php
 * ============================================================
 * Proveedor de Servicios Principal — Sistema RRHH.NET
 *
 * Se ejecuta al iniciar la aplicación Laravel y configura
 * servicios globales del sistema.
 *
 * Solución implementada: SSL/TLS para Gmail SMTP en entorno local
 * ---------------------------------------------------------------
 * Problema: PHP 8.3 en XAMPP Windows no puede verificar el
 * certificado SSL de smtp.gmail.com porque el bundle de certificados
 * de OpenSSL de XAMPP está desactualizado y no incluye la CA de Google.
 *
 * Solución: En entorno 'local' se reemplaza el transporte SMTP
 * estándar de Symfony Mailer por una instancia personalizada de
 * EsmtpTransport con la verificación SSL deshabilitada.
 *
 * ⚠️  IMPORTANTE: Esta configuración SOLO aplica cuando APP_ENV=local
 *     en el archivo .env. En producción (APP_ENV=production) se usa
 *     el transporte estándar de Laravel con SSL completo.
 *
 * @package  App\Providers
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * ============================================================
 */

namespace App\Providers;

use App\Database\LoggingSqlServerConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios en el contenedor de la aplicación.
     * (No se usa en esta versión del sistema)
     */
    public function register(): void
    {
        // ── TODOS los errores SQL del proyecto van a `log_errores_sql` ──
        // Se registra en la capa de conexión (driver sqlsrv), antes de que cualquier
        // try/catch de un controller los pueda tapar. Cubre las 3 conexiones (sqlsrv_rrhh
        // / documentos / gestion) porque todas usan el mismo driver.
        Connection::resolverFor('sqlsrv', function ($pdo, $database, $prefix, $config) {
            return new LoggingSqlServerConnection($pdo, $database, $prefix, $config);
        });
    }

    /**
     * Inicializa servicios después de que la aplicación está lista.
     *
     * En entorno local: configura el transporte SMTP sin verificación SSL
     * para solucionar el problema de certificados en XAMPP PHP 8.3 Windows.
     *
     * El parche extiende el driver 'smtp' del MailManager de Laravel,
     * reemplazándolo con un EsmtpTransport de Symfony configurado
     * con verify_peer = false (solo en desarrollo local).
     */
    public function boot(): void
    {
        // ── Auditoría: registrar la actividad (INSERT/UPDATE/DELETE) de los
        // usuarios, solo en peticiones HTTP (no en comandos/migraciones/colas).
        if (!app()->runningInConsole()) {
            \App\Support\RegistroActividad::escuchar();
        }

        // ── Parche SSL solo para entorno de desarrollo local ──────────────
        // En producción (hosting con certificados válidos) esto NO se ejecuta.
        if (app()->environment('local')) {

            $this->app->resolving('mail.manager', function ($mailManager) {

                $mailManager->extend('smtp', function () {

                    // Crear transporte SMTP manualmente con Symfony Mailer
                    $transport = new EsmtpTransport(
                        host: config('mail.mailers.smtp.host'),       // smtp.gmail.com
                        port: (int) config('mail.mailers.smtp.port'), // 465
                        tls:  config('mail.mailers.smtp.scheme') === 'smtps', // true
                    );

                    // Credenciales Gmail (del .env)
                    $transport->setUsername(config('mail.mailers.smtp.username'));
                    $transport->setPassword(config('mail.mailers.smtp.password'));

                    // Deshabilitar verificación de certificado SSL
                    // ⚠️ Solo seguro en entorno local/desarrollo
                    $transport->getStream()->setStreamOptions([
                        'ssl' => [
                            'verify_peer'      => false,
                            'verify_peer_name' => false,
                        ],
                    ]);

                    return $transport;
                });
            });
        }
    }
}
