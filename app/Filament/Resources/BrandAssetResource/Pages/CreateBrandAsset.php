<?php

namespace App\Filament\Resources\BrandAssetResource\Pages;

use App\Filament\Resources\BrandAssetResource;
use App\Services\BrandAssetService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateBrandAsset extends CreateRecord
{
    protected static string $resource = BrandAssetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var BrandAssetService $service */
        $service = App::make(BrandAssetService::class);

        return $service->create($data, $user, Str::uuid()->toString());
    }
}
