<?php

namespace App\Filament\Resources\PermissionCoverageReportResource\Pages;

use App\Filament\Resources\PermissionCoverageReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPermissionCoverageReports extends ListRecords
{
    protected static string $resource = PermissionCoverageReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
