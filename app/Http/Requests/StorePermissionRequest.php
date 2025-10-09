<?php

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', Permission::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? 0;

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($tenantId)],
            'description' => ['nullable', 'string'],
            'brand_id' => ['nullable', 'integer', $this->brandExistsRule($tenantId)],
            'is_system' => ['sometimes', 'boolean'],
        ];
    }

    protected function uniqueNameRule(int $tenantId)
    {
        return Rule::unique('permissions', 'name')->where(function (QueryBuilder $query) use ($tenantId): void {
            if ($tenantId > 0) {
                $query->where(fn (QueryBuilder $builder) => $builder
                    ->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId));

                return;
            }

            $query->whereNull('tenant_id');
        });
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
