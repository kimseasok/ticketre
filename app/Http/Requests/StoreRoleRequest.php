<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
            'permissions.*' => ['string', $this->permissionExistsRule()],
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

    protected function permissionExistsRule()
    {
        $tenantId = $this->user()?->tenant_id;

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
