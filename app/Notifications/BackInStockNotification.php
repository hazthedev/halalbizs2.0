<?php

namespace App\Notifications;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A variant a buyer asked to be alerted about is back in stock (one-shot). */
class BackInStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProductVariant $variant) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->variant->product;
        $name = $product->getTranslation('name', 'en').$this->variantSuffix();

        return (new MailMessage)
            ->subject(__('Back in stock: :name', ['name' => $product->getTranslation('name', 'en')]))
            ->line(__(':name is available again — grab it before it sells out.', ['name' => $name]))
            ->action(__('View product'), url('/p/'.$product->slug));
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->variant->product;

        return [
            'type' => 'back_in_stock',
            'message' => __(':name is back in stock', ['name' => $product->getTranslation('name', 'en').$this->variantSuffix()]),
            'url' => url('/p/'.$product->slug),
            'product_id' => $product->id,
            'product_variant_id' => $this->variant->id,
        ];
    }

    private function variantSuffix(): string
    {
        return $this->variant->options_label ? ' ('.$this->variant->options_label.')' : '';
    }
}
