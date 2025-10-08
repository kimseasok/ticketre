<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRelationshipRequest;
use App\Http\Requests\UpdateTicketRelationshipRequest;
use App\Http\Resources\TicketRelationshipResource;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', TicketRelationship::class);

        $query = TicketRelationship::query()->with(['primaryTicket', 'relatedTicket', 'creator']);

        if ($type = $request->query('relationship_type')) {
            $query->where('relationship_type', $type);
        }

        if ($primary = $request->query('primary_ticket_id')) {
            $query->where('primary_ticket_id', (int) $primary);
        }

        if ($related = $request->query('related_ticket_id')) {
            $query->where('related_ticket_id', (int) $related);
        }

        $relationships = $query->latest()->paginate($request->integer('per_page', 15));

        return TicketRelationshipResource::collection($relationships);
    }

    public function store(StoreTicketRelationshipRequest $request): JsonResponse
    {
        try {
            $relationship = $this->service->create($request->validated(), $request->user(), $request->headers->get('X-Correlation-ID'));
        } catch (ValidationException $exception) {
            $this->transformValidationException($exception);
        }

        return TicketRelationshipResource::make($relationship)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, TicketRelationship $ticketRelationship): TicketRelationshipResource
    {
        $this->authorizeRequest($request, 'view', $ticketRelationship);

        return TicketRelationshipResource::make($ticketRelationship->load(['primaryTicket', 'relatedTicket', 'creator']));
    }

    public function update(UpdateTicketRelationshipRequest $request, TicketRelationship $ticketRelationship): TicketRelationshipResource
    {
        try {
            $relationship = $this->service->update($ticketRelationship, $request->validated(), $request->user(), $request->headers->get('X-Correlation-ID'));
        } catch (ValidationException $exception) {
            $this->transformValidationException($exception);
        }

        return TicketRelationshipResource::make($relationship);
    }

    public function destroy(Request $request, TicketRelationship $ticketRelationship): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $ticketRelationship);

        $user = $request->user();

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], 401));
        }

        try {
            $this->service->delete($ticketRelationship, $user, $request->headers->get('X-Correlation-ID'));
        } catch (ValidationException $exception) {
            $this->transformValidationException($exception);
        }

        return response()->json(null, 204);
    }

    protected function authorizeRequest(Request $request, string $ability, mixed $arguments): void
    {
        $user = $request->user();

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], 401));
        }

        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $response->message() ?: 'This action is unauthorized.',
                ],
            ], 403));
        }
    }

    protected function transformValidationException(ValidationException $exception): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_VALIDATION',
                'message' => $exception->validator?->errors()->first() ?? $exception->getMessage() ?? 'Validation failed.',
                'details' => $exception->errors(),
            ],
        ], 422));
    }
}
