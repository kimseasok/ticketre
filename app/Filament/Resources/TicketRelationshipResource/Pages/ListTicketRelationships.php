<?php

namespace App\Filament\Resources\TicketRelationshipResource\Pages;

use App\Filament\Resources\TicketRelationshipResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTicketRelationships extends ListRecords
{
    protected static string $resource = TicketRelationshipResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['primaryTicket', 'relatedTicket', 'creator']);
    }
}
