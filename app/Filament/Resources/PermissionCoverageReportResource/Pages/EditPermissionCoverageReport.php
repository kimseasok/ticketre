<?php

namespace App\Filament\Resources\PermissionCoverageReportResource\Pages;

use App\Filament\Resources\PermissionCoverageReportResource;
use App\Models\User;
use App\Services\PermissionCoverageReportService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPermissionCoverageReport extends EditRecord
{
    protected static string $resource = PermissionCoverageReportResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->userOrFail();

        return app(PermissionCoverageReportService::class)->update($this->record, $data, $user);
    }
}
