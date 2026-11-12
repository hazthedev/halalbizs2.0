<?php

namespace App\Notifications;

use App\Models\HalalCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WhipCertificateExpiry extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HalalCertificate $certificate, public int $days) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('WHIP certificate expires in :days days', ['days' => $this->days]))
            ->line($this->message())
            ->action(__('Renew in WHIP'), config('services.whip.renew_url'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'whip_certificate_expiry', 'title' => __('Renew your WHIP certificate'),
            'body' => $this->message(), 'message' => $this->message(),
            'certificate_id' => $this->certificate->id, 'certificate_number' => $this->certificate->number,
            'valid_to' => $this->certificate->valid_to->toDateString(), 'days' => $this->days,
            'url' => config('services.whip.renew_url'),
        ];
    }

    private function message(): string
    {
        return __('Certificate :number expires on :date, in :days days. Renew in WHIP to keep your products certified.', [
            'number' => $this->certificate->number, 'date' => $this->certificate->valid_to->format('j M Y'), 'days' => $this->days,
        ]);
    }
}
