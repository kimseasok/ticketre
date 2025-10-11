<?php

namespace App\Filament\Resources\PortalSessionResource\Pages;

use App\Filament\Resources\PortalSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalSessions extends ListRecords
{
    protected static string $resource = PortalSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
