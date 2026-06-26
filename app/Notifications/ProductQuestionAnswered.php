<?php

namespace App\Notifications;

use App\Models\ProductQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The seller answered a buyer's product question (in-app + mail). */
class ProductQuestionAnswered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ProductQuestion $question) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->question->product;

        return (new MailMessage)
            ->subject(__('Your question was answered'))
            ->line(__('The seller answered your question on :name.', ['name' => $product->getTranslation('name', 'en')]))
            ->line('“'.$this->question->answer.'”')
            ->action(__('View product'), url('/p/'.$product->slug));
    }

    public function toArray(object $notifiable): array
    {
        $product = $this->question->product;

        return [
            'type' => 'question_answered',
            'message' => __('Your question on :name was answered', ['name' => $product->getTranslation('name', 'en')]),
            'url' => url('/p/'.$product->slug),
            'product_id' => $product->id,
        ];
    }
}
