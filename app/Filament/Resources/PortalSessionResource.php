<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PortalSessionResource\Pages;
use App\Models\PortalSession;
use App\Services\PortalAuthService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PortalSessionResource extends Resource
{
    protected static ?string $model = PortalSession::class;

    protected static ?string $navigationGroup = 'Portal';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Portal Sessions';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['contact', 'identity']);
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('contact.name')->label('Contact')->searchable()->sortable(),
                TextColumn::make('contact.email')->label('Email')->searchable(),
                TextColumn::make('provider')->sortable()->badge(),
                TextColumn::make('status')->badge()->colors([
                    'success' => 'active',
                    'warning' => 'expired',
                    'danger' => 'revoked',
                ])->sortable(),
                TextColumn::make('abilities')->label('Abilities')->limit(30)->tooltip(fn (PortalSession $record) => implode(', ', $record->abilities ?? [])),
                TextColumn::make('expires_at')->dateTime()->label('Access Expires'),
                TextColumn::make('refresh_expires_at')->dateTime()->label('Refresh Expires'),
                IconColumn::make('revoked_at')->label('Revoked')->boolean(),
                TextColumn::make('created_at')->dateTime()->label('Created'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $status = $data['value'] ?? null;

                        if ($status === 'active') {
                            $query->whereNull('revoked_at')
                                ->where(function ($builder) {
                                    $builder->whereNull('refresh_expires_at')
                                        ->orWhere('refresh_expires_at', '>', now());
                                });
                        } elseif ($status === 'revoked') {
                            $query->whereNotNull('revoked_at');
                        } elseif ($status === 'expired') {
                            $query->whereNull('revoked_at')
                                ->whereNotNull('refresh_expires_at')
                                ->where('refresh_expires_at', '<=', now());
                        }
                    }),
                SelectFilter::make('provider')
                    ->options(array_combine(config('portal.allowed_providers', []), config('portal.allowed_providers', []))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->can('portal.sessions.manage') ?? false)
                    ->action(function (PortalSession $record): void {
                        app(PortalAuthService::class)->revoke($record, auth()->user(), (string) Str::uuid());
                        $record->refresh();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPortalSessions::route('/'),
            'view' => Pages\ViewPortalSession::route('/{record}'),
        ];
    }
}
