<?php

namespace App\Filament\Resources\RbacEnforcementGapAnalysisResource\Pages;

use App\Filament\Resources\RbacEnforcementGapAnalysisResource;
use App\Models\User;
use App\Services\RbacEnforcementGapAnalysisService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRbacEnforcementGapAnalysis extends EditRecord
{
    protected static string $resource = RbacEnforcementGapAnalysisResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->userOrFail();

        return app(RbacEnforcementGapAnalysisService::class)->update($this->record, $data, $user);
    }
}
