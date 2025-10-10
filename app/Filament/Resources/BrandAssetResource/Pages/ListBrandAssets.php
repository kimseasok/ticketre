<?php

namespace App\Filament\Resources\BrandAssetResource\Pages;

use App\Filament\Resources\BrandAssetResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\CreateAction;

class ListBrandAssets extends ListRecords
{
    protected static string $resource = BrandAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
