<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('subject')->required()->maxLength(255),
                Forms\Components\Select::make('status')->options([
                    'open' => 'Open',
                    'pending' => 'Pending',
                    'closed' => 'Closed',
                ])->required(),
                Forms\Components\Select::make('priority')->options([
                    'low' => 'Low',
                    'medium' => 'Medium',
                    'high' => 'High',
                ])->required(),
                Forms\Components\Select::make('assignee_id')
                    ->label('Assignee')
                    ->searchable()
                    ->relationship('assignee', 'name')
                    ->nullable(),
                Forms\Components\Textarea::make('metadata')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('assignee.name')->label('Assignee'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'pending' => 'Pending',
                    'closed' => 'Closed',
                ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['assignee']);

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        return $query;
    }
}
