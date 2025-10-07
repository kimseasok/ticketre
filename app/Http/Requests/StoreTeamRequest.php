<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', Team::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? 0;

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($tenantId)],
            'slug' => ['nullable', 'string', 'max:255', $this->uniqueSlugRule($tenantId)],
            'description' => ['nullable', 'string'],
            'default_queue' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'members' => ['sometimes', 'array'],
            'members.*' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'members.*.role' => ['required', 'string', 'max:100'],
            'members.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    protected function uniqueNameRule(int $tenantId)
    {
        return Rule::unique('teams', 'name')->where('tenant_id', $tenantId);
    }

    protected function uniqueSlugRule(int $tenantId)
    {
        return Rule::unique('teams', 'slug')->where('tenant_id', $tenantId);
    }
}
