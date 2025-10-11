<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmtpOutboundMessageResource\Pages;
use App\Models\Brand;
use App\Models\Message;
use App\Models\SmtpOutboundMessage;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SmtpOutboundMessageResource extends Resource
{
    protected static ?string $model = SmtpOutboundMessage::class;

    protected static ?string $navigationGroup = 'Email';

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Routing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('ticket_id')
                                    ->label('Ticket')
                                    ->relationship('ticket', 'subject')
                                    ->searchable()
                                    ->required()
                                    ->helperText('Queued emails inherit tenant and brand from the selected ticket.'),
                                Select::make('message_id')
                                    ->label('Linked Ticket Message')
                                    ->options(fn (callable $get): array => static::messageOptions($get('ticket_id')))
                                    ->getSearchResultsUsing(fn (string $search, callable $get): array => static::messageSearchResults($get('ticket_id'), $search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::messageLabel($value))
                                    ->searchable()
                                    ->nullable()
                                    ->reactive()
                                    ->disabled(fn (callable $get): bool => ! $get('ticket_id'))
                                    ->helperText('Optional reference to an existing ticket message.'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('from_email')
                                    ->label('From Email')
                                    ->email()
                                    ->required(),
                                TextInput::make('from_name')
                                    ->label('From Name')
                                    ->maxLength(255),
                                TextInput::make('subject')
                                    ->label('Subject')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('mailer')
                                    ->label('Mailer')
                                    ->default('smtp')
                                    ->maxLength(64),
                            ]),
                    ]),
                Section::make('Recipients')
                    ->schema([
                        Repeater::make('to')
                            ->label('To')
                            ->required()
                            ->schema([
                                TextInput::make('email')->email()->required()->maxLength(255),
                                TextInput::make('name')->maxLength(255),
                            ])->columns(2),
                        Repeater::make('cc')
                            ->label('CC')
                            ->schema([
                                TextInput::make('email')->email()->required()->maxLength(255),
                                TextInput::make('name')->maxLength(255),
                            ])->columns(2),
                        Repeater::make('bcc')
                            ->label('BCC')
                            ->schema([
                                TextInput::make('email')->email()->required()->maxLength(255),
                                TextInput::make('name')->maxLength(255),
                            ])->columns(2),
                        Repeater::make('reply_to')
                            ->label('Reply-To')
                            ->schema([
                                TextInput::make('email')->email()->required()->maxLength(255),
                                TextInput::make('name')->maxLength(255),
                            ])->columns(2),
                    ])->columns(1),
                Section::make('Content')
                    ->schema([
                        Textarea::make('body_html')
                            ->label('HTML Body')
                            ->rows(6)
                            ->columnSpanFull(),
                        Textarea::make('body_text')
                            ->label('Text Body')
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
                Section::make('Attachments & Headers')
                    ->schema([
                        Repeater::make('attachments')
                            ->label('Attachments')
                            ->schema([
                                TextInput::make('disk')->label('Disk')->maxLength(64),
                                TextInput::make('path')->label('Path')->required()->maxLength(2048),
                                TextInput::make('name')->label('Filename')->maxLength(255),
                                TextInput::make('mime_type')->label('MIME Type')->maxLength(255),
                                TextInput::make('size')->label('Size (bytes)')->numeric()->minValue(0),
                            ])->columns(3),
                        KeyValue::make('headers')
                            ->label('Headers')
                            ->keyLabel('Header')
                            ->valueLabel('Value')
                            ->helperText('Additional SMTP headers. Values are truncated in logs.'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable()->sortable(),
                TextColumn::make('ticket.subject')->label('Ticket')->toggleable(),
                BadgeColumn::make('status')->colors([
                    'warning' => fn ($state) => in_array($state, [SmtpOutboundMessage::STATUS_QUEUED, SmtpOutboundMessage::STATUS_SENDING, SmtpOutboundMessage::STATUS_RETRYING], true),
                    'success' => SmtpOutboundMessage::STATUS_SENT,
                    'danger' => SmtpOutboundMessage::STATUS_FAILED,
                ])->sortable(),
                TextColumn::make('attempts')->label('Attempts')->sortable(),
                TextColumn::make('delivered_at')->dateTime()->sortable()->label('Delivered'),
                TextColumn::make('updated_at')->dateTime()->sortable()->label('Updated'),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => Brand::query()->orderBy('name')->pluck('name', 'id')->toArray()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        SmtpOutboundMessage::STATUS_QUEUED => 'Queued',
                        SmtpOutboundMessage::STATUS_SENDING => 'Sending',
                        SmtpOutboundMessage::STATUS_RETRYING => 'Retrying',
                        SmtpOutboundMessage::STATUS_SENT => 'Sent',
                        SmtpOutboundMessage::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmtpOutboundMessages::route('/'),
            'create' => Pages\CreateSmtpOutboundMessage::route('/create'),
            'view' => Pages\ViewSmtpOutboundMessage::route('/{record}'),
            'edit' => Pages\EditSmtpOutboundMessage::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function messageOptions(int|string|null $ticketId): array
    {
        if (! $ticketId) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = static::baseMessageQuery($ticketId)
            ->limit(50)
            ->get()
            ->mapWithKeys(function ($model, int $index): array {
                /** @var Message $message */
                $message = $model;

                /** @var array<string, string> $result */
                $result = [
                    (string) $message->getKey() => static::formatMessageLabel($message),
                ];

                return $result;
            })->all();

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected static function messageSearchResults(int|string|null $ticketId, string $search): array
    {
        if (! $ticketId) {
            return [];
        }

        /** @var array<string, string> $options */
        $options = static::baseMessageQuery($ticketId)
            ->where(function (Builder $query) use ($search): void {
                $query->where('body', 'like', '%'.$search.'%');

                if (is_numeric($search)) {
                    $query->orWhere('id', (int) $search);
                }
            })
            ->limit(50)
            ->get()
            ->mapWithKeys(function ($model, int $index): array {
                /** @var Message $message */
                $message = $model;

                /** @var array<string, string> $result */
                $result = [
                    (string) $message->getKey() => static::formatMessageLabel($message),
                ];

                return $result;
            })->all();

        return $options;
    }

    protected static function messageLabel(int|string|null $messageId): ?string
    {
        if (! $messageId) {
            return null;
        }

        $message = Message::query()->find($messageId);

        return $message ? static::formatMessageLabel($message) : null;
    }

    protected static function baseMessageQuery(int|string $ticketId): Builder
    {
        return Message::query()
            ->where('ticket_id', $ticketId)
            ->latest('id');
    }

    protected static function formatMessageLabel(Message $message): string
    {
        return '#'.$message->getKey().' · '.Str::limit((string) $message->body, 60);
    }
}
