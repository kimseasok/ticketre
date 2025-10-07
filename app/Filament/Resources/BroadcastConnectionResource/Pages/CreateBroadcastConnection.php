<?php

namespace App\Filament\Resources\BroadcastConnectionResource\Pages;

use App\Filament\Resources\BroadcastConnectionResource;
use App\Models\User;
use App\Services\BroadcastConnectionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CreateBroadcastConnection extends CreateRecord
{
    protected static string $resource = BroadcastConnectionResource::class;

    protected function handleRecordCreation(array $data): \App\Models\BroadcastConnection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401, 'Authentication required.');
        }

        /** @var BroadcastConnectionService $service */
        $service = App::make(BroadcastConnectionService::class);

        return $service->create($data, $user, 'filament');
    }
}
