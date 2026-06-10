<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $signedUrl)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido a Sistema PANDORA - Configuración de Acceso')
            ->greeting('Hola ' . $notifiable->name . ',')
            ->line('Tu cuenta en el Sistema PANDORA ha sido creada exitosamente.')
            ->line('Para completar la configuración de tu cuenta y establecer tu contraseña definitiva, por favor haz clic en el siguiente enlace:')
            ->action('Configurar Contraseña', $this->signedUrl)
            ->line('Este enlace de acceso seguro expirará en 24 horas.')
            ->line('Si no esperabas este correo, puedes ignorarlo.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
