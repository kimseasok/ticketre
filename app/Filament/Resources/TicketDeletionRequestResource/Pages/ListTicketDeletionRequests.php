<?php

namespace App\Filament\Resources\TicketDeletionRequestResource\Pages;

use App\Filament\Resources\TicketDeletionRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListTicketDeletionRequests extends ListRecords
{
    protected static string $resource = TicketDeletionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
