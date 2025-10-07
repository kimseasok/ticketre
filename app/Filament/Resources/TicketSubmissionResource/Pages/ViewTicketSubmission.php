<?php

namespace App\Filament\Resources\TicketSubmissionResource\Pages;

use App\Filament\Resources\TicketSubmissionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTicketSubmission extends ViewRecord
{
    protected static string $resource = TicketSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
