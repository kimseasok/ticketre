<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RedisConfigurationResource\Pages;
use App\Models\Brand;
use App\Models\RedisConfiguration;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedisConfigurationResource extends Resource
{
    protected static ?string $model = RedisConfiguration::class;

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Cluster Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->helperText('Identifier used by API clients. Leave blank to auto-generate.'),
                        Select::make('brand_id')
                            ->label('Brand Scope')
                            ->options(fn () => static::brandOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Optional brand scope. Leave empty for tenant-wide defaults.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive configurations remain in the catalog but will not be applied.'),
                    ])->columns(2),
                Forms\Components\Section::make('Cache Connection')
                    ->schema([
                        TextInput::make('cache_connection_name')
                            ->label('Connection Name')
                            ->default('cache')
                            ->maxLength(64)
                            ->helperText('Laravel Redis connection key to override.'),
                        TextInput::make('cache_host')
                            ->label('Host')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('cache_port')
                            ->label('Port')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(65535),
                        TextInput::make('cache_database')
                            ->label('Database Index')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(16),
                        Toggle::make('cache_tls')
                            ->label('TLS Enabled'),
                        TextInput::make('cache_prefix')
                            ->label('Cache Prefix')
                            ->maxLength(255)
                            ->helperText('Optional prefix applied to cache keys. Defaults to the tenant slug.'),
                        TextInput::make('cache_auth_secret')
                            ->password()
                            ->label('Cache Password (optional)')
                            ->revealable()
                            ->helperText('Secrets are encrypted at rest. Leave blank to keep the current secret.'),
                    ])->columns(2),
                Forms\Components\Section::make('Session Connection')
                    ->schema([
                        TextInput::make('session_connection_name')
                            ->label('Connection Name')
                            ->default('default')
                            ->maxLength(64)
                            ->helperText('Redis connection key for session storage. Defaults to the cache connection.'),
                        TextInput::make('session_host')
                            ->label('Host')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('session_port')
                            ->label('Port')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(65535),
                        TextInput::make('session_database')
                            ->label('Database Index')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(16),
                        Toggle::make('session_tls')
                            ->label('TLS Enabled'),
                        TextInput::make('session_lifetime_minutes')
                            ->label('Session Lifetime (minutes)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(1440),
                        TextInput::make('session_auth_secret')
                            ->password()
                            ->label('Session Password (optional)')
                            ->revealable()
                            ->helperText('Secrets are encrypted at rest. Leave blank to keep the current secret.'),
                    ])->columns(2),
                Forms\Components\Section::make('Failover & Metadata')
                    ->schema([
                        Toggle::make('use_for_cache')
                            ->label('Use for Cache')
                            ->default(true),
                        Toggle::make('use_for_sessions')
                            ->label('Use for Sessions')
                            ->default(true),
                        Select::make('fallback_store')
                            ->label('Fallback Store')
                            ->options([
                                'file' => 'File',
                                'array' => 'In-memory (array)',
                            ])
                            ->default('file')
                            ->helperText('Fallback store engaged when Redis is unreachable. File retains sessions across requests.'),
                        KeyValue::make('options')
                            ->label('Driver Options (optional)')
                            ->keyLabel('Option')
                            ->valueLabel('Value')
                            ->helperText('Custom Redis client options serialized as JSON. Avoid secrets here.'),
                        Textarea::make('notes')
                            ->label('Operator Notes (NON-PRODUCTION)')
                            ->rows(3)
                            ->disabled()
                            ->helperText('Document operational runbooks in README.md. Notes are maintained outside of the database.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('brand.name')->label('Brand')->toggleable(),
                TextColumn::make('cache_connection_name')->label('Cache Conn')->toggleable(),
                TextColumn::make('cache_port')->label('Cache Port')->sortable(),
                TextColumn::make('session_port')->label('Session Port')->sortable(),
                IconColumn::make('cache_tls')->boolean()->label('Cache TLS'),
                IconColumn::make('session_tls')->boolean()->label('Session TLS'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
                TernaryFilter::make('is_active')
                    ->label('Active Only')
                    ->true(fn ($query) => $query->where('is_active', true))
                    ->false(fn ($query) => $query->where('is_active', false)),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRedisConfigurations::route('/'),
            'create' => Pages\CreateRedisConfiguration::route('/create'),
            'view' => Pages\ViewRedisConfiguration::route('/{record}'),
            'edit' => Pages\EditRedisConfiguration::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function brandOptions(): array
    {
        return Brand::query()
            ->when(app()->bound('currentTenant') && app('currentTenant'), fn ($query) => $query->where('tenant_id', app('currentTenant')->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
