<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageRepository $repository,
        private readonly MessageService $service
    ) {
    }

    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'viewAny', [Message::class, $ticket]);

        $visibility = $request->query('visibility');

        if (is_array($visibility)) {
            $this->throwValidationError('Visibility must be a string.');
        }

        if ($visibility && ! in_array($visibility, [Message::VISIBILITY_PUBLIC, Message::VISIBILITY_INTERNAL], true)) {
            $this->throwValidationError('Visibility must be public or internal.');
        }

        if ($visibility === Message::VISIBILITY_INTERNAL && ! $user->can('tickets.manage')) {
            $this->throwAuthorizationError('You are not authorized to view internal notes.');
        }

        $messages = $user->can('tickets.manage')
            ? $this->repository->forTicket($ticket, $visibility)
            : $this->repository->forPortal($ticket);

        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'create', [Message::class, $ticket]);

        $data = $request->validated();

        if ($data['visibility'] === Message::VISIBILITY_INTERNAL && ! $user->can('tickets.manage')) {
            $this->throwAuthorizationError('Only agents can create internal notes.');
        }

        $message = $this->service->create($ticket, [
            'visibility' => $data['visibility'],
            'author_role' => Message::ROLE_AGENT,
            'body' => $data['body'],
            'sent_at' => $data['sent_at'] ?? now(),
        ], $user)->load('author');

        return MessageResource::make($message)->response()->setStatusCode(201);
    }

    public function show(Request $request, Ticket $ticket, Message $message): MessageResource
    {
        $this->ensureMessageBelongsToTicket($ticket, $message);
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'view', $message);

        return MessageResource::make($message->load('author'));
    }

    public function update(UpdateMessageRequest $request, Ticket $ticket, Message $message): MessageResource
    {
        $this->ensureMessageBelongsToTicket($ticket, $message);
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'update', $message);

        $data = $request->validated();

        if (($data['visibility'] ?? null) === Message::VISIBILITY_INTERNAL && ! $user->can('tickets.manage')) {
            $this->throwAuthorizationError('Only agents can set internal visibility.');
        }

        $message = $this->service->update($message, $data, $user)->load('author');

        return MessageResource::make($message);
    }

    public function destroy(Request $request, Ticket $ticket, Message $message): JsonResponse
    {
        $this->ensureMessageBelongsToTicket($ticket, $message);
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'delete', $message);

        $this->service->delete($message, $user);

        return response()->json(null, 204);
    }

    protected function ensureMessageBelongsToTicket(Ticket $ticket, Message $message): void
    {
        if ($message->ticket_id !== $ticket->getKey()) {
            abort(404);
        }
    }

    protected function throwValidationError(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_VALIDATION',
                'message' => $message,
            ],
        ], 422));
    }

    protected function authorizeForRequest(Request $request, User $user, string $ability, mixed $arguments): void
    {
        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            $this->throwAuthorizationError($response->message() ?: 'This action is unauthorized.');
        }
    }

    protected function throwAuthorizationError(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_HTTP_403',
                'message' => $message,
            ],
        ], 403));
    }

    protected function resolveUser(Request $request): User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ],
        ], 401));
    }
}
