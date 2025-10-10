<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorizonDeploymentResource\Pages;
use App\Models\Brand;
use App\Models\HorizonDeployment;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
use Illuminate\Support\Str;

class HorizonDeploymentResource extends Resource
{
    protected static ?string $model = HorizonDeployment::class;

    protected static ?string $navigationGroup = 'Infrastructure';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Deployment Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->helperText('Optional identifier used by API clients. Leave blank to auto-generate.'),
                        Select::make('brand_id')
                            ->label('Brand Scope')
                            ->options(fn () => static::brandOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Scope the deployment to a brand or leave empty for tenant-wide access.'),
                        TextInput::make('domain')
                            ->label('Dashboard Domain')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('auth_guard')
                            ->label('Authentication Guard')
                            ->default('web')
                            ->maxLength(64),
                        TextInput::make('horizon_connection')
                            ->label('Queue Connection')
                            ->default('redis')
                            ->maxLength(64),
                        Toggle::make('uses_tls')
                            ->label('TLS Termination Enabled')
                            ->default(true),
                    ])->columns(2),
                Section::make('Supervisors')
                    ->schema([
                        Repeater::make('supervisors')
                            ->label('Supervisor Definitions')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(64),
                                TextInput::make('connection')
                                    ->label('Connection')
                                    ->default('redis')
                                    ->maxLength(64),
                                TagsInput::make('queue')
                                    ->label('Queues')
                                    ->placeholder('default')
                                    ->required()
                                    ->helperText('Queues processed by this supervisor.'),
                                TextInput::make('balance')
                                    ->label('Balance Strategy')
                                    ->default('auto')
                                    ->maxLength(32),
                                TextInput::make('min_processes')
                                    ->label('Min Processes')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(1),
                                TextInput::make('max_processes')
                                    ->label('Max Processes')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(200)
                                    ->default(10),
                                TextInput::make('max_jobs')
                                    ->label('Max Jobs')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('max_time')
                                    ->label('Max Runtime (seconds)')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('timeout')
                                    ->label('Timeout (seconds)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(900)
                                    ->default(60),
                                TextInput::make('tries')
                                    ->label('Max Tries')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->default(1),
                            ])
                            ->minItems(1)
                            ->maxItems(10)
                            ->default([
                                [
                                    'name' => 'app-supervisor',
                                    'connection' => 'redis',
                                    'queue' => ['default'],
                                    'balance' => 'auto',
                                    'min_processes' => 1,
                                    'max_processes' => 5,
                                    'timeout' => 60,
                                    'tries' => 1,
                                ],
                            ]),
                    ]),
                Section::make('Operational Metadata')
                    ->schema([
                        DateTimePicker::make('last_deployed_at')
                            ->label('Last Deployed At'),
                        DateTimePicker::make('ssl_certificate_expires_at')
                            ->label('SSL Certificate Expires At'),
                        KeyValue::make('metadata')
                            ->label('Metadata (NON-PRODUCTION)')
                            ->helperText('Auxiliary notes captured as key/value pairs.')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->default(fn () => ['notes' => 'NON-PRODUCTION seed for demo environments.'])
                            ->addActionLabel('Add Entry')
                            ->reorderable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('brand.name')->label('Brand')->toggleable(),
                TextColumn::make('domain')->label('Domain')->toggleable(),
                TextColumn::make('last_health_status')->label('Health')->badge()->sortable(),
                IconColumn::make('uses_tls')->boolean()->label('TLS'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
                SelectFilter::make('last_health_status')
                    ->label('Health Status')
                    ->options([
                        'ok' => Str::ucfirst('ok'),
                        'degraded' => Str::ucfirst('degraded'),
                        'fail' => Str::ucfirst('fail'),
                        'unknown' => Str::ucfirst('unknown'),
                    ]),
                TernaryFilter::make('uses_tls')
                    ->label('TLS Enabled')
                    ->true(fn ($query) => $query->where('uses_tls', true))
                    ->false(fn ($query) => $query->where('uses_tls', false)),
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
            'index' => Pages\ListHorizonDeployments::route('/'),
            'create' => Pages\CreateHorizonDeployment::route('/create'),
            'view' => Pages\ViewHorizonDeployment::route('/{record}'),
            'edit' => Pages\EditHorizonDeployment::route('/{record}/edit'),
        ];
    }

    protected static function brandOptions(): array
    {
        return Brand::orderBy('name')->pluck('name', 'id')->toArray();
    }
}
