<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Filament\Resources\TeamResource\RelationManagers\MembershipsRelationManager;
use App\Models\Brand;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(255)
                    ->helperText('Unique identifier used for routing and API access.'),
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions())
                    ->searchable()
                    ->preload()
                    ->helperText('Optional brand scope; leave blank for tenant-wide teams.'),
                TextInput::make('default_queue')
                    ->label('Default Queue')
                    ->maxLength(255)
                    ->helperText('Used for automatic assignment and reporting.'),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('NON-PRODUCTION guidance. Describe the team purpose for operators.'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->toggleable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->toggleable(),
                Tables\Columns\TextColumn::make('default_queue')->label('Default Queue')->toggleable(),
                Tables\Columns\TextColumn::make('memberships_count')
                    ->counts('memberships')
                    ->label('Members')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            MembershipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'view' => Pages\ViewTeam::route('/{record}'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function brandOptions(): array
    {
        return Brand::query()
            ->when(app()->bound('currentTenant') && app('currentTenant'), fn ($query) => $query->where('tenant_id', app('currentTenant')->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
