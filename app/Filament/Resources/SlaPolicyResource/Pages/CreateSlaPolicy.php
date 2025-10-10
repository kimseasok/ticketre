<?php

namespace App\Filament\Resources\SlaPolicyResource\Pages;

use App\Filament\Resources\SlaPolicyResource;
use App\Services\SlaPolicyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CreateSlaPolicy extends CreateRecord
{
    protected static string $resource = SlaPolicyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var SlaPolicyService $service */
        $service = App::make(SlaPolicyService::class);

        return $service->create($data, $user);
    }
}
