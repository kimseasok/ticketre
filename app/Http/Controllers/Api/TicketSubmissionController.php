<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePermission;
use App\Http\Requests\TicketSubmissionIndexRequest;
use App\Http\Resources\TicketSubmissionResource;
use App\Models\TicketSubmission;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketSubmissionController extends Controller
{
    public function __construct()
    {
        $this->middleware(EnsurePermission::class.':tickets.view')->only(['index', 'show']);
    }

    public function index(TicketSubmissionIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', TicketSubmission::class);

        $filters = $request->validated();

        $submissions = TicketSubmission::query()
            ->with(['ticket', 'contact'])
            ->withCount('attachments')
            ->when(isset($filters['channel']), fn ($query) => $query->where('channel', $filters['channel']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['search']) && $filters['search'] !== '', function ($query) use ($filters) {
                $search = $filters['search'];

                $query->where(function ($builder) use ($search) {
                    $builder->where('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('contact', fn ($relation) => $relation->where('email', 'like', "%{$search}%"));
                });
            })
            ->latest('submitted_at')
            ->paginate();

        return TicketSubmissionResource::collection($submissions);
    }

    public function show(Request $request, TicketSubmission $ticketSubmission): TicketSubmissionResource
    {
        $this->authorizeRequest($request, 'view', $ticketSubmission);

        $ticketSubmission->load(['ticket', 'contact', 'attachments']);
        $ticketSubmission->loadCount('attachments');

        return TicketSubmissionResource::make($ticketSubmission);
    }

    protected function authorizeRequest(Request $request, string $ability, mixed $arguments): void
    {
        if ($arguments instanceof TicketSubmission) {
            $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
            if ($currentTenant && (int) $arguments->tenant_id !== (int) $currentTenant->getKey()) {
                throw new HttpResponseException(response()->json([
                    'error' => [
                        'code' => 'ERR_HTTP_404',
                        'message' => 'Ticket submission not found.',
                    ],
                ], 404));
            }

            $currentBrand = app()->bound('currentBrand') ? app('currentBrand') : null;
            if ($currentBrand && (int) $arguments->brand_id !== (int) $currentBrand->getKey()) {
                throw new HttpResponseException(response()->json([
                    'error' => [
                        'code' => 'ERR_HTTP_404',
                        'message' => 'Ticket submission not found.',
                    ],
                ], 404));
            }
        }

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
}
