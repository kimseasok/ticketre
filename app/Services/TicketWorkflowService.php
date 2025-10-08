<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\TicketWorkflowTransition;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketWorkflowService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{workflow: TicketWorkflow, state: TicketWorkflowState, attributes: array<string, mixed>}
     */
    public function prepareForCreate(array $attributes): array
    {
        $tenantId = Arr::get($attributes, 'tenant_id');
        if (! $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Tenant context is required for workflow resolution.'],
            ]);
        }

        $brandId = Arr::get($attributes, 'brand_id');
        if ($brandId === null && app()->bound('currentBrand') && app('currentBrand')) {
            $brandId = app('currentBrand')->getKey();
        }
        $workflow = $this->resolveWorkflow($tenantId, $brandId, Arr::get($attributes, 'ticket_workflow_id'));
        $state = $this->resolveInitialState($workflow, Arr::get($attributes, 'workflow_state'));

        $attributes['ticket_workflow_id'] = $workflow->getKey();
        $attributes['workflow_state'] = $state->slug;

        if (! Arr::has($attributes, 'sla_due_at') && $state->sla_minutes) {
            $attributes['sla_due_at'] = now()->addMinutes($state->sla_minutes);
        }

        return [
            'workflow' => $workflow,
            'state' => $state,
            'attributes' => $attributes,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{workflow: TicketWorkflow, transition: TicketWorkflowTransition|null, state: TicketWorkflowState}
     */
    public function validateTransition(Ticket $ticket, string $targetState, User $actor, array $context = []): array
    {
        $workflow = $this->resolveTicketWorkflow($ticket);
        $currentState = $this->resolveState($workflow, $ticket->workflow_state);
        $nextState = $this->resolveState($workflow, $targetState);

        if ($currentState && $currentState->is($nextState)) {
            return [
                'workflow' => $workflow,
                'transition' => null,
                'state' => $nextState,
            ];
        }

        $transition = TicketWorkflowTransition::query()
            ->where('ticket_workflow_id', $workflow->getKey())
            ->where('from_state_id', $currentState?->getKey())
            ->where('to_state_id', $nextState->getKey())
            ->first();

        if (! $transition) {
            throw ValidationException::withMessages([
                'workflow_state' => [sprintf(
                    'Transition from %s to %s is not permitted.',
                    $currentState?->slug ?? 'unassigned',
                    $nextState->slug,
                )],
            ]);
        }

        if ($transition->requires_comment && empty($context['comment'])) {
            throw ValidationException::withMessages([
                'comment' => ['A comment is required for this transition.'],
            ]);
        }

        if ($transition->guard_hook) {
            $this->invokeGuardHook($transition->guard_hook, $ticket, $transition, $actor, $context);
        }

        if ($nextState->entry_hook) {
            $this->invokeEntryHook($nextState->entry_hook, $ticket, $transition, $actor, $context);
        }

        $this->logTransition($ticket, $transition, $actor, $context);

        return [
            'workflow' => $workflow,
            'transition' => $transition,
            'state' => $nextState,
        ];
    }

    protected function resolveTicketWorkflow(Ticket $ticket): TicketWorkflow
    {
        if ($ticket->ticket_workflow_id) {
            $workflow = TicketWorkflow::withoutGlobalScopes()
                ->where('tenant_id', $ticket->tenant_id)
                ->where(fn ($query) => $query
                    ->whereNull('brand_id')
                    ->orWhere('brand_id', $ticket->brand_id))
                ->find($ticket->ticket_workflow_id);

            if ($workflow) {
                return $workflow;
            }
        }

        return $this->resolveWorkflow($ticket->tenant_id, $ticket->brand_id, null);
    }

    protected function resolveWorkflow(int $tenantId, ?int $brandId, ?int $explicitWorkflowId): TicketWorkflow
    {
        $scopes = [];

        if ($brandId) {
            $scopes[] = fn ($builder) => $builder->where('brand_id', $brandId);
        }

        if ($brandId === null && app()->bound('currentBrand') && app('currentBrand')) {
            $scopes[] = fn ($builder) => $builder->where('brand_id', app('currentBrand')->getKey());
        }

        $scopes[] = fn ($builder) => $builder->whereNull('brand_id');

        foreach ($scopes as $scopeCallback) {
            $query = TicketWorkflow::query()->withoutGlobalScopes()->where('tenant_id', $tenantId);
            $query->where($scopeCallback);

            if ($explicitWorkflowId) {
                $workflow = (clone $query)->where('id', $explicitWorkflowId)->first();
                if ($workflow) {
                    return $workflow;
                }
            }

            $workflow = (clone $query)->where('is_default', true)->orderByDesc('updated_at')->first();
            if ($workflow) {
                return $workflow;
            }

            $workflow = (clone $query)->orderBy('id')->first();
            if ($workflow) {
                return $workflow;
            }
        }

        throw ValidationException::withMessages([
            'ticket_workflow_id' => ['No workflow is configured for this tenant/brand.'],
        ]);
    }

    protected function resolveInitialState(TicketWorkflow $workflow, ?string $requestedState): TicketWorkflowState
    {
        if ($requestedState) {
            return $this->resolveState($workflow, $requestedState);
        }

        $state = $workflow->states()->where('is_initial', true)->first();
        if ($state) {
            return $state;
        }

        $state = $workflow->states()->orderBy('position')->first();
        if ($state) {
            return $state;
        }

        throw ValidationException::withMessages([
            'workflow_state' => ['Workflow does not define any states.'],
        ]);
    }

    protected function resolveState(TicketWorkflow $workflow, ?string $slug): TicketWorkflowState
    {
        if (! $slug) {
            throw ValidationException::withMessages([
                'workflow_state' => ['Current workflow state is not set.'],
            ]);
        }

        $state = $workflow->states()->where('slug', $slug)->first();

        if (! $state) {
            throw ValidationException::withMessages([
                'workflow_state' => [sprintf('State %s is not defined for this workflow.', $slug)],
            ]);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function invokeGuardHook(string $hookClass, Ticket $ticket, TicketWorkflowTransition $transition, User $actor, array $context): void
    {
        if (! class_exists($hookClass)) {
            throw ValidationException::withMessages([
                'workflow_state' => [sprintf('Guard hook %s is not registered.', $hookClass)],
            ]);
        }

        app()->call([$this->resolveHookInstance($hookClass), '__invoke'], [
            'ticket' => $ticket,
            'transition' => $transition,
            'actor' => $actor,
            'context' => $context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function invokeEntryHook(string $hookClass, Ticket $ticket, TicketWorkflowTransition $transition, User $actor, array $context): void
    {
        if (! class_exists($hookClass)) {
            throw ValidationException::withMessages([
                'workflow_state' => [sprintf('Entry hook %s is not registered.', $hookClass)],
            ]);
        }

        app()->call([$this->resolveHookInstance($hookClass), '__invoke'], [
            'ticket' => $ticket,
            'transition' => $transition,
            'actor' => $actor,
            'context' => $context,
        ]);
    }

    protected function resolveHookInstance(string $hookClass): object
    {
        return app()->make($hookClass);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logTransition(Ticket $ticket, TicketWorkflowTransition $transition, User $actor, array $context): void
    {
        Log::channel(config('logging.default'))->info('ticket.workflow.transition', [
            'ticket_id' => $ticket->getKey(),
            'tenant_id' => $ticket->tenant_id,
            'brand_id' => $ticket->brand_id,
            'workflow_id' => $transition->ticket_workflow_id,
            'from_state_id' => $transition->from_state_id,
            'to_state_id' => $transition->to_state_id,
            'actor_id' => $actor->getKey(),
            'requires_comment' => $transition->requires_comment,
            'has_comment' => ! empty($context['comment']),
            'correlation_id' => request()?->header('X-Correlation-ID') ?? (string) Str::uuid(),
            'context' => 'ticket_workflow',
        ]);
    }
}
