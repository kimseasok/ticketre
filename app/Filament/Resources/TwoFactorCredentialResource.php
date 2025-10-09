<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TwoFactorCredentialResource\Pages;
use App\Models\TwoFactorCredential;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TwoFactorCredentialResource extends Resource
{
    protected static ?string $model = TwoFactorCredential::class;

    protected static ?string $navigationGroup = 'Security';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->required()
                ->disabledOn('edit'),
            Forms\Components\TextInput::make('label')
                ->maxLength(255)
                ->helperText('Optional label shown in audit logs.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                if ($tenant = auth()->user()?->tenant_id) {
                    $query->where('tenant_id', $tenant);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('User')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (TwoFactorCredential $record): string => $record->isLocked() ? 'Locked' : ($record->isConfirmed() ? 'Active' : 'Pending'))
                    ->colors([
                        'warning' => 'Pending',
                        'success' => 'Active',
                        'danger' => 'Locked',
                    ]),
                Tables\Columns\TextColumn::make('confirmed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('locked_until')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('recovery_codes_count')
                    ->counts('recoveryCodes as recovery_codes_count')
                    ->label('Recovery Codes'),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->visible(fn () => auth()->user()?->can('security.2fa.review') ?? false),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->visible(fn () => auth()->user()?->can('security.2fa.review') ?? false),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('security.2fa.review') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('security.2fa.review') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('security.2fa.review') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('security.2fa.review') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTwoFactorCredentials::route('/'),
            'create' => Pages\CreateTwoFactorCredential::route('/create'),
            'edit' => Pages\EditTwoFactorCredential::route('/{record}/edit'),
            'view' => Pages\ViewTwoFactorCredential::route('/{record}'),
        ];
    }
}
