<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlaPolicyResource\Pages;
use App\Models\Brand;
use App\Models\SlaPolicy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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

class SlaPolicyResource extends Resource
{
    protected static ?string $model = SlaPolicy::class;

    protected static ?string $navigationGroup = 'Automation & SLA';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Policy Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->helperText('Identifier used by API clients. Leave blank to auto-generate.')
                            ->maxLength(255),
                        Select::make('brand_id')
                            ->label('Brand Scope')
                            ->options(fn () => static::brandOptions())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Optional brand-specific SLA. Leave empty for tenant default.'),
                        Select::make('timezone')
                            ->required()
                            ->searchable()
                            ->options(fn () => static::timezoneOptions())
                            ->default('UTC'),
                        Toggle::make('enforce_business_hours')
                            ->label('Enforce business hours')
                            ->default(true)
                            ->helperText('When disabled, deadlines run 24/7 and ignore holiday calendars.'),
                        TextInput::make('default_first_response_minutes')
                            ->label('Default first response (minutes)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(43200)
                            ->helperText('Used when no channel/priority override is defined.'),
                        TextInput::make('default_resolution_minutes')
                            ->label('Default resolution (minutes)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(43200),
                    ])->columns(2),
                Section::make('Business Hours')
                    ->collapsible()
                    ->schema([
                        Repeater::make('business_hours')
                            ->label('Operating windows (local timezone)')
                            ->schema([
                                Select::make('day')
                                    ->options(static::dayOptions())
                                    ->required()
                                    ->searchable(),
                                TimePicker::make('start')
                                    ->withoutSeconds()
                                    ->required(),
                                TimePicker::make('end')
                                    ->withoutSeconds()
                                    ->required(),
                            ])
                            ->columns(3)
                            ->default([])
                            ->helperText('Define when response timers run. Entries with end time before start will be rejected.'),
                    ]),
                Section::make('Holiday Calendar')
                    ->collapsible()
                    ->schema([
                        Repeater::make('holiday_exceptions')
                            ->label('Holiday Dates (NON-PRODUCTION)')
                            ->schema([
                                DatePicker::make('date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('Y-m-d'),
                                TextInput::make('name')
                                    ->label('Label')
                                    ->maxLength(120),
                            ])
                            ->columns(2)
                            ->default([])
                            ->helperText('Dates are stored as YYYY-MM-DD and evaluated in the policy timezone.'),
                    ]),
                Section::make('Channel & Priority Targets')
                    ->schema([
                        Repeater::make('targets')
                            ->schema([
                                Select::make('channel')
                                    ->options(static::channelOptions())
                                    ->required()
                                    ->searchable(),
                                TextInput::make('priority')
                                    ->required()
                                    ->maxLength(50)
                                    ->helperText('Priority identifier (e.g. urgent, high).'),
                                TextInput::make('first_response_minutes')
                                    ->label('First response (minutes)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(43200),
                                TextInput::make('resolution_minutes')
                                    ->label('Resolution (minutes)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(43200),
                                Toggle::make('use_business_hours')
                                    ->label('Use business hours')
                                    ->default(true),
                            ])
                            ->columns(5)
                            ->default([])
                            ->helperText('Overrides apply when both channel and priority match. Minutes inherit defaults when empty.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('brand.name')->label('Brand')->toggleable(),
                TextColumn::make('timezone')->sortable()->toggleable(),
                TextColumn::make('default_first_response_minutes')->label('First Resp (m)')->sortable()->toggleable(),
                TextColumn::make('default_resolution_minutes')->label('Resolution (m)')->sortable()->toggleable(),
                IconColumn::make('enforce_business_hours')->boolean()->label('Business Hours'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
                TernaryFilter::make('enforce_business_hours')
                    ->label('Business Hours Only'),
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
            'index' => Pages\ListSlaPolicies::route('/'),
            'create' => Pages\CreateSlaPolicy::route('/create'),
            'edit' => Pages\EditSlaPolicy::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function brandOptions(): array
    {
        if (! app()->bound('currentTenant') || ! app('currentTenant')) {
            return [];
        }

        return Brand::query()
            ->where('tenant_id', app('currentTenant')->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * @return array<string, string>
     */
    protected static function timezoneOptions(): array
    {
        $identifiers = \DateTimeZone::listIdentifiers();

        return array_combine($identifiers, $identifiers) ?: ['UTC' => 'UTC'];
    }

    /**
     * @return array<string, string>
     */
    protected static function channelOptions(): array
    {
        return [
            'agent' => 'Agent',
            'portal' => 'Portal',
            'email' => 'Email',
            'chat' => 'Chat',
            'api' => 'API',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function dayOptions(): array
    {
        return [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
    }
}
