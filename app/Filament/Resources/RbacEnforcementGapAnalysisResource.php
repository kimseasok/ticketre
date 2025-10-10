<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RbacEnforcementGapAnalysisResource\Pages;
use App\Models\Brand;
use App\Models\RbacEnforcementGapAnalysis;
use App\Models\User;
use App\Services\RbacEnforcementGapAnalysisService;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class RbacEnforcementGapAnalysisResource extends Resource
{
    protected static ?string $model = RbacEnforcementGapAnalysis::class;

    protected static ?string $navigationGroup = 'Security & Compliance';

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Analysis Overview')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(160),
                        Select::make('status')
                            ->label('Status')
                            ->options(static::statusOptions())
                            ->required(),
                        DateTimePicker::make('analysis_date')
                            ->label('Analysis Date')
                            ->required(),
                        Select::make('brand_id')
                            ->label('Brand Scope')
                            ->options(fn () => static::brandOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Limit the analysis to a specific brand or leave empty for tenant-wide context.'),
                        TextInput::make('owner_team')
                            ->label('Owning Team')
                            ->maxLength(120),
                        TextInput::make('reference_id')
                            ->label('Reference ID')
                            ->maxLength(64)
                            ->helperText('Optional correlation reference for downstream tooling.'),
                    ])->columns(2),
                Section::make('Audit Matrix')
                    ->schema([
                        Repeater::make('audit_matrix')
                            ->label('Critical RBAC Assets')
                            ->minItems(1)
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'route' => 'Route',
                                        'command' => 'Command',
                                        'queue' => 'Queue',
                                    ])
                                    ->required(),
                                TextInput::make('identifier')
                                    ->label('Identifier')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('required_permissions')
                                    ->label('Required Permissions (comma separated)')
                                    ->helperText('Values split on commas are stored individually.')
                                    ->required()
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn (?string $state) => collect(explode(',', (string) $state))
                                        ->map(fn ($value) => trim((string) $value))
                                        ->filter()
                                        ->values()
                                        ->all())
                                    ->afterStateHydrated(function (TextInput $component, $state): void {
                                        if (is_array($state)) {
                                            $component->state(collect($state)->join(', '));
                                        }
                                    }),
                                TextInput::make('roles')
                                    ->label('Roles (comma separated)')
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn (?string $state) => collect(explode(',', (string) $state))
                                        ->map(fn ($value) => trim((string) $value))
                                        ->filter()
                                        ->values()
                                        ->all())
                                    ->afterStateHydrated(function (TextInput $component, $state): void {
                                        if (is_array($state)) {
                                            $component->state(collect($state)->join(', '));
                                        }
                                    }),
                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->maxLength(255),
                            ])->columns(2),
                    ]),
                Section::make('Findings')
                    ->schema([
                        Repeater::make('findings')
                            ->label('Findings & Remediations')
                            ->minItems(1)
                            ->schema([
                                Select::make('priority')
                                    ->label('Priority')
                                    ->options([
                                        'high' => 'High',
                                        'medium' => 'Medium',
                                        'low' => 'Low',
                                    ])
                                    ->required(),
                                TextInput::make('summary')
                                    ->label('Summary')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('owner')
                                    ->label('Owner')
                                    ->maxLength(120),
                                TextInput::make('eta_days')
                                    ->label('ETA (days)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(365),
                                TextInput::make('status')
                                    ->label('Status')
                                    ->maxLength(64),
                            ])->columns(2),
                    ]),
                Section::make('Remediation Plan & Notes')
                    ->schema([
                        KeyValue::make('remediation_plan')
                            ->label('Remediation Plan Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add entry')
                            ->helperText('Structured NON-PRODUCTION metadata describing remediation milestones.'),
                        Textarea::make('review_minutes')
                            ->label('Review Meeting Minutes (NON-PRODUCTION)')
                            ->rows(6)
                            ->required()
                            ->maxLength(4000),
                        Textarea::make('notes')
                            ->label('Additional Notes (NON-PRODUCTION)')
                            ->rows(4)
                            ->maxLength(2000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(40),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger' => 'draft',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title()),
                TextColumn::make('analysis_date')
                    ->label('Analysis Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('owner_team')
                    ->label('Owner Team')
                    ->toggleable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->toggleable(),
                IconColumn::make('findings')
                    ->label('Remediation Defined')
                    ->boolean()
                    ->getStateUsing(fn (RbacEnforcementGapAnalysis $record) => count($record->findings ?? []) > 0),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::statusOptions()),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->action(function (RbacEnforcementGapAnalysis $record): void {
                            $service = app(RbacEnforcementGapAnalysisService::class);
                            $user = auth()->user();

                            if (! $user instanceof User) {
                                abort(403, 'This action is unauthorized.');
                            }

                            $service->delete($record, $user);
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->action(function (Collection $records): void {
                        $service = app(RbacEnforcementGapAnalysisService::class);
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            abort(403, 'This action is unauthorized.');
                        }

                        foreach ($records as $record) {
                            if ($record instanceof RbacEnforcementGapAnalysis) {
                                $service->delete($record, $user);
                            }
                        }
                    }),
            ])
            ->defaultSort('analysis_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRbacEnforcementGapAnalyses::route('/'),
            'create' => Pages\CreateRbacEnforcementGapAnalysis::route('/create'),
            'edit' => Pages\EditRbacEnforcementGapAnalysis::route('/{record}/edit'),
            'view' => Pages\ViewRbacEnforcementGapAnalysis::route('/{record}'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function statusOptions(): array
    {
        return collect(RbacEnforcementGapAnalysis::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => str($status)->replace('_', ' ')->title()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function brandOptions(): array
    {
        $tenantId = app()->bound('currentTenant') && app('currentTenant') ? app('currentTenant')->getKey() : null;

        if (! $tenantId) {
            return [];
        }

        return Brand::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
