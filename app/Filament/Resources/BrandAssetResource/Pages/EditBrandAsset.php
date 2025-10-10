<?php

namespace App\Filament\Resources\BrandAssetResource\Pages;

use App\Filament\Resources\BrandAssetResource;
use App\Services\BrandAssetService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EditBrandAsset extends EditRecord
{
    protected static string $resource = BrandAssetResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var BrandAssetService $service */
        $service = App::make(BrandAssetService::class);

        return $service->update($record, $data, $user, Str::uuid()->toString());
    }

    protected function handleRecordDeletion(Model $record): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var BrandAssetService $service */
        $service = App::make(BrandAssetService::class);
        $service->delete($record, $user, Str::uuid()->toString());
    }
}
