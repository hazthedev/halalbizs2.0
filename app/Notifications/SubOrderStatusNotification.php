<?php

namespace App\Notifications;

use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One template for the whole status matrix (docs/06 §E): short,
 * verb-first, mono order numbers. Audience decides the wording.
 */
class SubOrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SubOrder $subOrder,
        public SubOrderStatus $status,
        public string $audience, // buyer | seller
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $line] = $this->copy();

        return (new MailMessage)
            ->subject("{$subject} · {$this->subOrder->sub_order_no}")
            ->line($line)
            ->action(__('View order'), $this->url());
    }

    public function toArray(object $notifiable): array
    {
        [$subject, $line] = $this->copy();

        return [
            'message' => "{$subject} — {$this->subOrder->sub_order_no}",
            'detail' => $line,
            'sub_order_id' => $this->subOrder->id,
            'sub_order_no' => $this->subOrder->sub_order_no,
            'status' => $this->status->value,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return $this->audience === 'seller'
            ? url("/seller/orders/{$this->subOrder->id}")
            : url("/account/orders/{$this->subOrder->id}");
    }

    /** @return array{0: string, 1: string} */
    private function copy(): array
    {
        $no = $this->subOrder->sub_order_no;

        if ($this->audience === 'seller') {
            return match ($this->status) {
                SubOrderStatus::Confirmed => [__('New order'), __('You have a new order :no to pack and ship.', ['no' => $no])],
                SubOrderStatus::Cancelled => [__('Order cancelled'), __('Order :no was cancelled.', ['no' => $no])],
                SubOrderStatus::Completed => [__('Order completed'), __('Order :no is completed. Earnings move to your balance.', ['no' => $no])],
                SubOrderStatus::ReturnRequested => [__('Return requested'), __('The buyer requested a return on order :no.', ['no' => $no])],
                default => [__('Order update'), __('Order :no is now :status.', ['no' => $no, 'status' => $this->status->label()])],
            };
        }

        return match ($this->status) {
            SubOrderStatus::Confirmed => [__('Order confirmed'), __('Order :no is confirmed and being prepared.', ['no' => $no])],
            SubOrderStatus::Processing => [__('Order packed'), __('Order :no is being packed.', ['no' => $no])],
            SubOrderStatus::Shipped => [__('Order shipped'), __('Order :no has shipped:tracking.', ['no' => $no, 'tracking' => $this->subOrder->tracking_no ? ' — '.$this->subOrder->tracking_courier.' '.$this->subOrder->tracking_no : ''])],
            SubOrderStatus::Delivered => [__('Order delivered'), __('Order :no was delivered. Confirm once everything looks good.', ['no' => $no])],
            SubOrderStatus::Completed => [__('Order completed'), __('Order :no is complete. Thanks for shopping!', ['no' => $no])],
            SubOrderStatus::Cancelled => [__('Order cancelled'), __('Order :no was cancelled.', ['no' => $no])],
            SubOrderStatus::Refunded => [__('Order refunded'), __('Order :no has been refunded.', ['no' => $no])],
            default => [__('Order update'), __('Order :no is now :status.', ['no' => $no, 'status' => $this->status->label()])],
        };
    }
}
