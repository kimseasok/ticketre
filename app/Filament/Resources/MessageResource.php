<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\MessageResource\Pages;
use App\Models\Message;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = Message::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('ticket_id')
                    ->relationship('ticket', 'subject')
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('visibility')
                    ->options([
                        Message::VISIBILITY_PUBLIC => 'Public',
                        Message::VISIBILITY_INTERNAL => 'Internal',
                    ])
                    ->required(),
                Forms\Components\Select::make('author_role')
                    ->options([
                        Message::ROLE_AGENT => 'Agent',
                        Message::ROLE_CONTACT => 'Contact',
                        Message::ROLE_SYSTEM => 'System',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('sent_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket.subject')->label('Ticket')->searchable(),
                Tables\Columns\BadgeColumn::make('visibility')->colors([
                    'success' => Message::VISIBILITY_PUBLIC,
                    'warning' => Message::VISIBILITY_INTERNAL,
                ])->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('author_role')->label('Author Role')->badge(),
                Tables\Columns\TextColumn::make('author.name')->label('Author')->searchable(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->options([
                        Message::VISIBILITY_PUBLIC => 'Public',
                        Message::VISIBILITY_INTERNAL => 'Internal',
                    ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sent_at', 'desc');
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
        $query = parent::getEloquentQuery()->with(['ticket', 'author']);

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
