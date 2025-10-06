<?php

namespace App\Jobs;

use App\Services\TicketDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessTicketDeletionRequestJob implements ShouldQueue
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

    public function __construct(public readonly int $requestId)
    {
        $this->onQueue('default');
    }

    public function handle(TicketDeletionService $service): void
    {
        $service->process($this->requestId);
    }

    public function failed(Throwable $exception): void
    {
        app(TicketDeletionService::class)->markFailed($this->requestId, $exception);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['ticket-deletion', 'request:'.$this->requestId];
    }
}
