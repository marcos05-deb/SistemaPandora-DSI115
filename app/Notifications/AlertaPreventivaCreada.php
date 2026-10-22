<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AlertaPreventiva;
use Illuminate\Notifications\Notification;

final class AlertaPreventivaCreada extends Notification
{
    public function __construct(private readonly AlertaPreventiva $alerta) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{alert_id: string, senal: string, label: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'alert_id' => (string) $this->alerta->id,
            'senal' => $this->alerta->senal,
            'label' => AlertaPreventiva::labelForSignal($this->alerta->senal),
        ];
    }
}
