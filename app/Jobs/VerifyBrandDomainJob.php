<?php

namespace App\Jobs;

use App\Models\BrandDomain;
use App\Models\User;
use App\Services\BrandDomainVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyBrandDomainJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    public int $backoff;

    public function __construct(
        public readonly int $brandDomainId,
        public readonly ?int $actorId,
        public readonly string $correlationId,
    ) {
        $this->tries = (int) config('branding.verification.max_attempts', 3);
        $this->backoff = (int) config('branding.verification.retry_seconds', 60);
    }

    public function handle(BrandDomainVerificationService $verificationService): void
    {
        $domain = BrandDomain::query()->find($this->brandDomainId);

        if (! $domain) {
            return;
        }

        $actor = $this->actorId ? User::query()->find($this->actorId) : null;

        $verificationService->verify($domain, $actor, $this->correlationId);
    }

    public function backoff(): int
    {
        return (int) config('branding.verification.retry_seconds', 60);
    }

    public function failed(Throwable $exception): void
    {
        $domain = BrandDomain::query()->find($this->brandDomainId);

        if (! $domain) {
            return;
        }

        $domain->forceFill([
            'status' => 'failed',
            'verification_error' => 'job_failed',
            'ssl_error' => 'job_failed',
        ])->save();

        Log::channel(config('logging.default'))->error('brand_domain.verify.job_failed', [
            'brand_domain_id' => $domain->getKey(),
            'tenant_id' => $domain->tenant_id,
            'brand_id' => $domain->brand_id,
            'domain_digest' => $domain->domainDigest(),
            'correlation_id' => $this->correlationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
