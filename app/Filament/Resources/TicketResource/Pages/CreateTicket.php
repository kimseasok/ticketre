<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Services\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var TicketService $service */
        $service = App::make(TicketService::class);

        return $service->create($data, $user);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['category_ids'] = $this->normalizeIdentifiers($data['category_ids'] ?? []);
        $data['tag_ids'] = $this->normalizeIdentifiers($data['tag_ids'] ?? []);

        return $data;
    }

    /**
     * @param  array<int|string>  $values
     * @return array<int>
     */
    protected function normalizeIdentifiers(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(static fn ($value) => (int) $value, $values), static fn ($value) => $value > 0)));
    }
}
