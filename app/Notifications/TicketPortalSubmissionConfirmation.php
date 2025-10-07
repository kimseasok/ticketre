<?php

namespace App\Notifications;

use App\Models\TicketSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TicketPortalSubmissionConfirmation extends Notification
{
    use Queueable;

    public function __construct(private readonly TicketSubmission $submission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = sprintf('TKT-%06d', $this->submission->ticket_id);
        $confirmationUrl = route('portal.tickets.confirmation', ['submission' => $this->submission->getKey()], true);

        return (new MailMessage())
            ->subject('We received your ticket '.$reference)
            ->greeting('Hello '.Str::of((string) ($this->submission->contact?->name ?? 'there'))->trim()->default('there'))
            ->line('Thanks for contacting our support team. Your request has been logged successfully.')
            ->line('Reference: '.$reference)
            ->line('Summary: '.$this->submission->subject)
            ->line('Status: '.ucfirst($this->submission->status))
            ->action('View your request', $confirmationUrl)
            ->line('We will review your message and reply as soon as possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'submission_id' => $this->submission->getKey(),
            'ticket_id' => $this->submission->ticket_id,
            'status' => $this->submission->status,
        ];
    }
}
