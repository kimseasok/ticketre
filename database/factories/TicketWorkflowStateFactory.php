<?php

namespace Database\Factories;

use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TicketWorkflowStateFactory extends Factory
{
    protected $model = TicketWorkflowState::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'ticket_workflow_id' => TicketWorkflow::factory(),
            'tenant_id' => static function (array $attributes): int {
                $workflow = $attributes['ticket_workflow_id'] instanceof TicketWorkflow
                    ? $attributes['ticket_workflow_id']
                    : TicketWorkflow::query()->findOrFail((int) $attributes['ticket_workflow_id']);

                return (int) $workflow->tenant_id;
            },
            'brand_id' => static function (array $attributes): ?int {
                $workflow = $attributes['ticket_workflow_id'] instanceof TicketWorkflow
                    ? $attributes['ticket_workflow_id']
                    : TicketWorkflow::query()->findOrFail((int) $attributes['ticket_workflow_id']);

                return $workflow->brand_id;
            },
            'name' => Str::title($name),
            'slug' => Str::slug($name.'-'.Str::random(5)),
            'position' => $this->faker->unique()->numberBetween(0, 10),
            'is_initial' => false,
            'is_terminal' => false,
            'sla_minutes' => $this->faker->randomElement([null, 60, 120, 240]),
            'entry_hook' => null,
            'description' => $this->faker->sentence(),
        ];
    }

    public function initial(): self
    {
        return $this->state(fn () => ['is_initial' => true]);
    }

    public function terminal(): self
    {
        return $this->state(fn () => ['is_terminal' => true]);
    }
}
