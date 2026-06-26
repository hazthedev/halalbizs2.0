<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** A buyer asked a question on one of the seller's products (in-app). */
class ProductQuestionAsked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Product $product) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'product_question',
            'message' => __('New question on :name', ['name' => $this->product->getTranslation('name', 'en')]),
            'url' => url('/seller/questions'),
            'product_id' => $this->product->id,
        ];
    }
}
