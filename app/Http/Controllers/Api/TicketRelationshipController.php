<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRelationshipRequest;
use App\Http\Requests\UpdateTicketRelationshipRequest;
use App\Http\Resources\TicketRelationshipResource;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Services\TicketRelationshipService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TicketRelationshipController extends Controller
{
    public function __construct(private readonly TicketRelationshipService $service)
    {
    }

    public function index(Request $request, Ticket $ticket): AnonymousResourceCollection
    {
        $this->guardTicketContext($ticket);
        $this->authorizeForRequest($request, 'view', $ticket);

        $relationships = TicketRelationship::query()
            ->with(['relatedTicket'])
            ->where('primary_ticket_id', $ticket->getKey())
            ->latest()
            ->paginate();

        return TicketRelationshipResource::collection($relationships);
    }

    public function store(StoreTicketRelationshipRequest $request, Ticket $ticket): JsonResponse
    {
        $this->guardTicketContext($ticket);
        $this->authorizeForRequest($request, 'update', $ticket);

        try {
            $relationship = $this->service->create($ticket, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            $this->abortWithValidation($exception);
        }

        return TicketRelationshipResource::make($relationship->load('relatedTicket'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Ticket $ticket, TicketRelationship $relationship): TicketRelationshipResource
    {
        $this->guardTicketContext($ticket);
        $this->guardRelationship($ticket, $relationship);
        $this->authorizeForRequest($request, 'view', $ticket);

        return TicketRelationshipResource::make($relationship->load('relatedTicket'));
    }

    public function update(UpdateTicketRelationshipRequest $request, Ticket $ticket, TicketRelationship $relationship): TicketRelationshipResource
    {
        $this->guardTicketContext($ticket);
        $this->guardRelationship($ticket, $relationship);
        $this->authorizeForRequest($request, 'update', $ticket);

        try {
            $relationship = $this->service->update($relationship, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            $this->abortWithValidation($exception);
        }

        return TicketRelationshipResource::make($relationship);
    }

    public function destroy(Request $request, Ticket $ticket, TicketRelationship $relationship): JsonResponse
    {
        $this->guardTicketContext($ticket);
        $this->guardRelationship($ticket, $relationship);
        $this->authorizeForRequest($request, 'update', $ticket);

        $this->service->delete($relationship, $request->user());

        return response()->json([], 204);
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

    protected function guardRelationship(Ticket $ticket, TicketRelationship $relationship): void
    {
        if ((int) $relationship->primary_ticket_id !== (int) $ticket->getKey()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_404',
                    'message' => 'Relationship not found for ticket.',
                ],
            ], 404));
        }
    }

    protected function abortWithValidation(ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first() ?? 'Validation failed.';

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_VALIDATION',
                'message' => $message,
                'details' => $exception->errors(),
            ],
        ], 422));
    }
}
