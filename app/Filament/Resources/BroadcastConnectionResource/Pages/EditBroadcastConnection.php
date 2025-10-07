<?php

namespace App\Filament\Resources\BroadcastConnectionResource\Pages;

use App\Filament\Resources\BroadcastConnectionResource;
use App\Models\BroadcastConnection;
use App\Models\User;
use App\Services\BroadcastConnectionService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class EditBroadcastConnection extends EditRecord
{
    protected static string $resource = BroadcastConnectionResource::class;

    protected function handleRecordUpdate($record, array $data): BroadcastConnection
    {
        if (! $record instanceof BroadcastConnection) {
            abort(500, 'Invalid broadcast connection context.');
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401, 'Authentication required.');
        }

        /** @var BroadcastConnectionService $service */
        $service = App::make(BroadcastConnectionService::class);

        return $service->update($record, $data, $user, 'filament');
    }
}
