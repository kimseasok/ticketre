<?php

namespace App\Http\Requests;

use App\Models\BroadcastConnection;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateBroadcastConnectionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var BroadcastConnection|null $connection */
        $connection = $this->route('broadcast_connection');
        $user = $this->user();

        if (! $connection || ! $user instanceof User) {
            return false;
        }

        return $user->can('broadcast_connections.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;
        /** @var BroadcastConnection|null $connection */
        $connection = $this->route('broadcast_connection');

        return [
            'brand_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'user_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'connection_id' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('broadcast_connections', 'connection_id')
                    ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($connection?->getKey()),
            ],
            'channel_name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in(BroadcastConnection::STATUSES)],
            'latency_ms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'last_seen_at' => ['sometimes', 'nullable', 'date'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'metadata.*' => ['nullable'],
        ];
    }
}
