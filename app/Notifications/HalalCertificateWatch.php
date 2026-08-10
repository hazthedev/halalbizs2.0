<?php

namespace App\Notifications;

use App\Models\HalalCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The expiry watch talking to a seller. One class, two states — they are the
 * same conversation at two points, and splitting them would duplicate the
 * store/certificate plumbing to change one sentence.
 *
 *   'expiring' — the renewal window opened; nothing has happened to the listings.
 *   'lapsed'   — the term ended; the covered products are off the storefront.
 */
class HalalCertificateWatch extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public HalalCertificate $certificate,
        public string $state,          // 'expiring' | 'lapsed'
        public int $affectedProducts = 0,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->certificate->number;

        if ($this->state === 'lapsed') {
            return (new MailMessage)
                ->subject(__('Halal certificate expired — :number', ['number' => $number]))
                ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
                ->line(__('Certificate :number expired on :date.', [
                    'number' => $number,
                    'date' => $this->certificate->valid_to->translatedFormat('j M Y'),
                ]))
                // Say plainly what happened to their listings. A seller who
                // discovers this by finding their shop empty is a support
                // ticket and a trust problem.
                ->line(trans_choice(
                    '{1} :count product it covers has been delisted and is no longer visible to buyers.|[2,*] :count products it covers have been delisted and are no longer visible to buyers.',
                    $this->affectedProducts,
                    ['count' => $this->affectedProducts],
                ))
                ->line(__('Upload the renewed certificate and those listings go back up automatically.'));
        }

        return (new MailMessage)
            ->subject(__('Halal certificate expiring soon — :number', ['number' => $number]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->name]))
            ->line(__('Certificate :number expires on :date, in :days days.', [
                'number' => $number,
                'date' => $this->certificate->valid_to->translatedFormat('j M Y'),
                'days' => max(0, $this->certificate->daysRemaining()),
            ]))
            ->line(trans_choice(
                '{1} :count of your listings depends on it.|[2,*] :count of your listings depend on it.',
                $this->affectedProducts,
                ['count' => $this->affectedProducts],
            ))
            ->line(__('They are delisted automatically the day it expires, so renew before then.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'halal_certificate_'.$this->state,
            'certificate_id' => $this->certificate->id,
            'certificate_number' => $this->certificate->number,
            'valid_to' => $this->certificate->valid_to->toDateString(),
            'affected_products' => $this->affectedProducts,
            'url' => route('seller.products.index'),
        ];
    }
}
