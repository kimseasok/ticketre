<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $role = $this->route('role');

        if (! $role instanceof Role) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $role);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        if (! $role instanceof Role) {
            return [];
        }
        $tenantId = $this->user()?->tenant_id ?? 0;

        return [
            'name' => ['sometimes', 'string', 'max:255', $this->uniqueNameRule($tenantId, $role)],
            'slug' => ['nullable', 'string', 'max:255', $this->uniqueSlugRule($tenantId, $role)],
            'description' => ['nullable', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', $this->permissionExistsRule($tenantId)],
        ];
    }

    protected function uniqueNameRule(int $tenantId, Role $role)
    {
        return Rule::unique('roles', 'name')->where('tenant_id', $tenantId)->ignore($role->getKey());
    }

    protected function uniqueSlugRule(int $tenantId, Role $role)
    {
        return Rule::unique('roles', 'slug')->where('tenant_id', $tenantId)->ignore($role->getKey());
    }

    protected function permissionExistsRule(int $tenantId)
    {
        return Rule::exists('permissions', 'name')
            ->where('guard_name', 'web')
            ->where(function (QueryBuilder $query) use ($tenantId): void {
                if ($tenantId) {
                    $query->where(fn (QueryBuilder $builder) => $builder
                        ->whereNull('tenant_id')
                        ->orWhere('tenant_id', $tenantId));

                    return;
                }

                $query->whereNull('tenant_id');
            });
    }
}
