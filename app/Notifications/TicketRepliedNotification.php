<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the ticket owner when support posts a reply. */
class TicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Support replied to your ticket'))
            ->line(__('Our support team replied to ":subject".', ['subject' => $this->ticket->subject]))
            ->action(__('View ticket'), $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => __('Support replied to ":subject"', ['subject' => $this->ticket->subject]),
            'ticket_id' => $this->ticket->id,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('help.tickets', ['ticket' => $this->ticket->id]);
    }
}
