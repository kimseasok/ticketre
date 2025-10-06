<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TicketDeletionRequest
 */
class TicketDeletionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'brand_id' => $this->brand_id,
            'ticket_id' => $this->ticket_id,
            'ticket' => $this->whenLoaded('ticket', function () {
                return [
                    'id' => $this->ticket?->getKey(),
                    'status' => $this->ticket?->status,
                    'priority' => $this->ticket?->priority,
                ];
            }),
            'requested_by' => $this->requested_by,
            'requester' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester?->getKey(),
                    'name' => $this->requester?->name,
                ];
            }),
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'id' => $this->approver?->getKey(),
                    'name' => $this->approver?->name,
                ];
            }),
            'cancelled_by' => $this->cancelled_by,
            'canceller' => $this->whenLoaded('canceller', function () {
                return [
                    'id' => $this->canceller?->getKey(),
                    'name' => $this->canceller?->name,
                ];
            }),
            'status' => $this->status,
            'reason' => $this->reason,
            'aggregate_snapshot' => $this->aggregate_snapshot,
            'correlation_id' => $this->correlation_id,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
