<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PortalSessionResource\Pages;
use App\Models\Brand;
use App\Models\PortalSession;
use App\Services\PortalSessionService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PortalSessionResource extends Resource
{
    protected static ?string $model = PortalSession::class;

    protected static ?string $navigationGroup = 'Portal';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Section::make('Session')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('id')->label('ID')->disabled(),
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\TextInput::make('account.email')->label('Account Email')->disabled(),
                    Forms\Components\TextInput::make('correlation_id')->label('Correlation ID')->disabled(),
                    Forms\Components\DateTimePicker::make('issued_at')->label('Issued')->disabled(),
                    Forms\Components\DateTimePicker::make('expires_at')->label('Expires')->disabled(),
                    Forms\Components\DateTimePicker::make('refresh_expires_at')->label('Refresh Expires')->disabled(),
                    Forms\Components\DateTimePicker::make('revoked_at')->label('Revoked')->disabled(),
                    Forms\Components\TextInput::make('ip_hash')->label('IP Hash')->disabled(),
                    Forms\Components\Textarea::make('user_agent')->label('User Agent')->disabled()->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Metadata')
                ->schema([
                    Forms\Components\KeyValue::make('metadata')->disabled()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('account.email')
                    ->label('Account Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'revoked',
                    ])
                    ->sortable(),
                TextColumn::make('issued_at')->label('Issued')->dateTime()->sortable(),
                TextColumn::make('expires_at')->label('Expires')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->label('Revoked')->dateTime()->sortable(),
                TextColumn::make('correlation_id')->label('Correlation ID')->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'revoked' => 'Revoked',
                    ])
                    ->query(function (Builder $query, ?string $state): Builder {
                        if ($state === 'active') {
                            return $query->whereNull('revoked_at')->where(function (Builder $builder): void {
                                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                        }

                        if ($state === 'revoked') {
                            return $query->whereNotNull('revoked_at');
                        }

                        return $query;
                    }),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => Brand::query()->pluck('name', 'id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('revoke')
                    ->label('Revoke')
                    ->requiresConfirmation()
                    ->hidden(fn (PortalSession $record) => $record->revoked_at !== null)
                    ->action(function (PortalSession $record): void {
                        $user = auth()->user();
                        if (! $user) {
                            throw new \RuntimeException('Authentication required.');
                        }

                        $correlationId = Str::uuid()->toString();
                        app(PortalSessionService::class)->revoke($record, $user, $correlationId, 'filament');
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortalSessions::route('/'),
            'view' => Pages\ViewPortalSession::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('account');
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
