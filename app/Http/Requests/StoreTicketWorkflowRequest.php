<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreTicketWorkflowRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.workflows.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('ticket_workflows')->where(function ($query) {
                    $query->where('tenant_id', app('currentTenant')->getKey());
                    $brand = app()->bound('currentBrand') ? app('currentBrand') : null;

                    if ($brand) {
                        $query->where('brand_id', $brand->getKey());
                    } else {
                        $query->whereNull('brand_id');
                    }
                }),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'states' => ['required', 'array', 'min:1'],
            'states.*.name' => ['required', 'string', 'max:255'],
            'states.*.slug' => ['required', 'string', 'max:255'],
            'states.*.position' => ['nullable', 'integer', 'min:0'],
            'states.*.is_initial' => ['sometimes', 'boolean'],
            'states.*.is_terminal' => ['sometimes', 'boolean'],
            'states.*.sla_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'states.*.entry_hook' => ['nullable', 'string', 'max:255'],
            'states.*.description' => ['nullable', 'string', 'max:500'],
            'transitions' => ['sometimes', 'array'],
            'transitions.*.from' => ['required_with:transitions', 'string', 'max:255'],
            'transitions.*.to' => ['required_with:transitions', 'string', 'max:255'],
            'transitions.*.guard_hook' => ['nullable', 'string', 'max:255'],
            'transitions.*.requires_comment' => ['sometimes', 'boolean'],
            'transitions.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $states = collect($this->input('states', []));
            $initialCount = $states->where('is_initial', true)->count();

            if ($initialCount === 0) {
                $validator->errors()->add('states', 'At least one initial state must be defined.');
            }

            $slugs = $states->pluck('slug');

            if ($slugs->count() !== $slugs->unique()->count()) {
                $validator->errors()->add('states', 'State slugs must be unique within the workflow.');
            }

            $transitionStates = collect($this->input('transitions', []))->flatMap(function ($transition) {
                return [$transition['from'] ?? null, $transition['to'] ?? null];
            })->filter();

            $missing = $transitionStates->diff($slugs);

            if ($missing->isNotEmpty()) {
                $validator->errors()->add('transitions', sprintf('Transitions reference unknown states: %s', $missing->implode(', ')));
            }
        });
    }
}
