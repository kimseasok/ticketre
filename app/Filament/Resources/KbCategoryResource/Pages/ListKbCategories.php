<?php

namespace App\Filament\Resources\KbCategoryResource\Pages;

use App\Filament\Resources\KbCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKbCategories extends ListRecords
{
    protected static string $resource = KbCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
