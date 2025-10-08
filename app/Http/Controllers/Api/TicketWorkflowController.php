<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketWorkflowRequest;
use App\Http\Requests\UpdateTicketWorkflowRequest;
use App\Http\Resources\TicketWorkflowResource;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\TicketWorkflowTransition;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TicketWorkflowController extends Controller
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TicketWorkflow::class);

        $query = TicketWorkflow::query()->with(['states', 'transitions.fromState', 'transitions.toState']);

        return TicketWorkflowResource::collection($query->paginate());
    }

    public function store(StoreTicketWorkflowRequest $request): JsonResponse
    {
        $this->authorize('create', TicketWorkflow::class);

        $workflow = $this->database->transaction(function () use ($request) {
            $data = $request->validated();
            $states = $data['states'];
            $transitions = $data['transitions'] ?? [];

            unset($data['states'], $data['transitions']);

            $tenant = app('currentTenant');
            $brand = $data['brand_id'] ?? (app()->bound('currentBrand') ? optional(app('currentBrand'))->getKey() : null);
            unset($data['brand_id']);

            $workflow = TicketWorkflow::create([
                'tenant_id' => $tenant->getKey(),
                'brand_id' => $brand,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
            ]);

            $stateMap = $this->syncStates($workflow, $states);
            $this->syncTransitions($workflow, $stateMap, $transitions);

            if ($workflow->is_default) {
                $this->demoteOtherDefaults($workflow);
            }

            return $workflow->fresh(['states', 'transitions.fromState', 'transitions.toState']);
        });

        return (new TicketWorkflowResource($workflow))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TicketWorkflow $ticketWorkflow): TicketWorkflowResource
    {
        $this->authorize('view', $ticketWorkflow);

        $ticketWorkflow->load(['states', 'transitions.fromState', 'transitions.toState']);

        return new TicketWorkflowResource($ticketWorkflow);
    }

    public function update(UpdateTicketWorkflowRequest $request, TicketWorkflow $ticketWorkflow): TicketWorkflowResource
    {
        $this->authorize('update', $ticketWorkflow);

        $workflow = $this->database->transaction(function () use ($request, $ticketWorkflow) {
            $data = $request->validated();
            $states = $data['states'] ?? null;
            $transitions = $data['transitions'] ?? null;

            unset($data['states'], $data['transitions']);

            if (! empty($data)) {
                $ticketWorkflow->fill($data);
                $ticketWorkflow->save();
            }

            if ($states !== null) {
                $stateMap = $this->syncStates($ticketWorkflow, $states);
            } else {
                $stateMap = $ticketWorkflow->states()->get()->keyBy('slug');
            }

            if ($transitions !== null) {
                $this->syncTransitions($ticketWorkflow, $stateMap, $transitions);
            }

            if ($ticketWorkflow->is_default) {
                $this->demoteOtherDefaults($ticketWorkflow);
            }

            return $ticketWorkflow->fresh(['states', 'transitions.fromState', 'transitions.toState']);
        });

        return new TicketWorkflowResource($workflow);
    }

    public function destroy(TicketWorkflow $ticketWorkflow): JsonResponse
    {
        $this->authorize('delete', $ticketWorkflow);

        $ticketWorkflow->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $states
     * @return array<string, TicketWorkflowState>
     */
    protected function syncStates(TicketWorkflow $workflow, array $states): array
    {
        $existing = $workflow->states()->get()->keyBy('id');
        $slugMap = [];
        $keptIds = [];

        foreach ($states as $position => $stateData) {
            $payload = [
                'tenant_id' => $workflow->tenant_id,
                'brand_id' => $workflow->brand_id,
                'name' => $stateData['name'],
                'slug' => $stateData['slug'],
                'position' => $stateData['position'] ?? $position,
                'is_initial' => (bool) ($stateData['is_initial'] ?? false),
                'is_terminal' => (bool) ($stateData['is_terminal'] ?? false),
                'sla_minutes' => $stateData['sla_minutes'] ?? null,
                'entry_hook' => $stateData['entry_hook'] ?? null,
                'description' => $stateData['description'] ?? null,
            ];

            if (! empty($stateData['id']) && $existing->has($stateData['id'])) {
                /** @var TicketWorkflowState $state */
                $state = $existing->get($stateData['id']);
                $state->fill($payload);
                $state->save();
            } else {
                $state = $workflow->states()->create($payload);
            }

            $slugMap[$state->slug] = $state;
            $keptIds[] = $state->getKey();
        }

        $toDelete = $existing->keys()->diff($keptIds);
        if ($toDelete->isNotEmpty()) {
            TicketWorkflowState::query()->whereIn('id', $toDelete)->delete();
        }

        return $slugMap;
    }

    /**
     * @param  array<int, array<string, mixed>>  $transitions
     * @param  array<string, TicketWorkflowState>  $stateMap
     */
    protected function syncTransitions(TicketWorkflow $workflow, array $stateMap, array $transitions): void
    {
        $existing = $workflow->transitions()->get()->keyBy('id');
        $kept = [];

        foreach ($transitions as $transitionData) {
            $fromSlug = $transitionData['from'];
            $toSlug = $transitionData['to'];

            if (! isset($stateMap[$fromSlug], $stateMap[$toSlug])) {
                continue;
            }

            $payload = [
                'tenant_id' => $workflow->tenant_id,
                'brand_id' => $workflow->brand_id,
                'from_state_id' => $stateMap[$fromSlug]->getKey(),
                'to_state_id' => $stateMap[$toSlug]->getKey(),
                'guard_hook' => $transitionData['guard_hook'] ?? null,
                'requires_comment' => (bool) ($transitionData['requires_comment'] ?? false),
                'metadata' => $transitionData['metadata'] ?? [],
            ];

            if (! empty($transitionData['id']) && $existing->has($transitionData['id'])) {
                /** @var TicketWorkflowTransition $transition */
                $transition = $existing->get($transitionData['id']);
                $transition->fill($payload);
                $transition->save();
            } else {
                $transition = $workflow->transitions()->create($payload);
            }

            $kept[] = $transition->getKey();
        }

        $toDelete = $existing->keys()->diff($kept);
        if ($toDelete->isNotEmpty()) {
            TicketWorkflowTransition::query()->whereIn('id', $toDelete)->delete();
        }
    }

    protected function demoteOtherDefaults(TicketWorkflow $workflow): void
    {
        TicketWorkflow::query()
            ->where('tenant_id', $workflow->tenant_id)
            ->where('id', '!=', $workflow->getKey())
            ->when($workflow->brand_id, fn ($query) => $query->where('brand_id', $workflow->brand_id))
            ->when(! $workflow->brand_id, fn ($query) => $query->whereNull('brand_id'))
            ->update(['is_default' => false]);
    }
}
