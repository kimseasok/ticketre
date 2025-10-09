<?php

namespace App\Services;

use App\Exceptions\TwoFactorException;
use App\Models\TwoFactorCredential;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OTPHP\TOTP;

class TwoFactorService
{
    public function __construct(private readonly TwoFactorAuditLogger $auditLogger)
    {
    }

    /**
     * @return array{credential: TwoFactorCredential, secret: string, uri: string}
     */
    public function startEnrollment(User $user, ?string $label, string $correlationId): array
    {
        $startedAt = microtime(true);
        $totp = TOTP::create(null, 30, 'sha1', 6);
        $secret = $totp->getSecret();
        $totp = $this->makeTotp($secret, $user, $label);

        $credential = DB::transaction(function () use ($user, $label, $secret): TwoFactorCredential {
            $credential = TwoFactorCredential::withTrashed()->firstOrNew([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->getKey(),
            ]);

            if ($credential->trashed()) {
                $credential->restore();
            }

            $credential->fill([
                'tenant_id' => $user->tenant_id,
                'brand_id' => $user->brand_id,
                'label' => $label,
                'secret' => Crypt::encryptString($secret),
                'confirmed_at' => null,
                'last_verified_at' => null,
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();

            $credential->recoveryCodes()->delete();

            return $credential;
        });

        $this->auditLogger->enrollmentStarted($credential, $user, $startedAt, $correlationId);

        Log::channel(config('logging.default'))->info('two_factor.enrollment_initiated', [
            'two_factor_id' => $credential->getKey(),
            'tenant_id' => $user->tenant_id,
            'brand_id' => $user->brand_id,
            'user_id' => $user->getKey(),
            'correlation_id' => $correlationId,
            'context' => 'two_factor',
        ]);

        return [
            'credential' => $credential->fresh(),
            'secret' => $secret,
            'uri' => $totp->getProvisioningUri(),
        ];
    }

    /**
     * @return array{credential: TwoFactorCredential, recovery_codes: array<int, string>}
     */
    public function confirmEnrollment(TwoFactorCredential $credential, string $code, User $actor, string $correlationId): array
    {
        $this->ensureNotLocked($credential, $correlationId);

        $startedAt = microtime(true);
        $secret = $credential->decryptedSecret();
        $totp = $this->makeTotp($secret, $actor, $credential->label);

        if (! $totp->verify($code)) {
            $locked = $this->recordFailure($credential, $correlationId);

            if ($locked) {
                $fresh = $credential->fresh();

                throw new TwoFactorException(
                    'ERR_2FA_LOCKED',
                    'Two-factor authentication is temporarily locked.',
                    423,
                    ['locked_until' => $fresh->locked_until?->toAtomString()]
                );
            }

            throw new TwoFactorException('ERR_2FA_INVALID_CODE', 'Invalid authentication code provided.');
        }

        $codes = DB::transaction(function () use ($credential): array {
            $credential->forceFill([
                'confirmed_at' => now(),
                'last_verified_at' => now(),
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();

            $credential->recoveryCodes()->delete();

            $codes = $this->generateRecoveryCodes();
            foreach ($codes as $rawCode) {
                $credential->recoveryCodes()->create([
                    'code_hash' => Hash::make($rawCode),
                ]);
            }

            return $codes;
        });

        $fresh = $credential->fresh(['recoveryCodes']);

        $this->auditLogger->enrollmentConfirmed($fresh, $actor, $codes, $startedAt, $correlationId);

        return [
            'credential' => $fresh,
            'recovery_codes' => $codes,
        ];
    }

    /**
     * @return array{credential: TwoFactorCredential, recovery_codes: array<int, string>}
     */
    public function regenerateRecoveryCodes(TwoFactorCredential $credential, User $actor, string $correlationId): array
    {
        if (! $credential->isConfirmed()) {
            throw new TwoFactorException('ERR_2FA_NOT_CONFIRMED', 'Two-factor authentication has not been confirmed.', 409);
        }

        $startedAt = microtime(true);

        $codes = DB::transaction(function () use ($credential): array {
            $credential->recoveryCodes()->delete();
            $codes = $this->generateRecoveryCodes();

            foreach ($codes as $rawCode) {
                $credential->recoveryCodes()->create([
                    'code_hash' => Hash::make($rawCode),
                ]);
            }

            return $codes;
        });

        $fresh = $credential->fresh(['recoveryCodes']);

        $this->auditLogger->recoveryCodesRegenerated($fresh, $actor, $startedAt, $correlationId, count($codes));

        return [
            'credential' => $fresh,
            'recovery_codes' => $codes,
        ];
    }

    public function verifyChallenge(
        TwoFactorCredential $credential,
        User $actor,
        string $correlationId,
        ?string $code = null,
        ?string $recoveryCode = null
    ): TwoFactorCredential {
        if (! $credential->isConfirmed()) {
            throw new TwoFactorException('ERR_2FA_NOT_CONFIRMED', 'Two-factor authentication has not been confirmed.', 409);
        }

        $this->ensureNotLocked($credential, $correlationId);

        $startedAt = microtime(true);

        if ($code !== null) {
            $secret = $credential->decryptedSecret();
            $totp = $this->makeTotp($secret, $actor, $credential->label);

            if (! $totp->verify($code)) {
                $locked = $this->recordFailure($credential, $correlationId);

                if ($locked) {
                    $fresh = $credential->fresh();

                    throw new TwoFactorException(
                        'ERR_2FA_LOCKED',
                        'Two-factor authentication is temporarily locked.',
                        423,
                        ['locked_until' => $fresh->locked_until?->toAtomString()]
                    );
                }

                throw new TwoFactorException('ERR_2FA_INVALID_CODE', 'Invalid authentication code provided.');
            }

            $updated = $this->markSuccessfulChallenge($credential);
            $this->auditLogger->challengeVerified($updated, $actor, $startedAt, $correlationId, 'totp');

            return $updated;
        }

        if ($recoveryCode !== null) {
            $match = $credential->recoveryCodes()
                ->whereNull('used_at')
                ->get()
                ->first(fn ($codeModel) => Hash::check($recoveryCode, $codeModel->code_hash));

            if (! $match) {
                $locked = $this->recordFailure($credential, $correlationId);

                if ($locked) {
                    $fresh = $credential->fresh();

                    throw new TwoFactorException(
                        'ERR_2FA_LOCKED',
                        'Two-factor authentication is temporarily locked.',
                        423,
                        ['locked_until' => $fresh->locked_until?->toAtomString()]
                    );
                }

                throw new TwoFactorException('ERR_2FA_INVALID_CODE', 'Invalid recovery code provided.');
            }

            DB::transaction(function () use ($match, $credential): void {
                $match->forceFill(['used_at' => now()])->save();

                $credential->forceFill([
                    'last_verified_at' => now(),
                    'failed_attempts' => 0,
                    'locked_until' => null,
                ])->save();
            });

            $updated = $credential->fresh(['recoveryCodes']);
            $this->auditLogger->challengeVerified($updated, $actor, $startedAt, $correlationId, 'recovery');

            return $updated;
        }

        throw new TwoFactorException('ERR_VALIDATION', 'An authentication code or recovery code is required.');
    }

    protected function markSuccessfulChallenge(TwoFactorCredential $credential): TwoFactorCredential
    {
        DB::transaction(function () use ($credential): void {
            $credential->forceFill([
                'last_verified_at' => now(),
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();
        });

        return $credential->fresh(['recoveryCodes']);
    }

    protected function ensureNotLocked(TwoFactorCredential $credential, string $correlationId): void
    {
        if ($credential->isLocked()) {
            Log::warning('two_factor.locked', [
                'two_factor_id' => $credential->getKey(),
                'user_id' => $credential->user_id,
                'locked_until' => $credential->locked_until?->toAtomString(),
                'correlation_id' => $correlationId,
            ]);

            throw new TwoFactorException(
                'ERR_2FA_LOCKED',
                'Two-factor authentication is temporarily locked.',
                423,
                ['locked_until' => $credential->locked_until?->toAtomString()]
            );
        }
    }

    protected function recordFailure(TwoFactorCredential $credential, string $correlationId): bool
    {
        $maxAttempts = (int) config('security.two_factor.max_attempts', 5);
        $lockoutMinutes = (int) config('security.two_factor.lockout_minutes', 5);

        DB::transaction(function () use ($credential, $maxAttempts, $lockoutMinutes): void {
            $attempts = $credential->failed_attempts + 1;

            if ($attempts >= $maxAttempts) {
                $credential->forceFill([
                    'failed_attempts' => 0,
                    'locked_until' => now()->addMinutes($lockoutMinutes),
                ])->save();

                return;
            }

            $credential->forceFill(['failed_attempts' => $attempts])->save();
        });

        $fresh = $credential->fresh();
        $remaining = max(0, $maxAttempts - $fresh->failed_attempts);

        $context = [
            'two_factor_id' => $credential->getKey(),
            'user_id' => $credential->user_id,
            'tenant_id' => $credential->tenant_id,
            'remaining_attempts' => $remaining,
            'locked_until' => $fresh->locked_until?->toAtomString(),
            'correlation_id' => $correlationId,
        ];

        Log::warning('two_factor.failed_attempt', $context);

        return $fresh->isLocked();
    }

    /**
     * @return array<int, string>
     */
    protected function generateRecoveryCodes(): array
    {
        $count = (int) config('security.two_factor.recovery_codes', 10);

        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(10)))
            ->toArray();
    }

    protected function makeTotp(string $secret, User $user, ?string $label = null): TOTP
    {
        $totp = TOTP::create($secret, 30, 'sha1', 6);
        $totp->setLabel($label ?: $user->email);
        $totp->setIssuer(config('app.name', 'Ticketre'));

        return $totp;
    }
}
