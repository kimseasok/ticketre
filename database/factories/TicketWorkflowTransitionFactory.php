<?php

namespace Database\Factories;

use App\Models\TicketWorkflowTransition;
use App\Models\TicketWorkflowState;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketWorkflowTransitionFactory extends Factory
{
    protected $model = TicketWorkflowTransition::class;

    public function definition(): array
    {
        return [
            'from_state_id' => TicketWorkflowState::factory(),
            'to_state_id' => static function (array $attributes) {
                $fromState = $attributes['from_state_id'] instanceof TicketWorkflowState
                    ? $attributes['from_state_id']
                    : TicketWorkflowState::query()->findOrFail((int) $attributes['from_state_id']);

                return TicketWorkflowState::factory()->create([
                    'ticket_workflow_id' => $fromState->ticket_workflow_id,
                    'tenant_id' => $fromState->tenant_id,
                    'brand_id' => $fromState->brand_id,
                ])->getKey();
            },
            'ticket_workflow_id' => static function (array $attributes): int {
                $fromState = $attributes['from_state_id'] instanceof TicketWorkflowState
                    ? $attributes['from_state_id']
                    : TicketWorkflowState::query()->findOrFail((int) $attributes['from_state_id']);

                return (int) $fromState->ticket_workflow_id;
            },
            'tenant_id' => static function (array $attributes): int {
                $fromState = $attributes['from_state_id'] instanceof TicketWorkflowState
                    ? $attributes['from_state_id']
                    : TicketWorkflowState::query()->findOrFail((int) $attributes['from_state_id']);

                return (int) $fromState->tenant_id;
            },
            'brand_id' => static function (array $attributes): ?int {
                $fromState = $attributes['from_state_id'] instanceof TicketWorkflowState
                    ? $attributes['from_state_id']
                    : TicketWorkflowState::query()->findOrFail((int) $attributes['from_state_id']);

                return $fromState->brand_id;
            },
            'guard_hook' => null,
            'requires_comment' => false,
            'metadata' => [],
        ];
    }
}
