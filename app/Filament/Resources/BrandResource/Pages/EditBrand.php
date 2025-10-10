<?php

namespace App\Filament\Resources\BrandResource\Pages;

use App\Filament\Resources\BrandResource;
use App\Services\BrandConfigurationService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var BrandConfigurationService $service */
        $service = App::make(BrandConfigurationService::class);

        return $service->update($record, $data, $user, Str::uuid()->toString());
    }

    protected function handleRecordDeletion(Model $record): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var BrandConfigurationService $service */
        $service = App::make(BrandConfigurationService::class);
        $service->delete($record, $user, Str::uuid()->toString());
    }
}
