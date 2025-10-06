<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\Message;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ticket_id')
                    ->relationship('ticket', 'subject')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->required()
                    ->default(fn () => auth()->id()),
                Forms\Components\Select::make('visibility')
                    ->options([
                        Message::VISIBILITY_PUBLIC => 'Public',
                        Message::VISIBILITY_INTERNAL => 'Internal',
                    ])
                    ->default(Message::VISIBILITY_PUBLIC)
                    ->required(),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->maxLength(65535),
                Forms\Components\TextInput::make('author_role')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Role is derived from the selected author.'),
                Forms\Components\DateTimePicker::make('sent_at')
                    ->required()
                    ->default(fn () => now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket.subject')
                    ->label('Ticket')
                    ->searchable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('visibility')
                    ->colors([
                        'success' => Message::VISIBILITY_PUBLIC,
                        'warning' => Message::VISIBILITY_INTERNAL,
                    ]),
                Tables\Columns\TextColumn::make('author_role')
                    ->label('Author Role')
                    ->badge(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->options([
                        Message::VISIBILITY_PUBLIC => 'Public',
                        Message::VISIBILITY_INTERNAL => 'Internal',
                    ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->relationship('brand', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['author', 'ticket']);

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where('brand_id', app('currentBrand')->getKey());
        }

        return $query;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $ticket = Ticket::query()->findOrFail($data['ticket_id']);
        $data['tenant_id'] = $ticket->tenant_id;
        $data['brand_id'] = $ticket->brand_id;

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['ticket_id'])) {
            $ticket = Ticket::query()->find($data['ticket_id']);
            if ($ticket) {
                $data['tenant_id'] = $ticket->tenant_id;
                $data['brand_id'] = $ticket->brand_id;
            }
        }

        return $data;
    }
}
