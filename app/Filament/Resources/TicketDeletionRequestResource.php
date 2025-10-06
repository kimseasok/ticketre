<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketDeletionRequestResource\Pages;
use App\Models\Brand;
use App\Models\Ticket;
use App\Models\TicketDeletionRequest;
use App\Services\TicketDeletionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class TicketDeletionRequestResource extends Resource
{
    protected static ?string $model = TicketDeletionRequest::class;

    protected static ?string $navigationGroup = 'Compliance';

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ticket_id')
                    ->relationship('ticket', 'subject', function (Builder $query) {
                        if (app()->bound('currentTenant') && app('currentTenant')) {
                            $query->where('tenant_id', app('currentTenant')->getKey());
                        }

                        $query->whereNull('deleted_at');
                    })
                    ->getOptionLabelFromRecordUsing(fn (Ticket $record) => sprintf('#%d · %s', $record->getKey(), Arr::get($record->metadata, 'redacted') ? 'Redacted' : $record->subject))
                    ->searchable()
                    ->required(),
                Forms\Components\Textarea::make('reason')
                    ->rows(4)
                    ->maxLength(500)
                    ->required()
                    ->helperText('Provide the legal justification for deleting ticket content.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket.subject')
                    ->label('Ticket')
                    ->formatStateUsing(function ($state, TicketDeletionRequest $record) {
                        return sprintf('#%d · %s', $record->ticket_id, $state ?? 'Redacted');
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => TicketDeletionRequest::STATUS_PENDING,
                        'info' => TicketDeletionRequest::STATUS_APPROVED,
                        'primary' => TicketDeletionRequest::STATUS_PROCESSING,
                        'success' => TicketDeletionRequest::STATUS_COMPLETED,
                        'danger' => TicketDeletionRequest::STATUS_FAILED,
                        'gray' => TicketDeletionRequest::STATUS_CANCELLED,
                    ])
                    ->label('Status')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requested By')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('approver.name')->label('Approved By')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('hold_expires_at')->label('Hold Expires')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('processed_at')->label('Processed At')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        TicketDeletionRequest::STATUS_PENDING => 'Pending',
                        TicketDeletionRequest::STATUS_APPROVED => 'Approved',
                        TicketDeletionRequest::STATUS_PROCESSING => 'Processing',
                        TicketDeletionRequest::STATUS_COMPLETED => 'Completed',
                        TicketDeletionRequest::STATUS_FAILED => 'Failed',
                        TicketDeletionRequest::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(function (): array {
                        if (! app()->bound('currentTenant') || ! app('currentTenant')) {
                            return [];
                        }

                        return Brand::query()
                            ->where('tenant_id', app('currentTenant')->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->visible(fn (TicketDeletionRequest $record) => $record->status === TicketDeletionRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\TextInput::make('hold_hours')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(168)
                            ->default(TicketDeletionService::DEFAULT_HOLD_HOURS)
                            ->label('Hold (hours)')
                            ->helperText('Delay processing to allow reversals (0-168 hours).'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (TicketDeletionRequest $record, array $data): void {
                        $hold = isset($data['hold_hours']) ? (int) $data['hold_hours'] : TicketDeletionService::DEFAULT_HOLD_HOURS;
                        $user = auth()->user();

                        if (! $user) {
                            abort(403, 'Authentication required.');
                        }

                        app(TicketDeletionService::class)->approve($record, $user, $hold);
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (TicketDeletionRequest $record) => in_array($record->status, [
                        TicketDeletionRequest::STATUS_PENDING,
                        TicketDeletionRequest::STATUS_APPROVED,
                    ], true))
                    ->requiresConfirmation()
                    ->action(function (TicketDeletionRequest $record): void {
                        $user = auth()->user();

                        if (! $user) {
                            abort(403, 'Authentication required.');
                        }

                        app(TicketDeletionService::class)->cancel($record, $user);
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTicketDeletionRequests::route('/'),
            'create' => Pages\CreateTicketDeletionRequest::route('/create'),
            'view' => Pages\ViewTicketDeletionRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['ticket', 'requester', 'approver', 'canceller', 'brand']);

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $brand = app('currentBrand');
            $query->where(function (Builder $builder) use ($brand) {
                $builder->where('brand_id', $brand?->getKey())
                    ->orWhereNull('brand_id');
            });
        }

        return $query;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
