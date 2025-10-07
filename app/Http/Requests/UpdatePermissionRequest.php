<?php

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('permissions.manage') : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Permission $permission */
        $permission = $this->route('permission');
        $tenantId = app()->bound('currentTenant') && app('currentTenant')
            ? app('currentTenant')->getKey()
            : null;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:191',
                Rule::unique('permissions', 'name')
                    ->ignore($permission?->getKey())
                    ->where(function (Builder $query) use ($tenantId) {
                        $query->whereNull('tenant_id');

                        if ($tenantId !== null) {
                            $query->orWhere('tenant_id', $tenantId);
                        }
                    }),
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:191',
                Rule::unique('permissions', 'slug')
                    ->ignore($permission?->getKey())
                    ->where(function (Builder $query) use ($tenantId) {
                        $query->whereNull('tenant_id');

                        if ($tenantId !== null) {
                            $query->orWhere('tenant_id', $tenantId);
                        }
                    }),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guard_name' => ['sometimes', 'string', 'max:60'],
            'is_system' => ['sometimes', 'boolean'],
        ];
    }
}
