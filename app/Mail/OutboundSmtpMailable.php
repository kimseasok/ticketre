<?php

namespace App\Mail;

use App\Models\SmtpOutboundMessage;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

class OutboundSmtpMailable extends Mailable
{
    public function __construct(private readonly SmtpOutboundMessage $outbound)
    {
    }

    public function build(): self
    {
        $mailable = $this
            ->subject($this->outbound->subject)
            ->from($this->outbound->from_email, $this->outbound->from_name);

        foreach ($this->outbound->to ?? [] as $recipient) {
            if (! empty($recipient['email'])) {
                $mailable->to($recipient['email'], $recipient['name'] ?? null);
            }
        }

        foreach ($this->outbound->cc ?? [] as $recipient) {
            if (! empty($recipient['email'])) {
                $mailable->cc($recipient['email'], $recipient['name'] ?? null);
            }
        }

        foreach ($this->outbound->bcc ?? [] as $recipient) {
            if (! empty($recipient['email'])) {
                $mailable->bcc($recipient['email'], $recipient['name'] ?? null);
            }
        }

        foreach ($this->outbound->reply_to ?? [] as $recipient) {
            if (! empty($recipient['email'])) {
                $mailable->replyTo($recipient['email'], $recipient['name'] ?? null);
            }
        }

        $mailable->withSymfonyMessage(function (Email $email): void {
            foreach ($this->outbound->headers ?? [] as $name => $value) {
                if (is_string($name) && $value !== null) {
                    $email->getHeaders()->addTextHeader($name, (string) $value);
                }
            }

            if ($this->outbound->body_html) {
                $email->html($this->outbound->body_html);
            }

            if ($this->outbound->body_text) {
                $email->text($this->outbound->body_text);
            }
        });

        foreach ($this->outbound->attachments ?? [] as $attachment) {
            $path = $attachment['path'] ?? null;

            if (! $path) {
                continue;
            }

            $disk = $attachment['disk'] ?? config('filesystems.default');
            $name = $attachment['name'] ?? basename($path);
            $mime = $attachment['mime_type'] ?? null;

            $mailable->attachFromStorageDisk($disk, $path, $name, array_filter([
                'mime' => $mime,
            ]));
        }

        return $mailable;
    }
}
