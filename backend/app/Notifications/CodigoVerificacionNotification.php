<?php

/**
 * ============================================================
 * CodigoVerificacionNotification.php
 * ============================================================
 * Notificación: Código de Verificación para Primer Acceso
 *
 * Se envía por email durante el flujo de activación de cuenta
 * (Paso 2 del primer acceso). Contiene un código de 6 dígitos
 * que el usuario debe ingresar en el frontend para verificar
 * su dirección de email.
 *
 * El código tiene una validez de 15 minutos (controlado en
 * AuthController::enviarCodigo mediante Laravel Cache).
 *
 * Canal de envío: mail (SMTP Gmail, configurado en .env)
 *
 * @package  App\Notifications
 * @author   Sistema RRHH.NET
 * @version  1.0.0
 * @since    2026-06-08
 * @see      App\Http\Controllers\AuthController::enviarCodigo()
 * ============================================================
 */

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CodigoVerificacionNotification extends Notification
{
    /**
     * Crea la notificación con el código de verificación.
     *
     * @param string $codigo  Código de 6 dígitos (ej: "047832")
     */
    public function __construct(private string $codigo) {}

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
     * Construye el mensaje de email.
     *
     * El asunto, saludo y cuerpo están en español.
     * El nombre del destinatario se toma del campo NOMBRE del modelo Usuario.
     *
     * @param  object $notifiable  La instancia del modelo Usuario
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código de verificación - Sistema RRHH')
            ->greeting('¡Hola, ' . $notifiable->NOMBRE . '!')
            ->line('Recibimos una solicitud para activar tu cuenta en el Sistema RRHH.')
            ->line('Tu código de verificación es:')
            ->line('## ' . $this->codigo)
            ->line('Este código es válido por **15 minutos**.')
            ->line('Si no solicitaste este código, podés ignorar este mensaje.')
            ->salutation('Sistema RRHH — Silcar Equipos Industriales');
    }
}
