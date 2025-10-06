<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketRelationshipResource\Pages;
use App\Models\TicketRelationship;
use Filament\Forms;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketRelationshipResource extends Resource
{
    protected static ?string $model = TicketRelationship::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('primary_ticket_id')
                    ->label('Primary Ticket')
                    ->relationship('primaryTicket', 'subject')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('related_ticket_id')
                    ->label('Related Ticket')
                    ->relationship('relatedTicket', 'subject')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('relationship_type')
                    ->options([
                        TicketRelationship::TYPE_MERGED => 'Merged',
                        TicketRelationship::TYPE_SPLIT => 'Split',
                        TicketRelationship::TYPE_DUPLICATE => 'Duplicate',
                    ])
                    ->required(),
                KeyValue::make('context')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('primaryTicket.subject')
                    ->label('Primary Ticket')
                    ->searchable(),
                Tables\Columns\TextColumn::make('relatedTicket.subject')
                    ->label('Related Ticket')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('relationship_type')
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->colors([
                        'primary' => TicketRelationship::TYPE_MERGED,
                        'success' => TicketRelationship::TYPE_DUPLICATE,
                        'warning' => TicketRelationship::TYPE_SPLIT,
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('relationship_type')
                    ->options([
                        TicketRelationship::TYPE_MERGED => 'Merged',
                        TicketRelationship::TYPE_SPLIT => 'Split',
                        TicketRelationship::TYPE_DUPLICATE => 'Duplicate',
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
            'index' => Pages\ListTicketRelationships::route('/'),
            'create' => Pages\CreateTicketRelationship::route('/create'),
            'edit' => Pages\EditTicketRelationship::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['primaryTicket', 'relatedTicket']);

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where('brand_id', app('currentBrand')->getKey());
        }

        return $query;
    }
}
