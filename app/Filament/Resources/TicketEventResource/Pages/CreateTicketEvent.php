<?php

namespace App\Filament\Resources\TicketEventResource\Pages;

use App\Filament\Resources\TicketEventResource;
use App\Models\Ticket;
use App\Services\TicketLifecycleBroadcaster;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateTicketEvent extends CreateRecord
{
    protected static string $resource = TicketEventResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        $payload = [];
        if (! empty($data['payload'])) {
            $payload = json_decode($data['payload'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'payload' => 'Payload must be valid JSON.',
                ]);
            }
        }

        /** @var TicketLifecycleBroadcaster $broadcaster */
        $broadcaster = App::make(TicketLifecycleBroadcaster::class);

        $ticket = Ticket::query()->findOrFail($data['ticket_id']);

        return $broadcaster->record($ticket->fresh(), $data['type'], $payload, $user, $data['visibility']);
    }
}
