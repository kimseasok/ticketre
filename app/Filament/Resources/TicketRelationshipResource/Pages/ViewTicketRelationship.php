<?php

namespace App\Filament\Resources\TicketRelationshipResource\Pages;

use App\Filament\Resources\TicketRelationshipResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTicketRelationship extends ViewRecord
{
    protected static string $resource = TicketRelationshipResource::class;

    protected function resolveRecord(int|string $key): \Illuminate\Database\Eloquent\Model
    {
        return parent::resolveRecord($key)->load(['primaryTicket', 'relatedTicket', 'creator']);
    }
}
