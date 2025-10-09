<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TwoFactorCredential
 */
class TwoFactorCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = 'pending';

        if ($this->resource->isLocked()) {
            $status = 'locked';
        } elseif ($this->resource->isConfirmed()) {
            $status = 'active';
        }

        return [
            'type' => 'two-factor-credentials',
            'id' => (string) $this->resource->getKey(),
            'attributes' => [
                'user_id' => $this->resource->user_id,
                'tenant_id' => $this->resource->tenant_id,
                'brand_id' => $this->resource->brand_id,
                'label' => $this->resource->label,
                'status' => $status,
                'confirmed_at' => $this->resource->confirmed_at?->toAtomString(),
                'last_verified_at' => $this->resource->last_verified_at?->toAtomString(),
                'locked_until' => $this->resource->locked_until?->toAtomString(),
                'failed_attempts' => $this->resource->failed_attempts,
                'recovery_codes_remaining' => $this->whenLoaded('recoveryCodes', function () {
                    return $this->resource->recoveryCodes->whereNull('used_at')->count();
                }),
                'created_at' => $this->resource->created_at?->toAtomString(),
                'updated_at' => $this->resource->updated_at?->toAtomString(),
            ],
        ];
    }
}
