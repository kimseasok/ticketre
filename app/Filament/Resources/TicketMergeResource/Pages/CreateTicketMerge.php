<?php

namespace App\Filament\Resources\TicketMergeResource\Pages;

use App\Filament\Resources\TicketMergeResource;
use App\Services\TicketMergeService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateTicketMerge extends CreateRecord
{
    protected static string $resource = TicketMergeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(401, 'Authentication required.');
        }

        return app(TicketMergeService::class)->merge($data, $actor, $data['correlation_id'] ?? null);
    }
}
