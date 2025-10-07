<?php

namespace App\Http\Requests;

use App\Models\BroadcastConnection;
use App\Models\User;
use Illuminate\Validation\Rule;

class StoreBroadcastConnectionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->can('broadcast_connections.manage');
    }

    public function rules(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;

        return [
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'connection_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('broadcast_connections', 'connection_id')->when(
                    $tenantId,
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'channel_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(BroadcastConnection::STATUSES)],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
            'last_seen_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
        ];
    }
}
