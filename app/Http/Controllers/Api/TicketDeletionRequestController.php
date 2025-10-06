<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTicketDeletionRequest;
use App\Http\Requests\CancelTicketDeletionRequest;
use App\Http\Requests\StoreTicketDeletionRequest;
use App\Http\Resources\TicketDeletionRequestResource;
use App\Models\TicketDeletionRequest;
use App\Services\TicketDeletionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TicketDeletionRequestController extends Controller
{
    public function __construct(private readonly TicketDeletionService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', TicketDeletionRequest::class);

        $filters = $request->only(['status', 'ticket_id', 'brand_id']);

        $query = TicketDeletionRequest::query()->with(['ticket', 'requester', 'approver', 'canceller']);

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $brand = app('currentBrand');
            $query->where(function ($builder) use ($brand) {
                $builder->where('brand_id', $brand?->getKey())
                    ->orWhereNull('brand_id');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['ticket_id'])) {
            $query->where('ticket_id', $filters['ticket_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        $requests = $query->latest()->paginate($request->integer('per_page', 15));

        return TicketDeletionRequestResource::collection($requests);
    }

    public function store(StoreTicketDeletionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['brand_id'] = app()->bound('currentBrand') && app('currentBrand')
            ? app('currentBrand')->getKey()
            : null;

        $correlationId = $data['correlation_id'] ?? $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        $model = $this->service->request($data, $request->user(), $correlationId);

        return TicketDeletionRequestResource::make($model->load(['ticket', 'requester']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, TicketDeletionRequest $ticketDeletionRequest): TicketDeletionRequestResource
    {
        $this->authorizeRequest($request, 'view', $ticketDeletionRequest);

        return TicketDeletionRequestResource::make($ticketDeletionRequest->load(['ticket', 'requester', 'approver', 'canceller']));
    }

    public function approve(ApproveTicketDeletionRequest $request, TicketDeletionRequest $ticketDeletionRequest): TicketDeletionRequestResource
    {
        $data = $request->validated();
        $holdHours = isset($data['hold_hours']) ? (int) $data['hold_hours'] : TicketDeletionService::DEFAULT_HOLD_HOURS;

        $model = $this->service->approve($ticketDeletionRequest, $request->user(), $holdHours);

        return TicketDeletionRequestResource::make($model->load(['ticket', 'requester', 'approver']));
    }

    public function cancel(CancelTicketDeletionRequest $request, TicketDeletionRequest $ticketDeletionRequest): TicketDeletionRequestResource
    {
        $model = $this->service->cancel($ticketDeletionRequest, $request->user());

        return TicketDeletionRequestResource::make($model->load(['ticket', 'requester', 'canceller']));
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
}
