<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\TicketEventResource\Pages;
use App\Models\TicketEvent;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketEventResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = TicketEvent::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ticket_id')
                    ->relationship('ticket', 'subject')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('type')
                    ->options([
                        TicketEvent::TYPE_CREATED => 'Ticket Created',
                        TicketEvent::TYPE_UPDATED => 'Ticket Updated',
                        TicketEvent::TYPE_ASSIGNED => 'Ticket Assigned',
                        TicketEvent::TYPE_MERGED => 'Ticket Merged',
                    ])
                    ->required(),
                Forms\Components\Select::make('visibility')
                    ->options([
                        TicketEvent::VISIBILITY_INTERNAL => 'Internal',
                        TicketEvent::VISIBILITY_PUBLIC => 'Public',
                    ])
                    ->default(TicketEvent::VISIBILITY_INTERNAL)
                    ->required(),
                Textarea::make('payload')
                    ->rows(6)
                    ->helperText('Provide JSON payload to merge with normalized event data.')
                    ->default(fn () => json_encode([], JSON_PRETTY_PRINT))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket.subject')->label('Ticket')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('visibility')->badge(),
                Tables\Columns\TextColumn::make('broadcasted_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('initiator.name')->label('Initiator'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        TicketEvent::TYPE_CREATED => 'Ticket Created',
                        TicketEvent::TYPE_UPDATED => 'Ticket Updated',
                        TicketEvent::TYPE_ASSIGNED => 'Ticket Assigned',
                        TicketEvent::TYPE_MERGED => 'Ticket Merged',
                    ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('broadcasted_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketEvents::route('/'),
            'create' => Pages\CreateTicketEvent::route('/create'),
            'view' => Pages\ViewTicketEvent::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['ticket', 'initiator']);

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where('brand_id', app('currentBrand')->getKey());
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return static::userCan('tickets.view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('tickets.manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCan('tickets.manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCan('tickets.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
