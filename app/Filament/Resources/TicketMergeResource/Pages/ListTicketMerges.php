<?php

namespace App\Filament\Resources\TicketMergeResource\Pages;

use App\Filament\Resources\TicketMergeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTicketMerges extends ListRecords
{
    protected static string $resource = TicketMergeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Merge'),
        ];
    }
}
