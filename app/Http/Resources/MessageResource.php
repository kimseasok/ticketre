<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'ticket_id' => $this->resource->ticket_id,
            'tenant_id' => $this->resource->tenant_id,
            'brand_id' => $this->resource->brand_id,
            'visibility' => $this->resource->visibility,
            'author_role' => $this->resource->author_role,
            'body' => $this->resource->body,
            'sent_at' => optional($this->resource->sent_at)->toIso8601String(),
            'created_at' => optional($this->resource->created_at)->toIso8601String(),
            'updated_at' => optional($this->resource->updated_at)->toIso8601String(),
            'author' => $this->whenLoaded('author', function () {
                return [
                    'id' => $this->author->getKey(),
                    'name' => $this->author->name,
                ];
            }),
        ];
    }
}
