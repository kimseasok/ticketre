<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\StoreTicketEventRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketEventResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Services\TicketLifecycleBroadcaster;
use App\Services\TicketService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeForRequest($request, 'viewAny', Ticket::class);

        $tickets = Ticket::query()->with(['assignee', 'contact', 'company'])->latest()->paginate();

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['metadata'] = $request->sanitizedMetadata() ?? [];
        $data['custom_fields'] = $request->sanitizedCustomFields() ?? [];
        $data['channel'] = Ticket::CHANNEL_API;

        $correlationId = Str::limit($request->headers->get('X-Correlation-ID') ?: (string) Str::uuid(), 64, '');

        $ticket = $this->service->create($data, $request->user(), $correlationId);

        return TicketResource::make($ticket)->response()->setStatusCode(201);
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        $this->guardTicketContext($ticket);

        $this->authorizeForRequest($request, 'view', $ticket);

        return TicketResource::make($ticket->load(['assignee', 'contact', 'company']));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        $this->guardTicketContext($ticket);

        $data = $request->validated();
        if (($customFields = $request->sanitizedCustomFields(true)) !== null) {
            $data['custom_fields'] = $customFields;
        }

        if (($metadata = $request->sanitizedMetadata(true)) !== null) {
            $data['metadata'] = $metadata;
        }

        try {
            $ticket = $this->service->update($ticket, $data, $request->user());
        } catch (ValidationException $exception) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_VALIDATION',
                    'message' => $exception->getMessage() ?: 'Validation failed.',
                    'details' => $exception->errors(),
                ],
            ], 422));
        }

        return TicketResource::make($ticket);
    }

    public function events(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $this->guardTicketContext($ticket);

        $this->authorizeForRequest($request, 'view', $ticket);

        $events = TicketEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->latest('broadcasted_at')
            ->paginate();

        return TicketEventResource::collection($events);
    }

    public function storeEvent(StoreTicketEventRequest $request, Ticket $ticket, TicketLifecycleBroadcaster $broadcaster): JsonResponse
    {
        $this->guardTicketContext($ticket);

        $this->authorizeForRequest($request, 'view', $ticket);

        $data = $request->validated();

        $record = $broadcaster->record(
            $ticket->fresh(),
            $data['type'],
            $data['payload'] ?? [],
            $request->user(),
            $data['visibility'] ?? TicketEvent::VISIBILITY_INTERNAL
        );

        return TicketEventResource::make($record)->response()->setStatusCode(201);
    }

    protected function authorizeForRequest(Request $request, string $ability, mixed $arguments): void
    {
        $response = Gate::forUser($request->user())->inspect($ability, $arguments);

        if (! $response->allowed()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $response->message() ?: 'This action is unauthorized.',
                ],
            ], 403));
        }
    }

    protected function guardTicketContext(Ticket $ticket): void
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if ($tenant && (int) $ticket->tenant_id !== (int) $tenant->getKey()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_404',
                    'message' => 'Ticket not found.',
                ],
            ], 404));
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if ($brand && (int) $ticket->brand_id !== (int) $brand->getKey()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_404',
                    'message' => 'Ticket not found.',
                ],
            ], 404));
        }
    }
}
