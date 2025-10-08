<?php

namespace App\Filament\Resources\TicketRelationshipResource\Pages;

use App\Filament\Resources\TicketRelationshipResource;
use App\Services\TicketRelationshipService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class EditTicketRelationship extends EditRecord
{
    protected static string $resource = TicketRelationshipResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['context'] = collect($data['context'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        return App::make(TicketRelationshipService::class)->update($record, $data, $user);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        App::make(TicketRelationshipService::class)->delete($record, $user);
    }
}
