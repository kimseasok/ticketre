<?php

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $permission = $this->route('permission');

        if (! $user || ! $permission instanceof Permission) {
            return false;
        }

        return Gate::forUser($user)->allows('update', $permission);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $permission = $this->route('permission');
        if (! $permission instanceof Permission) {
            return [];
        }

        $tenantId = $this->user()?->tenant_id ?? 0;

        return [
            'name' => ['sometimes', 'string', 'max:255', $this->uniqueNameRule($tenantId, $permission)],
            'description' => ['nullable', 'string'],
            'brand_id' => ['nullable', 'integer', $this->brandExistsRule($tenantId)],
            'is_system' => ['sometimes', 'boolean'],
        ];
    }

    protected function uniqueNameRule(int $tenantId, Permission $permission)
    {
        return Rule::unique('permissions', 'name')
            ->where(function (QueryBuilder $query) use ($tenantId): void {
                if ($tenantId > 0) {
                    $query->where(fn (QueryBuilder $builder) => $builder
                        ->whereNull('tenant_id')
                        ->orWhere('tenant_id', $tenantId));

                    return;
                }

                $query->whereNull('tenant_id');
            })
            ->ignore($permission->getKey());
    }

    protected function brandExistsRule(int $tenantId)
    {
        return Rule::exists('brands', 'id')->where(function (QueryBuilder $query) use ($tenantId): void {
            if ($tenantId > 0) {
                $query->where('tenant_id', $tenantId);
            }
        });
    }
}
