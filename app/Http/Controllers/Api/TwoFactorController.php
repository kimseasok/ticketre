<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TwoFactorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\BeginTwoFactorEnrollmentRequest;
use App\Http\Requests\ConfirmTwoFactorRequest;
use App\Http\Requests\RegenerateTwoFactorRecoveryCodesRequest;
use App\Http\Requests\TwoFactorChallengeRequest;
use App\Http\Resources\TwoFactorCredentialResource;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

    public function show(Request $request): JsonResponse|TwoFactorCredentialResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $credential = $this->credentialForUser($user);

        if (! $credential) {
            return $this->errorResponse(
                'ERR_2FA_NOT_ENROLLED',
                'Two-factor authentication has not been initiated for this user.',
                404,
                $this->correlationId($request)
            );
        }

        $this->authorize('view', $credential);

        $correlationId = $this->correlationId($request);

        $resource = (new TwoFactorCredentialResource($credential->loadMissing('recoveryCodes')))
            ->additional(['meta' => ['correlation_id' => $correlationId]]);

        return $this->withCorrelationId($resource->response(), $request, $correlationId)
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(BeginTwoFactorEnrollmentRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlationId = $this->correlationId($request);

        try {
            $result = $this->twoFactorService->startEnrollment($user, $request->validated()['label'] ?? null, $correlationId);
        } catch (TwoFactorException $exception) {
            return $this->errorResponse($exception->errorCode(), $exception->getMessage(), $exception->status(), $correlationId, $exception->context());
        }

        $resource = (new TwoFactorCredentialResource($result['credential']->loadMissing('recoveryCodes')))
            ->additional([
                'meta' => [
                    'secret' => $result['secret'],
                    'otpauth_url' => $result['uri'],
                    'correlation_id' => $correlationId,
                ],
            ]);

        return $this->withCorrelationId($resource->response(), $request, $correlationId)
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlationId = $this->correlationId($request);

        $credential = $this->credentialForUser($user);

        if (! $credential) {
            return $this->errorResponse('ERR_2FA_NOT_ENROLLED', 'Two-factor authentication has not been initiated for this user.', 404, $correlationId);
        }

        $this->authorize('update', $credential);

        try {
            $result = $this->twoFactorService->confirmEnrollment($credential, $request->validated()['code'], $user, $correlationId);
        } catch (TwoFactorException $exception) {
            return $this->errorResponse($exception->errorCode(), $exception->getMessage(), $exception->status(), $correlationId, $exception->context());
        }

        $resource = (new TwoFactorCredentialResource($result['credential']->loadMissing('recoveryCodes')))
            ->additional([
                'meta' => [
                    'recovery_codes' => $result['recovery_codes'],
                    'correlation_id' => $correlationId,
                ],
            ]);

        return $this->withCorrelationId($resource->response(), $request, $correlationId)
            ->setStatusCode(Response::HTTP_OK);
    }

    public function regenerate(RegenerateTwoFactorRecoveryCodesRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlationId = $this->correlationId($request);

        $credential = $this->credentialForUser($user);

        if (! $credential) {
            return $this->errorResponse('ERR_2FA_NOT_ENROLLED', 'Two-factor authentication has not been initiated for this user.', 404, $correlationId);
        }

        $this->authorize('update', $credential);

        try {
            $result = $this->twoFactorService->regenerateRecoveryCodes($credential, $user, $correlationId);
        } catch (TwoFactorException $exception) {
            return $this->errorResponse($exception->errorCode(), $exception->getMessage(), $exception->status(), $correlationId, $exception->context());
        }

        $resource = (new TwoFactorCredentialResource($result['credential']->loadMissing('recoveryCodes')))
            ->additional([
                'meta' => [
                    'recovery_codes' => $result['recovery_codes'],
                    'correlation_id' => $correlationId,
                ],
            ]);

        return $this->withCorrelationId($resource->response(), $request, $correlationId)
            ->setStatusCode(Response::HTTP_OK);
    }

    public function challenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $correlationId = $this->correlationId($request);
        $credential = $this->credentialForUser($user);

        if (! $credential) {
            return $this->errorResponse('ERR_2FA_NOT_ENROLLED', 'Two-factor authentication has not been initiated for this user.', 404, $correlationId);
        }

        $this->authorize('update', $credential);

        $data = $request->validated();

        try {
            $updated = $this->twoFactorService->verifyChallenge(
                $credential,
                $user,
                $correlationId,
                $data['code'] ?? null,
                $data['recovery_code'] ?? null
            );
        } catch (TwoFactorException $exception) {
            return $this->errorResponse($exception->errorCode(), $exception->getMessage(), $exception->status(), $correlationId, $exception->context());
        }

        $ttl = (int) config('security.two_factor.session_ttl_minutes', 30);
        $verifiedAt = now();
        $expiresAt = $verifiedAt->clone()->addMinutes($ttl);

        $this->storeSessionPass($request, $user->getKey(), $expiresAt);

        $resource = (new TwoFactorCredentialResource($updated->loadMissing('recoveryCodes')))
            ->additional([
                'meta' => [
                    'verified_at' => $verifiedAt->toAtomString(),
                    'expires_at' => $expiresAt->toAtomString(),
                    'correlation_id' => $correlationId,
                ],
            ]);

        return $this->withCorrelationId($resource->response(), $request, $correlationId)
            ->setStatusCode(Response::HTTP_OK);
    }

    private function errorResponse(string $code, string $message, int $status, string $correlationId, array $context = []): JsonResponse
    {
        return response()
            ->json([
                'error' => array_merge([
                    'code' => $code,
                    'message' => $message,
                    'correlation_id' => $correlationId,
                ], $context ? ['context' => $context] : []),
            ], $status)
            ->header('X-Correlation-ID', $correlationId);
    }

    private function correlationId(Request $request): string
    {
        $header = trim((string) $request->headers->get('X-Correlation-ID'));

        if ($header !== '') {
            return $header;
        }

        return (string) str()->uuid();
    }

    private function withCorrelationId(JsonResponse $response, Request $request, ?string $correlationId = null): JsonResponse
    {
        $correlationId = $correlationId ?? $this->correlationId($request);

        if (! $response->headers->has('X-Correlation-ID')) {
            $response->headers->set('X-Correlation-ID', $correlationId);
        }

        return $response;
    }

    private function storeSessionPass(Request $request, int $userId, Carbon $expiresAt): void
    {
        $sessionKey = 'two_factor_verified_'.$userId;
        $request->session()->put($sessionKey, $expiresAt->toAtomString());
    }

    private function credentialForUser(User $user): ?TwoFactorCredential
    {
        return TwoFactorCredential::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->getKey())
            ->with('recoveryCodes')
            ->first();
    }
}
