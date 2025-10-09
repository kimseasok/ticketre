<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can('teams.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('teams', 'slug')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'default_queue' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
