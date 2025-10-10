<?php

namespace App\Filament\Resources\PermissionCoverageReportResource\Pages;

use App\Filament\Resources\PermissionCoverageReportResource;
use App\Models\User;
use App\Services\PermissionCoverageReportService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePermissionCoverageReport extends CreateRecord
{
    protected static string $resource = PermissionCoverageReportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->userOrFail();

        return app(PermissionCoverageReportService::class)->create($data, $user);
    }
}
