<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends ApiFormRequest
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
        $tenantId = app()->bound('currentTenant') && app('currentTenant')
            ? app('currentTenant')->getKey()
            : null;

        return [
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('permissions', 'name')->where(function (Builder $query) use ($tenantId) {
                    $query->whereNull('tenant_id');

                    if ($tenantId !== null) {
                        $query->orWhere('tenant_id', $tenantId);
                    }
                }),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('permissions', 'slug')->where(function (Builder $query) use ($tenantId) {
                    $query->whereNull('tenant_id');

                    if ($tenantId !== null) {
                        $query->orWhere('tenant_id', $tenantId);
                    }
                }),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'guard_name' => ['sometimes', 'string', 'max:60'],
            'is_system' => ['sometimes', 'boolean'],
        ];
    }
}
