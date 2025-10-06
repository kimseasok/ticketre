<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ListTicketMessagesRequest;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TicketMessageController extends Controller
{
    public function __construct(
        private readonly MessageRepository $messages,
        private readonly AuditLogger $auditLogger
    ) {
    }

    public function index(ListTicketMessagesRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $audience = $request->validated()['audience'] ?? 'agent';

        if ($user && $user->cannot('messages.view')) {
            return $this->errorResponse('ERR_FORBIDDEN', 'You are not allowed to view messages.', Response::HTTP_FORBIDDEN);
        }

        if ($response = $this->guardTicketAccess($ticket, $user)) {
            return $response;
        }

        $collection = $this->messages->queryForTicket($ticket, $audience)->get();

        Log::info('ticket.messages.listed', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'audience' => $audience,
            'count' => $collection->count(),
            'actor_id' => $user?->getKey(),
            'actor_role' => $user?->getRoleNames()->first(),
        ]);

        return MessageResource::collection($collection)->response();
    }

    public function store(StoreTicketMessageRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ($response = $this->guardTicketAccess($ticket, $user)) {
            return $response;
        }

        $data = $request->validated();

        $message = new Message([
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'ticket_id' => $ticket->getKey(),
            'user_id' => $user?->getKey(),
            'visibility' => $data['visibility'] ?? Message::VISIBILITY_PUBLIC,
            'body' => $data['body'],
            'sent_at' => $data['sent_at'] ?? now(),
        ]);

        $message->save();
        $message->load('author');

        $this->auditLogger->log($user, 'ticket.message.created', $message, [
            'visibility' => $message->visibility,
            'body' => $message->body,
        ]);

        Log::info('ticket.message.created', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'message_id' => $message->getKey(),
            'visibility' => $message->visibility,
            'body_length' => mb_strlen($message->body),
            'actor_id' => $user?->getKey(),
            'actor_role' => $user?->getRoleNames()->first(),
        ]);

        return MessageResource::make($message)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function guardTicketAccess(Ticket $ticket, ?User $user): ?JsonResponse
    {
        if (! $user) {
            return $this->errorResponse('ERR_UNAUTHENTICATED', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if ((int) $user->tenant_id !== (int) $ticket->tenant_id) {
            return $this->errorResponse('ERR_NOT_FOUND', 'Ticket not found.', Response::HTTP_NOT_FOUND);
        }

        if ($ticket->brand_id && $user->brand_id && (int) $user->brand_id !== (int) $ticket->brand_id) {
            return $this->errorResponse('ERR_NOT_FOUND', 'Ticket not found.', Response::HTTP_NOT_FOUND);
        }

        return null;
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
