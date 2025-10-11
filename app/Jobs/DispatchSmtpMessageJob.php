<?php

namespace App\Jobs;

use App\Services\SmtpOutboundMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchSmtpMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 360];

    public function __construct(public readonly int $messageId, public readonly string $correlationId)
    {
        $this->onQueue('email');
    }

    public function handle(SmtpOutboundMessageService $service): void
    {
        $service->send($this->messageId, $this->correlationId);
    }

    public function failed(Throwable $exception): void
    {
        app(SmtpOutboundMessageService::class)->markFailed($this->messageId, $exception, $this->correlationId);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['smtp', 'smtp-outbound:'.$this->messageId];
    }
}
