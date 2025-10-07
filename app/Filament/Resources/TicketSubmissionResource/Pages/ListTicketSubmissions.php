<?php

namespace App\Filament\Resources\TicketSubmissionResource\Pages;

use App\Filament\Resources\TicketSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListTicketSubmissions extends ListRecords
{
    protected static string $resource = TicketSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
