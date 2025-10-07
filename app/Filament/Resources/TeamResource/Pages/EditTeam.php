<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use App\Services\TeamService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord()->load('memberships');

        $data['members'] = $record->memberships
            ->map(fn ($membership) => [
                'user_id' => $membership->user_id,
                'role' => $membership->role,
                'is_primary' => (bool) $membership->is_primary,
            ])
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var TeamService $service */
        $service = App::make(TeamService::class);

        return $service->update($record, $data, $user);
    }
}
