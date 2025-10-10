<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionCoverageReportResource\Pages;
use App\Models\Brand;
use App\Models\PermissionCoverageReport;
use App\Models\User;
use App\Services\PermissionCoverageReportService;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class PermissionCoverageReportResource extends Resource
{
    protected static ?string $model = PermissionCoverageReport::class;

    protected static ?string $navigationGroup = 'Security & Compliance';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Select::make('module')
                            ->label('Module')
                            ->options(static::moduleOptions())
                            ->required()
                            ->disabled(fn (?PermissionCoverageReport $record) => $record !== null),
                        Select::make('brand_id')
                            ->label('Brand Scope')
                            ->options(fn () => static::brandOptions())
                            ->searchable()
                            ->preload()
                            ->helperText('Scope coverage report to a specific brand or leave empty for tenant-wide visibility.'),
                    ])->columns(2),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Operator Notes (NON-PRODUCTION)')
                            ->rows(4)
                            ->maxLength(1024),
                    ]),
                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add entry')
                            ->helperText('Stored as structured NON-PRODUCTION metadata for CI pipelines.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('module')
                    ->label('Module')
                    ->sortable()
                    ->badge(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->toggleable(),
                BadgeColumn::make('coverage')
                    ->label('Coverage %')
                    ->colors([
                        'danger' => fn ($state) => $state < 75,
                        'warning' => fn ($state) => $state >= 75 && $state < 90,
                        'success' => fn ($state) => $state >= 90,
                    ])
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2).'%'),
                TextColumn::make('unguarded_routes')
                    ->label('Unguarded')
                    ->sortable(),
                IconColumn::make('unguarded_routes')
                    ->label('Passes Gate')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->getStateUsing(fn (PermissionCoverageReport $record) => $record->unguarded_routes === 0),
                TextColumn::make('generated_at')
                    ->dateTime()
                    ->label('Generated At')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->label('Module')
                    ->options(static::moduleOptions()),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->action(function (PermissionCoverageReport $record): void {
                            $service = app(PermissionCoverageReportService::class);
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
                        $service = app(PermissionCoverageReportService::class);
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            abort(403, 'This action is unauthorized.');
                        }

                        foreach ($records as $record) {
                            if ($record instanceof PermissionCoverageReport) {
                                $service->delete($record, $user);
                            }
                        }
                    }),
            ])
            ->defaultSort('generated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissionCoverageReports::route('/'),
            'create' => Pages\CreatePermissionCoverageReport::route('/create'),
            'edit' => Pages\EditPermissionCoverageReport::route('/{record}/edit'),
            'view' => Pages\ViewPermissionCoverageReport::route('/{record}'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function moduleOptions(): array
    {
        return collect(PermissionCoverageReport::MODULES)
            ->mapWithKeys(fn (string $module) => [$module => ucfirst(str_replace('_', ' ', $module))])
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
