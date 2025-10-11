<?php

namespace App\Data;

use App\Models\PortalSession;
use Carbon\CarbonImmutable;

class PortalSessionTokens
{
    public function __construct(
        public readonly PortalSession $session,
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly CarbonImmutable $expiresAt,
        public readonly CarbonImmutable $refreshExpiresAt,
        public readonly string $correlationId
    ) {
    }
}
