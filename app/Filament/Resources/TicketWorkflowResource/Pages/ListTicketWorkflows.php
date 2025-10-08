<?php

namespace App\Filament\Resources\TicketWorkflowResource\Pages;

use App\Filament\Resources\TicketWorkflowResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Actions\CreateAction;

class ListTicketWorkflows extends ListRecords
{
    protected static string $resource = TicketWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
