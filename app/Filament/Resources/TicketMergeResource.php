<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketMergeResource\Pages;
use App\Models\Brand;
use App\Models\Ticket;
use App\Models\TicketMerge;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TicketMergeResource extends Resource
{
    protected static ?string $model = TicketMerge::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('primary_ticket_id')
                    ->label('Primary Ticket')
                    ->options(fn () => static::ticketOptions())
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::ticketOptions($search))
                    ->getOptionLabelUsing(fn ($value) => static::ticketOptions()[$value] ?? $value)
                    ->required(),
                Select::make('secondary_ticket_id')
                    ->label('Secondary Ticket')
                    ->options(fn () => static::ticketOptions())
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => static::ticketOptions($search))
                    ->getOptionLabelUsing(fn ($value) => static::ticketOptions()[$value] ?? $value)
                    ->required()
                    ->different('primary_ticket_id'),
                TextInput::make('correlation_id')
                    ->label('Correlation ID')
                    ->maxLength(64)
                    ->helperText('Optional NON-PRODUCTION correlation identifier used for audit logs.'),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable()->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => TicketMerge::STATUS_PROCESSING,
                        'success' => TicketMerge::STATUS_COMPLETED,
                        'danger' => TicketMerge::STATUS_FAILED,
                        'secondary' => TicketMerge::STATUS_PENDING,
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryTicket.subject')
                    ->label('Primary Ticket')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('secondaryTicket.subject')
                    ->label('Secondary Ticket')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('initiator.name')
                    ->label('Initiated By')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('summary.messages_migrated')
                    ->label('Messages')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('summary.events_migrated')
                    ->label('Events')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        TicketMerge::STATUS_PROCESSING => 'Processing',
                        TicketMerge::STATUS_COMPLETED => 'Completed',
                        TicketMerge::STATUS_FAILED => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketMerges::route('/'),
            'create' => Pages\CreateTicketMerge::route('/create'),
            'view' => Pages\ViewTicketMerge::route('/{record}'),
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

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where('brand_id', app('currentBrand')->getKey());
        }

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
