<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        /** @var Team|null $team */
        $team = $this->route('team');

        if (! $team instanceof Team) {
            return false;
        }

        return $user->can('teams.manage') && $team->tenant_id === $user->tenant_id;
    }

    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('team');

        $tenantId = $team->tenant_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('teams', 'slug')->ignore($team->getKey())->where('tenant_id', $tenantId),
            ],
            'default_queue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'brand_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
