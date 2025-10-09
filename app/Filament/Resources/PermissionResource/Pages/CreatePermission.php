<?php

namespace App\Filament\Resources\PermissionResource\Pages;

use App\Filament\Resources\PermissionResource;
use App\Services\PermissionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var PermissionService $service */
        $service = App::make(PermissionService::class);

        return $service->create($data, $user);
    }
}
