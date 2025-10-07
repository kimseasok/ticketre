<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\KbCategoryResource\Pages;
use App\Models\KbCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class KbCategoryResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = KbCategory::class;

    protected static ?string $navigationGroup = 'Knowledge Base';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->default(fn () => app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null)
                    ->required(),
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name', function (Builder $query) {
                        if (app()->bound('currentBrand') && app('currentBrand')) {
                            $query->where('brand_id', app('currentBrand')->getKey());
                        }

                        return $query->orderBy('name');
                    })
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (Get $get, ?KbCategory $record) => Rule::unique('kb_categories', 'slug')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->where('brand_id', $get('brand_id'))
                        ->ignore($record?->getKey())),
                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->sortable(),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent')->toggleable(),
                Tables\Columns\TextColumn::make('depth')->sortable(),
                Tables\Columns\TextColumn::make('order')->sortable(),
                Tables\Columns\TextColumn::make('path')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKbCategories::route('/'),
            'create' => Pages\CreateKbCategory::route('/create'),
            'edit' => Pages\EditKbCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['parent', 'brand']);

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where('brand_id', app('currentBrand')->getKey());
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return static::userCan('knowledge.view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('knowledge.manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCan('knowledge.manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCan('knowledge.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
