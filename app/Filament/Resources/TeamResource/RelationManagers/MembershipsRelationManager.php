<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use App\Models\TeamMembership;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(fn () => $this->userOptions())
                    ->searchable()
                    ->required(),
                Select::make('role')
                    ->options(array_combine(TeamMembership::ROLES, TeamMembership::ROLES))
                    ->required(),
                Toggle::make('is_primary')->inline(false),
                DateTimePicker::make('joined_at')->default(now()),
            ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Member')->searchable(),
                Tables\Columns\TextColumn::make('role')->badge(),
                Tables\Columns\IconColumn::make('is_primary')->boolean()->label('Primary'),
                Tables\Columns\TextColumn::make('joined_at')->dateTime()->label('Joined'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('joined_at', 'desc');
    }

    /**
     * @return array<int|string, string>
     */
    protected function userOptions(): array
    {
        $team = $this->getOwnerRecord();

        return User::query()
            ->where('tenant_id', $team->tenant_id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
