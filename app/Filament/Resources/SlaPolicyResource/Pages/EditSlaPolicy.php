<?php

namespace App\Filament\Resources\SlaPolicyResource\Pages;

use App\Filament\Resources\SlaPolicyResource;
use App\Services\SlaPolicyService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class EditSlaPolicy extends EditRecord
{
    protected static string $resource = SlaPolicyResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var SlaPolicyService $service */
        $service = App::make(SlaPolicyService::class);

        return $service->update($record, $data, $user);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var SlaPolicyService $service */
        $service = App::make(SlaPolicyService::class);

        $service->delete($record, $user);
    }
}
