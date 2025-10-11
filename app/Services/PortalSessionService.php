<?php

namespace App\Services;

use App\Data\PortalSessionTokens;
use App\Models\PortalAccount;
use App\Models\PortalSession;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class PortalSessionService
{
    public function __construct(private readonly PortalSessionAuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function login(array $data, string $ipAddress, ?string $userAgent, ?string $correlationId = null): PortalSessionTokens
    {
        $startedAt = microtime(true);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        /** @var PortalAccount|null $account */
        $account = PortalAccount::query()->where('email', $email)->first();

        if (! $account) {
            $this->logDenied('account_not_found', null, $email, $ipAddress, $correlationId);

            throw new AuthenticationException('Invalid credentials.');
        }

        if (! $account->isActive()) {
            $this->logDenied('account_inactive', $account, $email, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal account is disabled.');
        }

        $brandId = $this->brandIdForAccount($account);

        if (! Hash::check($password, $account->password)) {
            $this->logDenied('password_mismatch', $account, $email, $ipAddress, $correlationId);

            throw new AuthenticationException('Invalid credentials.');
        }

        $tokens = $this->issueTokens(
            $account,
            $ipAddress,
            $userAgent,
            $correlationId,
            $data['device_name'] ?? null,
            $startedAt,
            $brandId
        );

        return $tokens;
    }

    public function issueForAccount(PortalAccount $account, string $ipAddress, ?string $userAgent, ?string $correlationId = null, ?string $deviceName = null): PortalSessionTokens
    {
        $startedAt = microtime(true);
        $brandId = $this->brandIdForAccount($account, allowMissingBrand: true);

        return $this->issueTokens($account, $ipAddress, $userAgent, $correlationId, $deviceName, $startedAt, $brandId);
    }

    public function refresh(string $refreshToken, string $ipAddress, ?string $userAgent, ?string $correlationId = null, ?string $deviceName = null): PortalSessionTokens
    {
        $startedAt = microtime(true);
        $hash = hash('sha256', $refreshToken);

        /** @var PortalSession|null $session */
        $session = PortalSession::query()->with('account')->where('refresh_token_hash', $hash)->first();

        if (! $session) {
            $this->logDenied('refresh_not_found', null, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Invalid refresh token.');
        }

        $this->assertSessionBrandContext($session);

        if ($session->revoked_at) {
            $this->logDenied('session_revoked', $session->account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal session has been revoked.');
        }

        if ($session->refresh_expires_at && $session->refresh_expires_at->isPast()) {
            $this->logDenied('refresh_expired', $session->account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Refresh token expired.');
        }

        $account = $session->account;
        if (! $account) {
            $this->logDenied('account_missing', null, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal account missing for session.');
        }

        if (! $account->isActive()) {
            $this->logDenied('account_inactive', $account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal account is disabled.');
        }

        $abilities = $this->resolveAbilities($account);
        $correlation = $this->resolveCorrelationId($correlationId);
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addSeconds($this->accessTokenTtl());
        $refreshExpiresAt = $issuedAt->addSeconds($this->refreshTokenTtl());
        $previousAccessTokenId = (string) $session->access_token_id;
        $newAccessTokenId = Str::uuid()->toString();
        $newRefreshToken = Str::random(96);
        $refreshHash = hash('sha256', $newRefreshToken);
        $ipHash = $ipAddress ? hash('sha256', $ipAddress) : $session->ip_hash;
        $userAgentValue = $userAgent ? Str::limit($userAgent, 255, '') : $session->user_agent;
        $metadata = $this->mergeMetadata(
            $session->metadata ?? [],
            array_filter([
                'device_name' => $deviceName,
                'last_ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
                'last_user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
            ], static fn ($value) => $value !== null)
        );

        DB::transaction(function () use ($session, $issuedAt, $expiresAt, $refreshExpiresAt, $newAccessTokenId, $refreshHash, $ipHash, $userAgentValue, $metadata, $correlation, $abilities) {
            $session->forceFill([
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'refresh_expires_at' => $refreshExpiresAt,
                'access_token_id' => $newAccessTokenId,
                'refresh_token_hash' => $refreshHash,
                'last_used_at' => $issuedAt,
                'ip_hash' => $ipHash,
                'user_agent' => $userAgentValue,
                'metadata' => $metadata,
                'correlation_id' => $correlation,
                'abilities' => $abilities,
            ])->save();
        });

        $session->refresh()->load('account');

        $accessToken = $this->encodeAccessToken($session, $account, $abilities, $issuedAt, $expiresAt, $correlation);

        $this->auditLogger->refreshed($session, null, $previousAccessTokenId, $startedAt, $correlation);

        return new PortalSessionTokens($session, $accessToken, $newRefreshToken, $expiresAt, $refreshExpiresAt, $correlation);
    }

    public function logout(PortalSession $session, string $ipAddress, ?string $userAgent, ?string $correlationId = null): string
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        if ($session->revoked_at) {
            return $correlation;
        }

        $metadata = $this->mergeMetadata($session->metadata ?? [], [
            'revoked_reason' => 'logout',
            'last_ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : ($session->metadata['last_ip_hash'] ?? null),
            'last_user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : ($session->metadata['last_user_agent_hash'] ?? null),
        ]);

        DB::transaction(function () use ($session, $metadata, $correlation) {
            $session->forceFill([
                'revoked_at' => now(),
                'metadata' => $metadata,
                'correlation_id' => $correlation,
            ])->save();
        });

        $session->refresh()->load('account');

        $this->auditLogger->revoked($session, null, 'logout', $startedAt, $correlation);

        return $correlation;
    }

    public function revoke(PortalSession $session, User $actor, ?string $correlationId = null, string $reason = 'admin'): string
    {
        $startedAt = microtime(true);
        $correlation = $this->resolveCorrelationId($correlationId);

        if ($session->revoked_at) {
            return $correlation;
        }

        $metadata = $this->mergeMetadata($session->metadata ?? [], [
            'revoked_reason' => $reason,
            'revoked_by' => $actor->getKey(),
        ]);

        DB::transaction(function () use ($session, $metadata, $correlation) {
            $session->forceFill([
                'revoked_at' => now(),
                'metadata' => $metadata,
                'correlation_id' => $correlation,
            ])->save();
        });

        $session->refresh()->load('account');

        $this->auditLogger->revoked($session, $actor, $reason, $startedAt, $correlation);

        return $correlation;
    }

    public function validateAccessToken(string $token, string $ipAddress, ?string $userAgent, string $correlationId): PortalSession
    {
        try {
            $decoded = $this->decodeToken($token);
        } catch (AuthenticationException $e) {
            $this->logDenied('token_invalid', null, null, $ipAddress, $correlationId);

            throw $e;
        }

        $payload = $decoded['payload'];
        $sessionId = isset($payload['sid']) ? (int) $payload['sid'] : null;
        $accessTokenId = $payload['jti'] ?? null;

        if (! $sessionId || ! $accessTokenId) {
            $this->logDenied('token_missing_claims', null, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Invalid token.');
        }

        /** @var PortalSession|null $session */
        $session = PortalSession::query()->with('account')->find($sessionId);

        if (! $session) {
            $this->logDenied('session_not_found', null, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal session not found.');
        }

        $this->assertSessionBrandContext($session);

        if ($session->revoked_at) {
            $this->logDenied('session_revoked', $session->account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal session has been revoked.');
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $this->logDenied('session_expired', $session->account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal token expired.');
        }

        if (! hash_equals((string) $session->access_token_id, (string) $accessTokenId)) {
            $this->logDenied('token_mismatch', $session->account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal token revoked.');
        }

        $account = $session->account;
        if (! $account || ! $account->isActive()) {
            $this->logDenied('account_inactive', $account, null, $ipAddress, $correlationId);

            throw new AuthenticationException('Portal account disabled.');
        }

        $abilities = $session->abilities ?? [];
        if (! in_array('portal.access', $abilities, true)) {
            $abilities = $this->resolveAbilities($account);
            $session->abilities = $abilities;
        }

        $ipHash = $ipAddress ? hash('sha256', $ipAddress) : $session->ip_hash;
        $userAgentValue = $userAgent ? Str::limit($userAgent, 255, '') : $session->user_agent;
        $metadata = $this->mergeMetadata(
            $session->metadata ?? [],
            array_filter([
                'last_ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
                'last_user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
            ], static fn ($value) => $value !== null)
        );

        DB::transaction(function () use ($session, $ipHash, $userAgentValue, $metadata, $correlationId, $abilities) {
            $session->forceFill([
                'last_used_at' => now(),
                'ip_hash' => $ipHash,
                'user_agent' => $userAgentValue,
                'metadata' => $metadata,
                'correlation_id' => $correlationId,
                'abilities' => $abilities,
            ])->save();
        });

        return $session->refresh()->load('account');
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAbilities(PortalAccount $account): array
    {
        $permissions = $account->getAllPermissions()->pluck('name')->values()->all();
        $permissions = array_values(array_unique($permissions));

        if (! in_array('portal.access', $permissions, true)) {
            $this->logDenied('ability_missing', $account, $account->email, request()?->ip() ?? '127.0.0.1', request()?->header('X-Correlation-ID'));

            throw new AuthenticationException('Portal access is not granted for this account.');
        }

        return $permissions;
    }

    protected function accessTokenTtl(): int
    {
        return (int) config('portal.auth.access_token_ttl', 900);
    }

    protected function refreshTokenTtl(): int
    {
        return (int) config('portal.auth.refresh_token_ttl', 1209600);
    }

    protected function encodeAccessToken(PortalSession $session, PortalAccount $account, array $abilities, CarbonImmutable $issuedAt, CarbonImmutable $expiresAt, string $correlationId): string
    {
        $header = [
            'alg' => config('portal.auth.algorithm', 'HS256'),
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => config('portal.auth.issuer', config('app.url')),
            'sub' => (string) $account->getKey(),
            'aud' => 'portal',
            'jti' => (string) $session->access_token_id,
            'sid' => (string) $session->getKey(),
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'tenant_id' => $session->tenant_id,
            'brand_id' => $session->brand_id,
            'abilities' => $abilities,
            'correlation_id' => $correlationId,
            'type' => 'access',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $this->signingKey(), true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signatureEncoded;
    }

    /**
     * @return array{header: array<string, mixed>, payload: array<string, mixed>}
     */
    protected function decodeToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new AuthenticationException('Malformed token.');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        try {
            $header = json_decode($this->base64UrlDecode($headerEncoded), true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AuthenticationException('Malformed token.', $e->getCode(), $e);
        }

        $expectedSignature = hash_hmac('sha256', $headerEncoded.'.'.$payloadEncoded, $this->signingKey(), true);
        $providedSignature = $this->base64UrlDecode($signatureEncoded);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new AuthenticationException('Invalid token signature.');
        }

        if (($header['alg'] ?? null) !== config('portal.auth.algorithm', 'HS256')) {
            throw new AuthenticationException('Unsupported token algorithm.');
        }

        $now = CarbonImmutable::now()->getTimestamp();
        if (isset($payload['exp']) && (int) $payload['exp'] < $now) {
            throw new AuthenticationException('Portal token expired.');
        }

        return ['header' => $header, 'payload' => $payload];
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new AuthenticationException('Malformed token.');
        }

        return $decoded;
    }

    protected function signingKey(): string
    {
        $secret = config('portal.auth.jwt_secret');

        if (! $secret) {
            $secret = config('app.key');
        }

        if (! $secret) {
            throw new RuntimeException('Portal JWT secret is not configured.');
        }

        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7));
        }

        return (string) $secret;
    }

    protected function resolveCorrelationId(?string $value): string
    {
        $header = request()?->header('X-Correlation-ID');
        $candidate = $value ?? $header ?? (string) Str::uuid();

        return Str::limit($candidate, 64, '');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    protected function mergeMetadata(array $metadata, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if ($value === null) {
                continue;
            }

            $metadata[$key] = $value;
        }

        return $metadata;
    }

    protected function brandIdForAccount(PortalAccount $account, bool $allowMissingBrand = false): ?int
    {
        $currentBrand = app()->bound('currentBrand') ? app('currentBrand') : null;

        if ($account->brand_id && $currentBrand && (int) $account->brand_id !== (int) $currentBrand->getKey()) {
            $this->logDenied('brand_mismatch', $account, $account->email, request()?->ip() ?? '127.0.0.1', request()?->header('X-Correlation-ID'));

            throw new AuthenticationException('Portal account is not enabled for this brand.');
        }

        if ($account->brand_id && ! $currentBrand) {
            return (int) $account->brand_id;
        }

        if ($currentBrand) {
            return (int) $currentBrand->getKey();
        }

        if ($account->brand_id && ! $allowMissingBrand) {
            return (int) $account->brand_id;
        }

        return $account->brand_id ? (int) $account->brand_id : null;
    }

    protected function issueTokens(PortalAccount $account, string $ipAddress, ?string $userAgent, ?string $correlationId, ?string $deviceName, float $startedAt, ?int $brandId): PortalSessionTokens
    {
        $abilities = $this->resolveAbilities($account);
        $correlation = $this->resolveCorrelationId($correlationId);
        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addSeconds($this->accessTokenTtl());
        $refreshExpiresAt = $issuedAt->addSeconds($this->refreshTokenTtl());
        $accessTokenId = Str::uuid()->toString();
        $refreshToken = Str::random(96);
        $refreshHash = hash('sha256', $refreshToken);
        $ipHash = $ipAddress ? hash('sha256', $ipAddress) : null;
        $userAgentValue = $userAgent ? Str::limit($userAgent, 255, '') : null;
        $metadata = array_filter([
            'device_name' => $deviceName,
            'last_ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
            'last_user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
        ], static fn ($value) => $value !== null);

        /** @var PortalSession $session */
        $session = DB::transaction(function () use ($account, $brandId, $accessTokenId, $refreshHash, $abilities, $ipHash, $userAgentValue, $issuedAt, $expiresAt, $refreshExpiresAt, $metadata, $correlation) {
            $session = PortalSession::create([
                'tenant_id' => $account->tenant_id,
                'brand_id' => $brandId,
                'portal_account_id' => $account->getKey(),
                'access_token_id' => $accessTokenId,
                'refresh_token_hash' => $refreshHash,
                'abilities' => $abilities,
                'ip_hash' => $ipHash,
                'user_agent' => $userAgentValue,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'refresh_expires_at' => $refreshExpiresAt,
                'last_used_at' => $issuedAt,
                'correlation_id' => $correlation,
                'metadata' => $metadata,
            ]);

            $account->forceFill(['last_login_at' => $issuedAt])->save();

            return $session;
        });

        $session->load('account');

        $accessToken = $this->encodeAccessToken($session, $account, $abilities, $issuedAt, $expiresAt, $correlation);

        $this->auditLogger->issued($session, null, $startedAt, $correlation);

        return new PortalSessionTokens($session, $accessToken, $refreshToken, $expiresAt, $refreshExpiresAt, $correlation);
    }

    protected function assertSessionBrandContext(PortalSession $session): void
    {
        $currentBrand = app()->bound('currentBrand') ? app('currentBrand') : null;

        if ($session->brand_id && $currentBrand && (int) $session->brand_id !== (int) $currentBrand->getKey()) {
            throw new AuthenticationException('Portal session does not match active brand.');
        }
    }

    protected function logDenied(string $reason, ?PortalAccount $account, ?string $email, string $ipAddress, ?string $correlationId): void
    {
        Log::warning('portal.session.denied', [
            'reason' => $reason,
            'portal_account_id' => $account?->getKey(),
            'tenant_id' => $account?->tenant_id,
            'brand_id' => $account?->brand_id,
            'email_hash' => $email ? hash('sha256', strtolower($email)) : ($account ? hash('sha256', strtolower($account->email)) : null),
            'ip_hash' => $ipAddress ? hash('sha256', $ipAddress) : null,
            'correlation_id' => $this->resolveCorrelationId($correlationId),
        ]);
    }
}
