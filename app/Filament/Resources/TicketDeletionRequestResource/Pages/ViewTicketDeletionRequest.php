<?php

namespace App\Filament\Resources\TicketDeletionRequestResource\Pages;

use App\Filament\Resources\TicketDeletionRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTicketDeletionRequest extends ViewRecord
{
    protected static string $resource = TicketDeletionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(false),
        ];
    }
}
