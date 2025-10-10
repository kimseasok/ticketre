<?php

namespace App\Filament\Resources\RbacEnforcementGapAnalysisResource\Pages;

use App\Filament\Resources\RbacEnforcementGapAnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRbacEnforcementGapAnalyses extends ListRecords
{
    protected static string $resource = RbacEnforcementGapAnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
