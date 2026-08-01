<?php

/**
 * ============================================================
 * ResetPasswordNotification.php
 * ============================================================
 * Notificación: Link de Restablecimiento de Contraseña
 *
 * Se envía por email cuando el usuario solicita recuperar su
 * contraseña (ruta POST /api/auth/forgot-password).
 *
 * El link generado apunta al frontend Vue.js y contiene:
 *   - token : token aleatorio de 64 caracteres (texto plano)
 *   - email : email del usuario (URL-encoded)
 *
 * El token en texto plano viaja en la URL. En la base de datos
 * (Cache) se guarda únicamente el HASH del token, por seguridad.
 * La verificación se hace con Hash::check() en resetPassword().
 *
 * El link tiene validez de 1 hora (controlado en
 * AuthController::forgotPassword mediante Laravel Cache).
 *
 * URL generada: {FRONTEND_URL}/reset-password?token=...&email=...
 * La variable FRONTEND_URL se define en el archivo .env del backend.
 *
 * Canal de envío: mail (SMTP Gmail, configurado en .env)
 *
 * @package  App\Notifications
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * @see      App\Http\Controllers\AuthController::forgotPassword()
 * @see      App\Http\Controllers\AuthController::resetPassword()
 * ============================================================
 */

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    /**
     * Crea la notificación con el token de reseteo.
     *
     * @param string $token  Token aleatorio de 64 caracteres (texto plano)
     */
    public function __construct(private string $token) {}

    /**
     * Define los canales por los que se envía la notificación.
     * Solo se usa el canal 'mail' (email).
     *
     * @param  object $notifiable  La instancia del modelo Usuario
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Construye el mensaje de email con el link de reseteo.
     *
     * La URL se arma combinando FRONTEND_URL (del .env) con el token
     * y el email del usuario, para que el frontend pueda pre-rellenar
     * el formulario de nueva contraseña.
     *
     * @param  object $notifiable  La instancia del modelo Usuario
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Construir URL del frontend para el formulario de nueva contraseña
        $url = config('app.frontend_url') . '/reset-password'
             . '?token=' . $this->token
             . '&email=' . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Restablecer contraseña - Sistema RRHH')
            ->greeting('¡Hola, ' . $notifiable->NOMBRE . '!')
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer mi contraseña', $url)
            ->line('Este link es válido por **1 hora**.')
            ->line('Si no solicitaste restablecer tu contraseña, podés ignorar este mensaje.')
            ->salutation('Sistema RRHH — Silcar Equipos Industriales');
    }
}
