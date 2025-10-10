<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObservabilityStackResource\Pages;
use App\Models\Brand;
use App\Models\ObservabilityStack;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
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

class ObservabilityStackResource extends Resource
{
    protected static ?string $model = ObservabilityStack::class;

    protected static ?string $navigationGroup = 'Observability';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Stack Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('Slug')
                    ->maxLength(255)
                    ->helperText('Identifier for API clients. Leave blank to auto-generate.'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'evaluating' => 'Evaluating',
                        'selected' => 'Selected',
                        'deprecated' => 'Deprecated',
                    ])
                    ->required(),
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions())
                    ->searchable()
                    ->preload()
                    ->helperText('Optional brand scope. Leave empty for tenant-wide selections.'),
                Select::make('logs_tool')
                    ->label('Logs Tool')
                    ->options([
                        'elk' => 'Elastic (ELK)',
                        'opensearch' => 'OpenSearch',
                        'loki-grafana' => 'Grafana Loki',
                    ])
                    ->required(),
                Select::make('metrics_tool')
                    ->label('Metrics Tool')
                    ->options([
                        'prometheus' => 'Prometheus',
                        'grafana-mimir' => 'Grafana Mimir',
                        'opensearch-metrics' => 'OpenSearch Metrics',
                    ])
                    ->required(),
                Select::make('alerts_tool')
                    ->label('Alerting Tool')
                    ->options([
                        'grafana-alerting' => 'Grafana Alerting',
                        'pagerduty' => 'PagerDuty',
                        'opsgenie' => 'Opsgenie',
                    ])
                    ->required(),
                TextInput::make('log_retention_days')
                    ->label('Log Retention (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->required(),
                TextInput::make('metric_retention_days')
                    ->label('Metric Retention (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->required(),
                TextInput::make('trace_retention_days')
                    ->label('Trace Retention (days)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(180),
                TextInput::make('estimated_monthly_cost')
                    ->label('Estimated Monthly Cost (USD)')
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                TextInput::make('trace_sampling_strategy')
                    ->label('Trace Sampling Strategy')
                    ->maxLength(255),
                Textarea::make('security_notes')
                    ->label('Security Notes')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('compliance_notes')
                    ->label('Compliance Notes')
                    ->rows(3)
                    ->columnSpanFull(),
                Repeater::make('decision_matrix')
                    ->label('Decision Matrix (NON-PRODUCTION analysis)')
                    ->schema([
                        TextInput::make('option')
                            ->label('Option')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('monthly_cost')
                            ->label('Monthly Cost')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                        Textarea::make('scalability')
                            ->label('Scalability Summary')
                            ->rows(2)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                Textarea::make('metadata.description')
                    ->label('Operator Notes (NON-PRODUCTION)')
                    ->rows(3)
                    ->columnSpanFull(),
                KeyValue::make('metadata.tags')
                    ->label('Metadata Tags')
                    ->columnSpanFull()
                    ->keyLabel('Key')
                    ->valueLabel('Value'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Stack')->searchable()->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'evaluating',
                        'success' => 'selected',
                        'danger' => 'deprecated',
                    ])
                    ->sortable(),
                TextColumn::make('logs_tool')->label('Logs')->toggleable(),
                TextColumn::make('metrics_tool')->label('Metrics')->toggleable(),
                TextColumn::make('estimated_monthly_cost')
                    ->label('Monthly Cost')
                    ->money('usd')
                    ->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'evaluating' => 'Evaluating',
                        'selected' => 'Selected',
                        'deprecated' => 'Deprecated',
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
            'index' => Pages\ListObservabilityStacks::route('/'),
            'create' => Pages\CreateObservabilityStack::route('/create'),
            'view' => Pages\ViewObservabilityStack::route('/{record}'),
            'edit' => Pages\EditObservabilityStack::route('/{record}/edit'),
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
