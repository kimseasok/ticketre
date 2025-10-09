<?php

namespace App\Filament\Resources\TwoFactorCredentialResource\Pages;

use App\Filament\Resources\TwoFactorCredentialResource;
use App\Models\User;
use App\Services\TwoFactorService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateTwoFactorCredential extends CreateRecord
{
    protected static string $resource = TwoFactorCredentialResource::class;

    protected ?string $secret = null;

    protected ?string $otpauthUrl = null;

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::query()
            ->whereKey($data['user_id'])
            ->firstOrFail();

        $service = app(TwoFactorService::class);
        $correlationId = (string) Str::uuid();

        $result = $service->startEnrollment($user, $data['label'] ?? null, $correlationId);

        $this->secret = $result['secret'];
        $this->otpauthUrl = $result['uri'];

        return $result['credential'];
    }

    protected function afterCreate(): void
    {
        if ($this->secret && $this->otpauthUrl) {
            Notification::make()
                ->title('Two-factor enrollment started')
                ->body("Provide this secret to the user during enrollment: {$this->secret}\nOTP URI: {$this->otpauthUrl}")
                ->success()
                ->persistent()
                ->send();
        }
    }
}
