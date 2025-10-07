<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\TicketSubmissionResource\Pages;
use App\Models\TicketSubmission;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketSubmissionResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = TicketSubmission::class;

    protected static ?string $navigationGroup = 'Tickets';

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Submission details')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextEntry::make('subject')->label('Subject')->weight('medium'),
                                TextEntry::make('status')->badge()
                                    ->color(fn (TicketSubmission $record) => match ($record->status) {
                                        TicketSubmission::STATUS_FAILED => 'danger',
                                        TicketSubmission::STATUS_PENDING => 'warning',
                                        default => 'success',
                                    }),
                                TextEntry::make('channel')->badge()->color('gray'),
                                TextEntry::make('submitted_at')->dateTime(),
                                TextEntry::make('correlation_id')->label('Correlation ID')->copyable(),
                                TextEntry::make('tags')->formatStateUsing(fn (?array $tags) => $tags ? implode(', ', $tags) : '—'),
                            ])->columns(2),
                        TextEntry::make('message')->label('Message')->html(false)->markdown(false)->columnSpanFull(),
                    ])->columns(2),
                Section::make('Contact')
                    ->schema([
                        TextEntry::make('contact.name')->label('Name'),
                        TextEntry::make('contact.email')->label('Email'),
                    ])->columns(2),
                Section::make('Ticket')
                    ->schema([
                        TextEntry::make('ticket_id')->label('Ticket #'),
                        TextEntry::make('ticket.status')->label('Status'),
                        TextEntry::make('ticket.priority')->label('Priority'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_id')
                    ->label('Ticket #')
                    ->sortable(),
                TextColumn::make('subject')
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('channel')
                    ->colors([
                        'primary' => TicketSubmission::CHANNEL_PORTAL,
                        'info' => TicketSubmission::CHANNEL_EMAIL,
                        'warning' => TicketSubmission::CHANNEL_CHAT,
                        'success' => TicketSubmission::CHANNEL_API,
                    ])
                    ->label('Channel')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => TicketSubmission::STATUS_ACCEPTED,
                        'warning' => TicketSubmission::STATUS_PENDING,
                        'danger' => TicketSubmission::STATUS_FAILED,
                    ])
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('contact.email')
                    ->label('Contact Email')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TagsColumn::make('tags')
                    ->label('Tags')
                    ->separator(', ')
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options([
                        TicketSubmission::CHANNEL_PORTAL => 'Portal',
                        TicketSubmission::CHANNEL_EMAIL => 'Email',
                        TicketSubmission::CHANNEL_CHAT => 'Chat',
                        TicketSubmission::CHANNEL_API => 'API',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        TicketSubmission::STATUS_ACCEPTED => 'Accepted',
                        TicketSubmission::STATUS_PENDING => 'Pending',
                        TicketSubmission::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketSubmissions::route('/'),
            'view' => Pages\ViewTicketSubmission::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['ticket', 'contact'])
            ->withCount('attachments')
            ->latest('submitted_at');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return static::userCan('tickets.view');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
