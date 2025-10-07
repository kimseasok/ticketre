<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\TeamResource\Pages;
use App\Models\Brand;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class TeamResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = Team::class;

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255)
                    ->disabled(fn (?Team $record) => $record !== null)
                    ->helperText('Slug generated automatically when left blank.'),
                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->nullable()
                    ->searchable()
                    ->options(function () {
                        $query = Brand::query()->orderBy('name');

                        if (app()->bound('currentTenant') && app('currentTenant')) {
                            $query->where('tenant_id', app('currentTenant')->getKey());
                        }

                        return $query->pluck('name', 'id')->toArray();
                    }),
                Forms\Components\TextInput::make('default_queue')
                    ->label('Default queue')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->maxLength(65535),
                Forms\Components\Repeater::make('members')
                    ->label('Members')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->required()
                            ->searchable()
                            ->options(function () {
                                $query = User::query()->orderBy('name');

                                if (app()->bound('currentTenant') && app('currentTenant')) {
                                    $query->where('tenant_id', app('currentTenant')->getKey());
                                }

                                return $query->pluck('name', 'id')->toArray();
                            }),
                        Forms\Components\TextInput::make('role')
                            ->label('Team role')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Toggle::make('is_primary')
                            ->label('Primary assignment')
                            ->default(false),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->default([]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Identifier')->copyable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('default_queue')->label('Queue')->toggleable(),
                Tables\Columns\BadgeColumn::make('memberships_count')
                    ->label('Members')
                    ->counts('memberships')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(fn (Team $record) => static::deleteTeam($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records->each(fn (Team $team) => static::deleteTeam($team))),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('memberships');

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        return $query;
    }

    protected static function deleteTeam(Team $team): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var TeamService $service */
        $service = App::make(TeamService::class);
        $service->delete($team, $user);
    }

    public static function canViewAny(): bool
    {
        return static::userCan('teams.view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('teams.manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCan('teams.manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCan('teams.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
