<?php

namespace App\Filament\Resources\KbCategoryResource\Pages;

use App\Filament\Resources\KbCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKbCategory extends EditRecord
{
    protected static string $resource = KbCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
