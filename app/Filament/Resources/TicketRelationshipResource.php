<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketRelationshipResource\Pages;
use App\Models\Brand;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use Filament\Forms;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TicketRelationshipResource extends Resource
{
    protected static ?string $model = TicketRelationship::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('relationship_type')
                    ->label('Relationship Type')
                    ->options([
                        TicketRelationship::TYPE_MERGE => 'Merge',
                        TicketRelationship::TYPE_SPLIT => 'Split',
                        TicketRelationship::TYPE_DUPLICATE => 'Duplicate',
                    ])
                    ->required(),
                Select::make('primary_ticket_id')
                    ->label('Primary Ticket')
                    ->options(fn () => static::ticketOptions())
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::ticketOptions($search))
                    ->getOptionLabelUsing(fn ($value) => static::ticketOptions()[$value] ?? $value)
                    ->required(),
                Select::make('related_ticket_id')
                    ->label('Related Ticket')
                    ->options(fn () => static::ticketOptions())
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::ticketOptions($search))
                    ->getOptionLabelUsing(fn ($value) => static::ticketOptions()[$value] ?? $value)
                    ->required(),
                KeyValue::make('context')
                    ->label('Context Metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Optional NON-PRODUCTION metadata stored for observability and audit trails.')
                    ->columnSpanFull(),
                TextInput::make('correlation_id')
                    ->label('Correlation ID')
                    ->maxLength(64)
                    ->helperText('Optional correlation identifier propagated to logs and API responses.'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                Tables\Columns\BadgeColumn::make('relationship_type')
                    ->label('Type')
                    ->colors([
                        'primary' => TicketRelationship::TYPE_MERGE,
                        'warning' => TicketRelationship::TYPE_SPLIT,
                        'info' => TicketRelationship::TYPE_DUPLICATE,
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryTicket.subject')
                    ->label('Primary Ticket')
                    ->toggleable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('relatedTicket.subject')
                    ->label('Related Ticket')
                    ->toggleable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('relationship_type')
                    ->label('Relationship Type')
                    ->options([
                        TicketRelationship::TYPE_MERGE => 'Merge',
                        TicketRelationship::TYPE_SPLIT => 'Split',
                        TicketRelationship::TYPE_DUPLICATE => 'Duplicate',
                    ]),
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
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketRelationships::route('/'),
            'create' => Pages\CreateTicketRelationship::route('/create'),
            'view' => Pages\ViewTicketRelationship::route('/{record}'),
            'edit' => Pages\EditTicketRelationship::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function ticketOptions(string $search = ''): array
    {
        $query = Ticket::query()
            ->select(['id', 'subject'])
            ->orderByDesc('created_at')
            ->limit(50);

        if ($search !== '') {
            $query->where('subject', 'like', '%'.$search.'%');
        }

        return $query->get()->mapWithKeys(function (Ticket $ticket) {
            $label = sprintf('#%d · %s', $ticket->getKey(), Str::limit((string) $ticket->subject, 60, '…'));

            return [$ticket->getKey() => $label];
        })->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected static function brandOptions(): array
    {
        return Brand::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
