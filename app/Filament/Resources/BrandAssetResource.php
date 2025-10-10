<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandAssetResource\Pages;
use App\Models\Brand;
use App\Models\BrandAsset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BrandAssetResource extends Resource
{
    protected static ?string $model = BrandAsset::class;

    protected static ?string $navigationGroup = 'Branding';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Asset Metadata')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('brand_id')
                                    ->label('Brand')
                                    ->options(fn () => static::brandOptions())
                                    ->searchable()
                                    ->required(),
                                Select::make('type')
                                    ->label('Asset Type')
                                    ->options(static::assetTypeOptions())
                                    ->required(),
                                TextInput::make('disk')
                                    ->label('Storage Disk')
                                    ->default(config('branding.asset_disk'))
                                    ->maxLength(64),
                            ]),
                        TextInput::make('path')
                            ->label('Storage Path')
                            ->required()
                            ->maxLength(2048)
                            ->helperText('Relative path on the configured disk. PII is redacted in logs.'),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('content_type')
                                    ->label('Content Type')
                                    ->maxLength(128),
                                TextInput::make('size')
                                    ->label('Size (bytes)')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('checksum')
                                    ->label('Checksum (optional)')
                                    ->maxLength(128),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('cache_control')
                                    ->label('Cache-Control Header')
                                    ->default(config('branding.assets.cache_control'))
                                    ->maxLength(128),
                                TextInput::make('cdn_url')
                                    ->label('CDN URL (optional)')
                                    ->url()
                                    ->maxLength(2048),
                            ]),
                        KeyValue::make('meta')
                            ->label('Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('Arbitrary JSON metadata stored alongside the asset.'),
                        Textarea::make('notes')
                            ->label('Operator Notes')
                            ->rows(2)
                            ->default('NON-PRODUCTION reference. Document runtime delivery in README.md.')
                            ->disabled(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand.name')->label('Brand')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('version')->label('Version')->sortable(),
                TextColumn::make('disk')->toggleable(),
                BadgeColumn::make('content_type')->label('Content Type')->toggleable(),
                TextColumn::make('cache_control')->label('Cache-Control')->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(static::assetTypeOptions()),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrandAssets::route('/'),
            'create' => Pages\CreateBrandAsset::route('/create'),
            'view' => Pages\ViewBrandAsset::route('/{record}'),
            'edit' => Pages\EditBrandAsset::route('/{record}/edit'),
        ];
    }

    protected static function brandOptions(): array
    {
        return Brand::query()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function assetTypeOptions(): array
    {
        $types = config('branding.asset_types', []);

        return collect($types)
            ->mapWithKeys(fn (string $type) => [$type => Str::of($type)->replace('_', ' ')->title()->toString()])
            ->toArray();
    }
}
