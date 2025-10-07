<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BroadcastConnectionResource\Pages;
use App\Models\BroadcastConnection;
use App\Models\User;
use App\Services\BroadcastConnectionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class BroadcastConnectionResource extends Resource
{
    protected static ?string $model = BroadcastConnection::class;

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('brand_id')
                ->label('Brand')
                ->relationship('brand', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('connection_id')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('channel_name')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('status')
                ->required()
                ->options([
                    BroadcastConnection::STATUS_ACTIVE => 'Active',
                    BroadcastConnection::STATUS_STALE => 'Stale',
                    BroadcastConnection::STATUS_DISCONNECTED => 'Disconnected',
                ]),
            Forms\Components\TextInput::make('latency_ms')
                ->numeric()
                ->minValue(0)
                ->label('Latency (ms)'),
            Forms\Components\DateTimePicker::make('last_seen_at')
                ->label('Last Seen')
                ->seconds(false)
                ->nullable(),
            Forms\Components\KeyValue::make('metadata')
                ->label('Metadata')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('connection_id')->label('Connection')->searchable(),
                Tables\Columns\TextColumn::make('channel_name')->label('Channel')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('latency_ms')->label('Latency (ms)'),
                Tables\Columns\TextColumn::make('last_seen_at')->label('Last Seen')->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label('Updated')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    BroadcastConnection::STATUS_ACTIVE => 'Active',
                    BroadcastConnection::STATUS_STALE => 'Stale',
                    BroadcastConnection::STATUS_DISCONNECTED => 'Disconnected',
                ]),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->action(fn (BroadcastConnection $record) => static::deleteConnection($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records->each(fn (BroadcastConnection $connection) => static::deleteConnection($connection))),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBroadcastConnections::route('/'),
            'create' => Pages\CreateBroadcastConnection::route('/create'),
            'edit' => Pages\EditBroadcastConnection::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user', 'brand']);

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        return $query;
    }

    protected static function deleteConnection(BroadcastConnection $connection): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401, 'Authentication required.');
        }

        /** @var BroadcastConnectionService $service */
        $service = App::make(BroadcastConnectionService::class);
        $service->delete($connection, $user, (string) request()->headers->get('X-Correlation-ID', 'filament')); 
    }
}
