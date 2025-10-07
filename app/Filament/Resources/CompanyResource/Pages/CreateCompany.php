<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Services\CompanyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var CompanyService $service */
        $service = App::make(CompanyService::class);

        return $service->create($data, $user);
    }
}
