<?php

namespace App\Filament\Resources\TwoFactorCredentialResource\Pages;

use App\Filament\Resources\TwoFactorCredentialResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTwoFactorCredential extends EditRecord
{
    protected static string $resource = TwoFactorCredentialResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            \Filament\Actions\Action::make('unlock')
                ->label('Unlock')
                ->visible(fn () => $this->record->isLocked())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->forceFill([
                        'failed_attempts' => 0,
                        'locked_until' => null,
                    ])->save();

                    Notification::make()
                        ->title('Two-factor credential unlocked')
                        ->success()
                        ->send();
                }),
        ]);
    }
}
