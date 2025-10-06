<?php

namespace App\Repositories;

use App\Models\Ticket;
use App\Traits\BrandScope;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageRepository
{
    public function queryForTicket(Ticket $ticket, string $audience = 'agent'): HasMany
    {
        return $ticket->messages()
            ->withoutGlobalScopes([
                TenantScope::class,
                BrandScope::class,
            ])
            ->where('tenant_id', $ticket->tenant_id)
            ->when($ticket->brand_id, fn ($query) => $query->where('brand_id', $ticket->brand_id))
            ->with(['author:id,name'])
            ->withAudience($audience)
            ->orderBy('sent_at', 'asc');
    }

    public function forPortal(Ticket $ticket): Collection
    {
        return $this->queryForTicket($ticket, 'portal')->get();
    }

    public function allForAgents(Ticket $ticket): Collection
    {
        return $this->queryForTicket($ticket, 'agent')->get();
    }
}
