<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Object')
                    ->formatStateUsing(fn (string $state) => Str::afterLast($state, '\\'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('auditable_id')->label('Object ID')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Actor')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('changes')
                    ->limit(60)
                    ->formatStateUsing(fn (?array $state) => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')->label('Action')
                    ->options(fn () => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                Tables\Filters\SelectFilter::make('user_id')->label('Actor')
                    ->relationship('user', 'name'),
                Filter::make('created_at')
                    ->form([
                        Tables\Filters\Components\DatePicker::make('from'),
                        Tables\Filters\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Audit Log Details')
                    ->modalContent(fn (AuditLog $record) => view('filament.audit-log-view', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user']);

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where(function (Builder $inner) {
                $brand = app('currentBrand');
                $inner->where('brand_id', $brand?->getKey())
                    ->orWhereNull('brand_id');
            });
        }

        return $query;
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
}
