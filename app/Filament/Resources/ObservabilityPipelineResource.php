<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObservabilityPipelineResource\Pages;
use App\Models\Brand;
use App\Models\ObservabilityPipeline;
use Filament\Forms;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ObservabilityPipelineResource extends Resource
{
    protected static ?string $model = ObservabilityPipeline::class;

    protected static ?string $navigationGroup = 'Observability';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(255)
                    ->helperText('Identifier used by API clients. Leave blank to auto-generate.'),
                Select::make('pipeline_type')
                    ->label('Pipeline Type')
                    ->options([
                        'logs' => 'Logs',
                        'metrics' => 'Metrics',
                        'traces' => 'Traces',
                    ])
                    ->required(),
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions())
                    ->searchable()
                    ->preload()
                    ->helperText('Optional brand scope. Leave empty for tenant-wide pipelines.'),
                TextInput::make('ingest_endpoint')
                    ->label('Ingest Endpoint')
                    ->required()
                    ->maxLength(2048)
                    ->helperText('Destination for log or metric payloads. Values are redacted in logs.'),
                TextInput::make('ingest_protocol')
                    ->label('Protocol')
                    ->maxLength(32)
                    ->helperText('Protocol is auto-detected from the endpoint when omitted.'),
                TextInput::make('buffer_strategy')
                    ->label('Buffer Strategy')
                    ->maxLength(64)
                    ->helperText('NON-PRODUCTION guidance for operators configuring queueing/backpressure.'),
                TextInput::make('buffer_retention_seconds')
                    ->numeric()
                    ->minValue(0)
                    ->label('Buffer Retention (seconds)')
                    ->required(),
                TextInput::make('retry_backoff_seconds')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(3600)
                    ->label('Retry Backoff (seconds)')
                    ->required(),
                TextInput::make('max_retry_attempts')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->label('Max Retry Attempts')
                    ->required(),
                TextInput::make('batch_max_bytes')
                    ->numeric()
                    ->minValue(1024)
                    ->label('Batch Size (bytes)')
                    ->helperText('Maximum payload size forwarded downstream.')
                    ->required(),
                TextInput::make('metrics_scrape_interval_seconds')
                    ->numeric()
                    ->minValue(5)
                    ->maxValue(3600)
                    ->label('Scrape Interval (seconds)')
                    ->visible(fn (Get $get) => $get('pipeline_type') === 'metrics'),
                Textarea::make('metadata.description')
                    ->label('Operator Notes (NON-PRODUCTION)')
                    ->rows(3)
                    ->columnSpanFull(),
                KeyValue::make('metadata.tags')
                    ->label('Metadata Tags (optional)')
                    ->columnSpanFull()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Metadata is stored as JSON for automation and reporting.'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                BadgeColumn::make('pipeline_type')
                    ->colors([
                        'primary',
                        'success' => 'metrics',
                        'warning' => 'traces',
                    ])
                    ->label('Type'),
                TextColumn::make('brand.name')->label('Brand')->toggleable(),
                TextColumn::make('buffer_strategy')->label('Buffer')->toggleable(),
                TextColumn::make('buffer_retention_seconds')->label('Buffer (s)')->sortable(),
                TextColumn::make('retry_backoff_seconds')->label('Backoff (s)')->sortable(),
                IconColumn::make('metrics_scrape_interval_seconds')
                    ->boolean()
                    ->label('Metrics'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('pipeline_type')
                    ->label('Pipeline Type')
                    ->options([
                        'logs' => 'Logs',
                        'metrics' => 'Metrics',
                        'traces' => 'Traces',
                    ]),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
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
            'index' => Pages\ListObservabilityPipelines::route('/'),
            'create' => Pages\CreateObservabilityPipeline::route('/create'),
            'view' => Pages\ViewObservabilityPipeline::route('/{record}'),
            'edit' => Pages\EditObservabilityPipeline::route('/{record}/edit'),
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
