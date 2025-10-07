<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $this->route('team');

        if (! $user || ! $team instanceof Team) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $team);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $team = $this->route('team');
        $tenantId = $user?->tenant_id ?? 0;
        $teamId = $team instanceof Team ? $team->getKey() : null;

        return [
            'name' => ['sometimes', 'string', 'max:255', $this->uniqueNameRule($tenantId, $teamId)],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', $this->uniqueSlugRule($tenantId, $teamId)],
            'description' => ['sometimes', 'nullable', 'string'],
            'default_queue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand_id' => ['sometimes', 'nullable', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'members' => ['sometimes', 'array'],
            'members.*' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'members.*.role' => ['required', 'string', 'max:100'],
            'members.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    protected function uniqueNameRule(int $tenantId, ?int $teamId)
    {
        $rule = Rule::unique('teams', 'name')->where('tenant_id', $tenantId);

        if ($teamId) {
            $rule->ignore($teamId);
        }

        return $rule;
    }

    protected function uniqueSlugRule(int $tenantId, ?int $teamId)
    {
        $rule = Rule::unique('teams', 'slug')->where('tenant_id', $tenantId);

        if ($teamId) {
            $rule->ignore($teamId);
        }

        return $rule;
    }
}
