<?php

namespace App\Filament\Resources\BrandResource\RelationManagers;

use App\Models\BrandDomain;
use App\Services\BrandDomainService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('domain')
                    ->label('Domain')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Enter the hostname configured in DNS (e.g. support.example.com).'),
                TextInput::make('verification_token')
                    ->label('Verification Token')
                    ->maxLength(64)
                    ->helperText('Token is placed in a TXT record for DNS verification. Leave blank to auto-generate.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->label('Domain')->searchable(),
                BadgeColumn::make('status')
                    ->colors([
                        'primary' => 'pending',
                        'warning' => 'verifying',
                        'success' => 'verified',
                        'danger' => 'failed',
                    ]),
                BadgeColumn::make('ssl_status')
                    ->label('SSL')
                    ->colors([
                        'success' => 'active',
                        'primary' => 'unverified',
                    ]),
                TextColumn::make('updated_at')->dateTime()->label('Updated'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): Model {
                        $user = Auth::user();

                        if (! $user) {
                            abort(401, 'Authentication required.');
                        }

                        /** @var BrandDomainService $service */
                        $service = App::make(BrandDomainService::class);

                        return $service->create($data, $this->getOwnerRecord(), $user, Str::uuid()->toString());
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('verify')
                        ->label('Queue Verification')
                        ->icon('heroicon-o-bolt')
                        ->requiresConfirmation()
                        ->visible(fn (BrandDomain $record) => $record->status !== 'verified')
                        ->action(function (BrandDomain $record): void {
                            $user = Auth::user();

                            if (! $user) {
                                abort(401, 'Authentication required.');
                            }

                            /** @var BrandDomainService $service */
                            $service = App::make(BrandDomainService::class);
                            $service->beginVerification($record, $user, Str::uuid()->toString());
                        }),
                    ViewAction::make(),
                    EditAction::make()
                        ->using(function (BrandDomain $record, array $data): Model {
                            $user = Auth::user();

                            if (! $user) {
                                abort(401, 'Authentication required.');
                            }

                            /** @var BrandDomainService $service */
                            $service = App::make(BrandDomainService::class);

                            return $service->update($record, $data, $user, Str::uuid()->toString());
                        }),
                    DeleteAction::make()
                        ->action(function (BrandDomain $record): void {
                            $user = Auth::user();

                            if (! $user) {
                                abort(401, 'Authentication required.');
                            }

                            /** @var BrandDomainService $service */
                            $service = App::make(BrandDomainService::class);
                            $service->delete($record, $user, Str::uuid()->toString());
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
