<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketMergeRequest;
use App\Http\Resources\TicketMergeResource;
use App\Models\TicketMerge;
use App\Services\TicketMergeService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TicketMergeController extends Controller
{
    public function __construct(private readonly TicketMergeService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', TicketMerge::class);

        $query = TicketMerge::query()->with(['primaryTicket', 'secondaryTicket', 'initiator']);

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $brand = app('currentBrand');
            $query->where(function ($builder) use ($brand) {
                $builder->where('brand_id', $brand?->getKey())
                    ->orWhereNull('brand_id');
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($ticketId = $request->query('primary_ticket_id')) {
            $query->where('primary_ticket_id', $ticketId);
        }

        if ($brand = $request->query('brand_id')) {
            $query->where('brand_id', $brand);
        }

        $merges = $query->latest()->paginate($request->integer('per_page', 15));

        return TicketMergeResource::collection($merges);
    }

    public function store(StoreTicketMergeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['correlation_id'] = $data['correlation_id'] ?? $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        $merge = $this->service->merge($data, $request->user(), $data['correlation_id']);

        return TicketMergeResource::make($merge->load(['primaryTicket', 'secondaryTicket', 'initiator']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, TicketMerge $ticketMerge): TicketMergeResource
    {
        $this->authorizeRequest($request, 'view', $ticketMerge);

        return TicketMergeResource::make($ticketMerge->load(['primaryTicket', 'secondaryTicket', 'initiator']));
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
