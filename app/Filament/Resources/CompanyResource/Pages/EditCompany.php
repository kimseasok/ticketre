<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Services\CompanyService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var CompanyService $service */
        $service = App::make(CompanyService::class);

        return $service->update($record, $data, $user, request()?->header('X-Correlation-ID'));
    }

    protected function handleRecordDeletion(Model $record): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var CompanyService $service */
        $service = App::make(CompanyService::class);

        $service->delete($record, $user, request()?->header('X-Correlation-ID'));
    }
}
