<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketWorkflowResource\Pages;
use App\Models\Brand;
use App\Models\TicketWorkflow;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class TicketWorkflowResource extends Resource
{
    protected static ?string $model = TicketWorkflow::class;

    protected static ?string $navigationGroup = 'Ticketing';

    protected static ?string $navigationIcon = 'heroicon-o-flow';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Workflow Details')->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('brand_id')
                        ->label('Brand')
                        ->options(fn () => static::brandOptions())
                        ->searchable()
                        ->preload()
                        ->hint('Optional brand scope; leave blank for tenant default.'),
                    Toggle::make('is_default')
                        ->label('Default Workflow')
                        ->inline(false),
                    Textarea::make('description')
                        ->rows(3)
                        ->maxLength(500),
                ])->columns(2),
                Section::make('States')
                    ->schema([
                        Repeater::make('states')
                            ->label('Workflow States')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(255),
                                TextInput::make('slug')->required()->maxLength(255),
                                TextInput::make('position')->numeric()->default(0),
                                Toggle::make('is_initial')->inline(false)->default(false),
                                Toggle::make('is_terminal')->inline(false)->default(false),
                                TextInput::make('sla_minutes')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10080)
                                    ->label('SLA Minutes'),
                                TextInput::make('entry_hook')
                                    ->label('Entry Hook Class')
                                    ->maxLength(255),
                                Textarea::make('description')->rows(2)->maxLength(500),
                            ])
                            ->defaultItems(1)
                            ->itemLabel(fn (array $state) => $state['name'] ?? 'State')
                            ->columns(2)
                            ->reorderable(),
                    ])->collapsible()->collapsed(false),
                Section::make('Transitions')
                    ->schema([
                        Repeater::make('transitions')
                            ->schema([
                                Select::make('from')
                                    ->label('From State')
                                    ->options(fn (Get $get): array => static::stateOptions($get('states')))
                                    ->required(),
                                Select::make('to')
                                    ->label('To State')
                                    ->options(fn (Get $get): array => static::stateOptions($get('states')))
                                    ->required(),
                                TextInput::make('guard_hook')->label('Guard Hook')->maxLength(255),
                                Toggle::make('requires_comment')->inline(false)->default(false),
                                Textarea::make('metadata')->label('Metadata (JSON)')
                                    ->rows(2)
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        if (is_array($state)) {
                                            $component->state(json_encode($state, JSON_PRETTY_PRINT));
                                        }
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        if (empty($state)) {
                                            return [];
                                        }

                                        if (is_array($state)) {
                                            return $state;
                                        }

                                        $decoded = json_decode((string) $state, true);

                                        return is_array($decoded) ? $decoded : [];
                                    }),
                            ])
                            ->itemLabel(fn (array $transition) => ($transition['from'] ?? 'from').' → '.($transition['to'] ?? 'to'))
                            ->columns(2)
                            ->reorderable()
                            ->collapsed(),
                    ])->collapsible()->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->toggleable(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->toggleable(),
                Tables\Columns\TextColumn::make('states_count')
                    ->counts('states')
                    ->label('States')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
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
            'index' => Pages\ListTicketWorkflows::route('/'),
            'create' => Pages\CreateTicketWorkflow::route('/create'),
            'view' => Pages\ViewTicketWorkflow::route('/{record}'),
            'edit' => Pages\EditTicketWorkflow::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $states
     * @return array<string, string>
     */
    protected static function stateOptions(?array $states): array
    {
        if (! $states) {
            return [];
        }

        return Collection::make($states)
            ->pluck('name', 'slug')
            ->mapWithKeys(fn ($name, $slug) => [$slug => sprintf('%s (%s)', $name, $slug)])
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    protected static function brandOptions(): array
    {
        return Brand::query()
            ->when(app()->bound('currentTenant') && app('currentTenant'), function ($query) {
                $query->where('tenant_id', app('currentTenant')->getKey());
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
