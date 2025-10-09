<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Models\Brand;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Permission')
                    ->disabled(fn (?Permission $record) => $record?->is_system),
                Forms\Components\TextInput::make('slug')
                    ->label('Identifier')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Select::make('brand_id')
                    ->label('Brand scope')
                    ->options(function () {
                        if (! app()->bound('currentTenant') || ! app('currentTenant')) {
                            return [];
                        }

                        return Brand::query()
                            ->where('tenant_id', app('currentTenant')->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->prepend('All brands', '')
                            ->toArray();
                    })
                    ->hint('Leave empty to make the permission available to all brands within the tenant.')
                    ->searchable()
                    ->nullable()
                    ->default('')
                    ->disabled(fn (?Permission $record) => $record?->is_system),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->maxLength(65535)
                    ->helperText('Optional context for audit trails and documentation.'),
                Forms\Components\Toggle::make('is_system')
                    ->label('System permission')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Permission')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('Identifier')->copyable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->sortable()
                    ->default('All brands')
                    ->formatStateUsing(fn (?string $state) => $state ?? 'All brands'),
                Tables\Columns\IconColumn::make('is_system')->label('System')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand scope')
                    ->options(function () {
                        if (! app()->bound('currentTenant') || ! app('currentTenant')) {
                            return [];
                        }

                        return Brand::query()
                            ->where('tenant_id', app('currentTenant')->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->prepend('All brands', 'null')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === 'null') {
                            return $query->whereNull('brand_id');
                        }

                        if ($data['value'] ?? null) {
                            return $query->where('brand_id', $data['value']);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Permission $record) => ! $record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn () => false),
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
        $query = parent::getEloquentQuery()->with('brand');

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $brand = app('currentBrand');

            $query->where(function (Builder $builder) use ($brand): void {
                $builder
                    ->whereNull('brand_id')
                    ->orWhere('brand_id', $brand->getKey());
            });
        }

        return $query;
    }
}
