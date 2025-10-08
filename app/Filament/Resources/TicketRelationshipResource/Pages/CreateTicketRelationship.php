<?php

namespace App\Filament\Resources\TicketRelationshipResource\Pages;

use App\Filament\Resources\TicketRelationshipResource;
use App\Services\TicketRelationshipService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CreateTicketRelationship extends CreateRecord
{
    protected static string $resource = TicketRelationshipResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['primary_ticket_id'] = (int) ($data['primary_ticket_id'] ?? 0);
        $data['related_ticket_id'] = (int) ($data['related_ticket_id'] ?? 0);
        $data['context'] = collect($data['context'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        return App::make(TicketRelationshipService::class)->create($data, $user);
    }
}
