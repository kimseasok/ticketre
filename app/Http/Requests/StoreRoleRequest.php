<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', Role::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule()],
            'slug' => ['nullable', 'string', 'max:255', $this->uniqueSlugRule()],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    protected function uniqueNameRule()
    {
        $tenantId = $this->user()?->tenant_id ?? 0;

        return Rule::unique('roles', 'name')->where('tenant_id', $tenantId);
    }

    protected function uniqueSlugRule()
    {
        $tenantId = $this->user()?->tenant_id ?? 0;

        return Rule::unique('roles', 'slug')->where('tenant_id', $tenantId);
    }
}
