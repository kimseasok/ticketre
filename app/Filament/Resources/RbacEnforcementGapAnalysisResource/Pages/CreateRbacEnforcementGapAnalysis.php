<?php

namespace App\Filament\Resources\RbacEnforcementGapAnalysisResource\Pages;

use App\Filament\Resources\RbacEnforcementGapAnalysisResource;
use App\Models\User;
use App\Services\RbacEnforcementGapAnalysisService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRbacEnforcementGapAnalysis extends CreateRecord
{
    protected static string $resource = RbacEnforcementGapAnalysisResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->userOrFail();

        return app(RbacEnforcementGapAnalysisService::class)->create($data, $user);
    }
}
