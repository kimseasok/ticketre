<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Requests\StoreSmtpOutboundMessageRequest;
use App\Http\Requests\UpdateSmtpOutboundMessageRequest;
use App\Http\Resources\SmtpOutboundMessageResource;
use App\Models\SmtpOutboundMessage;
use App\Services\SmtpOutboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SmtpOutboundMessageController extends Controller
{
    public function __construct(private readonly SmtpOutboundMessageService $service)
    {
    }

    public function index(Request $request): JsonResponse|AnonymousResourceCollection
    {
        if ($response = $this->guardAuthorization($request, 'viewAny', SmtpOutboundMessage::class)) {
            return $response;
        }

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                SmtpOutboundMessage::STATUS_QUEUED,
                SmtpOutboundMessage::STATUS_SENDING,
                SmtpOutboundMessage::STATUS_RETRYING,
                SmtpOutboundMessage::STATUS_SENT,
                SmtpOutboundMessage::STATUS_FAILED,
            ])],
            'ticket_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'correlation_id' => ['nullable', 'string', 'max:255'],
        ]);

        $messages = SmtpOutboundMessage::query()
            ->with(['ticket', 'message'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['ticket_id'] ?? null, fn ($query, $ticketId) => $query->where('ticket_id', $ticketId))
            ->when($filters['brand_id'] ?? null, fn ($query, $brandId) => $query->where('brand_id', $brandId))
            ->when($filters['correlation_id'] ?? null, fn ($query, $cid) => $query->where('correlation_id', $cid))
            ->orderByDesc('created_at')
            ->paginate();

        return SmtpOutboundMessageResource::collection($messages);
    }

    public function store(StoreSmtpOutboundMessageRequest $request): JsonResponse
    {
        if ($response = $this->guardAuthorization($request, 'create', SmtpOutboundMessage::class)) {
            return $response;
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $correlationId = $this->correlationId($request);

        $message = $this->service->queue($request->validated(), $user, $correlationId);

        return (new SmtpOutboundMessageResource($message->load(['ticket', 'message'])))
            ->additional(['meta' => ['correlation_id' => $correlationId]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('X-Correlation-ID', $correlationId);
    }

    public function show(Request $request, SmtpOutboundMessage $smtpOutboundMessage): JsonResponse|SmtpOutboundMessageResource
    {
        if ($response = $this->guardAuthorization($request, 'view', $smtpOutboundMessage)) {
            return $response;
        }

        return new SmtpOutboundMessageResource($smtpOutboundMessage->load(['ticket', 'message']));
    }

    public function update(UpdateSmtpOutboundMessageRequest $request, SmtpOutboundMessage $smtpOutboundMessage): JsonResponse
    {
        if ($response = $this->guardAuthorization($request, 'update', $smtpOutboundMessage)) {
            return $response;
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $correlationId = $this->correlationId($request);

        $updated = $this->service->update($smtpOutboundMessage, $request->validated(), $user, $correlationId);

        return (new SmtpOutboundMessageResource($updated->load(['ticket', 'message'])))
            ->additional(['meta' => ['correlation_id' => $correlationId]])
            ->response()
            ->header('X-Correlation-ID', $correlationId);
    }

    public function destroy(Request $request, SmtpOutboundMessage $smtpOutboundMessage): JsonResponse
    {
        if ($response = $this->guardAuthorization($request, 'delete', $smtpOutboundMessage)) {
            return $response;
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $correlationId = $this->correlationId($request);

        $this->service->delete($smtpOutboundMessage, $user, $correlationId);

        return response()->json(null, Response::HTTP_NO_CONTENT)
            ->header('X-Correlation-ID', $correlationId);
    }

    protected function correlationId(Request $request): string
    {
        $value = $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        return Str::limit($value, 64, '');
    }

    protected function guardAuthorization(Request $request, string $ability, mixed $arguments = null): ?JsonResponse
    {
        try {
            if ($arguments === null) {
                $this->authorize($ability);
            } else {
                $this->authorize($ability, $arguments);
            }
        } catch (AuthorizationException $exception) {
            return $this->authorizationErrorResponse($exception, $request);
        }

        return null;
    }

    protected function authorizationErrorResponse(AuthorizationException $exception, Request $request): JsonResponse
    {
        $correlationId = $this->correlationId($request);

        return response()->json([
            'error' => [
                'code' => 'ERR_HTTP_403',
                'message' => $exception->getMessage() ?: 'This action is unauthorized.',
            ],
        ], Response::HTTP_FORBIDDEN)
            ->header('X-Correlation-ID', $correlationId);
    }
}
