<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketDepartment;
use App\Models\TicketTag;
use App\Services\TicketService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
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
                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->searchable()
                    ->options(fn () => TicketDepartment::query()->pluck('name', 'id')->all())
                    ->nullable(),
                Forms\Components\Select::make('assignee_id')
                    ->label('Assignee')
                    ->searchable()
                    ->relationship('assignee', 'name')
                    ->nullable(),
                Forms\Components\Select::make('category_ids')
                    ->label('Categories')
                    ->multiple()
                    ->preload()
                    ->options(fn () => TicketCategory::query()->pluck('name', 'id')->all()),
                Forms\Components\Select::make('tag_ids')
                    ->label('Tags')
                    ->multiple()
                    ->preload()
                    ->options(fn () => TicketTag::query()->pluck('name', 'id')->all()),
                Forms\Components\KeyValue::make('metadata')
                    ->label('Metadata')
                    ->columnSpanFull()
                    ->addButtonLabel('Add metadata entry')
                    ->keyLabel('Key')
                    ->valueLabel('Value'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subject')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('departmentRelation.name')->label('Department'),
                Tables\Columns\TagsColumn::make('categories.name')->label('Categories'),
                Tables\Columns\TagsColumn::make('tags.name')->label('Tags'),
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
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(fn () => TicketDepartment::query()->pluck('name', 'id')->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(fn (Ticket $record) => static::deleteTicket($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records->each(fn (Ticket $ticket) => static::deleteTicket($ticket))),
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
        $query = parent::getEloquentQuery()->with(['assignee', 'departmentRelation', 'categories', 'tags']);

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        return $query;
    }

    protected static function deleteTicket(Ticket $ticket): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var TicketService $service */
        $service = App::make(TicketService::class);
        $service->delete($ticket, $user);
    }
}
