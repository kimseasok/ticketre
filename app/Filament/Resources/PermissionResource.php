<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermissionResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = Permission::class;

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(191)
                    ->disabled(fn (?Permission $record) => $record?->is_system),
                Forms\Components\TextInput::make('slug')
                    ->label('Identifier')
                    ->maxLength(191)
                    ->disabled(fn (?Permission $record) => $record?->is_system)
                    ->helperText('Leave blank to generate from the name.'),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->maxLength(255)
                    ->helperText('Describe when to grant this permission. Stored as hashed digest in audit logs.'),
                Forms\Components\TextInput::make('guard_name')
                    ->label('Guard')
                    ->default('web')
                    ->required()
                    ->maxLength(60)
                    ->disabled(fn (?Permission $record) => $record?->is_system)
                    ->helperText('Guard used for authorization checks. Tenant APIs default to the "web" guard.'),
                Forms\Components\Toggle::make('is_system')
                    ->label('System permission')
                    ->disabled(fn (?Permission $record) => $record?->is_system)
                    ->helperText('System permissions are provisioned globally and cannot be removed.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Identifier')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_system')
                    ->label('System')
                    ->boolean(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_system')
                    ->label('System permission')
                    ->placeholder('All permissions')
                    ->trueLabel('System only')
                    ->falseLabel('Custom permissions'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Permission $record) => ! $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where(function ($builder) {
                $tenantId = app('currentTenant')->getKey();
                $builder->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            });
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return static::userCan('permissions.view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('permissions.manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCan('permissions.manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCan('permissions.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
