<?php

namespace App\Notifications;

use App\Models\HalalCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Our decision on a submitted halal certificate (audit H-6).
 *
 * Mirrors SellerApplicationDecision. On a rejection the seller's next step is
 * the whole point of the message, so the reviewer's note is carried verbatim
 * rather than summarised.
 */
class HalalCertificateDecision extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'approved'|'rejected'  $decision
     */
    public function __construct(
        public HalalCertificate $certificate,
        public string $decision,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->certificate->products()->count();

        if ($this->decision === 'approved') {
            return (new MailMessage)
                ->subject(__('Halal certificate approved — :number', ['number' => $this->certificate->number]))
                ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
                ->line(__('We have verified certificate :number. The halal badge is now live on :count of your products.', [
                    'number' => $this->certificate->number,
                    'count' => $count,
                ]))
                ->line(__('It runs until :date. We will remind you :days days before that.', [
                    'date' => $this->certificate->valid_to->format('d M Y'),
                    'days' => HalalCertificate::RENEWAL_WINDOW_DAYS,
                ]))
                ->action(__('View your certificates'), route('seller.certificates'));
        }

        return (new MailMessage)
            ->subject(__('Halal certificate needs another look — :number', ['number' => $this->certificate->number]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('We could not verify certificate :number yet.', ['number' => $this->certificate->number]))
            ->line(__('What we found: :reason', ['reason' => $this->reason]))
            ->line(__('Fix that and send it again — your products stay on sale in the meantime.'))
            ->action(__('Update the certificate'), route('seller.certificates'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->decision === 'approved'
                ? __('Halal certificate :number approved', ['number' => $this->certificate->number])
                : __('Halal certificate :number needs another look', ['number' => $this->certificate->number]),
            'detail' => $this->reason,
            'certificate_id' => $this->certificate->id,
            'url' => route('seller.certificates'),
        ];
    }
}
