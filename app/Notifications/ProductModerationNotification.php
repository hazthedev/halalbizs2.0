<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Moderation outcome (admin → store owner): approved / rejected / banned.
 * Mirrors SubOrderStatusNotification — short, verb-first, one action link.
 */
class ProductModerationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Product $product,
        public string $action, // approved | rejected | banned
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $line] = $this->copy();

        $mail = (new MailMessage)
            ->subject("{$subject} · {$this->product->getTranslation('name', 'en')}")
            ->line($line);

        if ($this->reason !== null && trim($this->reason) !== '') {
            $mail->line(__('Reason: :reason', ['reason' => $this->reason]));
        }

        return $mail->action(__('View product'), $this->url());
    }

    public function toArray(object $notifiable): array
    {
        [$subject, $line] = $this->copy();

        return [
            'message' => "{$subject} — {$this->product->getTranslation('name', 'en')}",
            'detail' => $line,
            'product_id' => $this->product->id,
            'action' => $this->action,
            'reason' => $this->reason,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->action === 'approved'
            ? route('product.show', $this->product)
            : route('seller.products.edit', $this->product);
    }

    /** @return array{0: string, 1: string} */
    private function copy(): array
    {
        $name = $this->product->getTranslation('name', 'en');

        return match ($this->action) {
            'approved' => [__('Product approved'), __('":name" passed review and is now live on the storefront.', ['name' => $name])],
            'rejected' => [__('Product rejected'), __('":name" did not pass review and moved back to your drafts. Fix the issue and resubmit.', ['name' => $name])],
            'banned' => [__('Product banned'), __('":name" was banned and removed from the storefront. Contact support if you believe this is a mistake.', ['name' => $name])],
            default => [__('Product update'), __('":name" was updated by a moderator.', ['name' => $name])],
        };
    }
}
