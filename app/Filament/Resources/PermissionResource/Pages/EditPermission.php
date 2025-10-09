<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use App\Services\PermissionService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var PermissionService $service */
        $service = App::make(PermissionService::class);

        return $service->update($record, $data, $user);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var PermissionService $service */
        $service = App::make(PermissionService::class);

        $service->delete($record, $user);
    }
}
