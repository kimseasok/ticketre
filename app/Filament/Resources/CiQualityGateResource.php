<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CiQualityGateResource\Pages;
use App\Models\Brand;
use App\Models\CiQualityGate;
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
use Filament\Tables\Table;

class CiQualityGateResource extends Resource
{
    protected static ?string $model = CiQualityGate::class;

    protected static ?string $navigationGroup = 'Platform';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->maxLength(255)
                    ->helperText('Used by API clients to reference this gate. Leave blank to auto-generate.'),
                Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions())
                    ->searchable()
                    ->preload()
                    ->helperText('Optional brand scope; omit for tenant-wide enforcement.'),
                TextInput::make('coverage_threshold')
                    ->label('Coverage Threshold (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step('0.01')
                    ->default(85)
                    ->required(),
                TextInput::make('max_critical_vulnerabilities')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(0)
                    ->label('Max Critical Vulnerabilities'),
                TextInput::make('max_high_vulnerabilities')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(1000)
                    ->default(0)
                    ->label('Max High Vulnerabilities'),
                Toggle::make('enforce_dependency_audit')
                    ->label('Enforce Dependency Audits')
                    ->default(true),
                Toggle::make('enforce_docker_build')
                    ->label('Require Docker Build')
                    ->default(true),
                Toggle::make('notifications_enabled')
                    ->label('Notifications Enabled')
                    ->default(true),
                TextInput::make('notify_channel')
                    ->label('Notify Channel (redacted)')
                    ->maxLength(255)
                    ->helperText('Channel hint (stored hashed in logs). NON-PRODUCTION sample data recommended.'),
                Textarea::make('metadata.description')
                    ->label('Operator Notes (NON-PRODUCTION)')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('slug')->toggleable(),
                TextColumn::make('brand.name')->label('Brand')->toggleable(),
                TextColumn::make('coverage_threshold')->label('Coverage %')->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                TextColumn::make('max_critical_vulnerabilities')->label('Critical')->sortable(),
                TextColumn::make('max_high_vulnerabilities')->label('High')->sortable(),
                IconColumn::make('enforce_dependency_audit')->boolean()->label('Audit'),
                IconColumn::make('enforce_docker_build')->boolean()->label('Docker'),
                IconColumn::make('notifications_enabled')->boolean()->label('Notify'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')->label('Brand')->options(fn () => static::brandOptions()),
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
            'index' => Pages\ListCiQualityGates::route('/'),
            'create' => Pages\CreateCiQualityGate::route('/create'),
            'view' => Pages\ViewCiQualityGate::route('/{record}'),
            'edit' => Pages\EditCiQualityGate::route('/{record}/edit'),
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
