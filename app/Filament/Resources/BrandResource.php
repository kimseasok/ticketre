<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Filament\Resources\BrandResource\RelationManagers\DomainsRelationManager;
use App\Models\Brand;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationGroup = 'Branding';

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Brand Identity')
                    ->schema([
                        TextInput::make('name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(255)
                            ->helperText('Slug is used by the API and domain automation. Leave blank to auto-generate.'),
                        TextInput::make('domain')
                            ->label('Primary Domain')
                            ->maxLength(255)
                            ->helperText('Custom domains are managed below. This field controls default portal routing.'),
                    ])->columns(3),
                Section::make('Brand Assets')
                    ->schema([
                        FileUpload::make('primary_logo_path')
                            ->label('Primary Logo')
                            ->image()
                            ->directory('brands/logos')
                            ->disk(config('branding.asset_disk'))
                            ->helperText('Upload PNG/SVG assets. Values are stored as relative paths and redacted in logs.'),
                        FileUpload::make('secondary_logo_path')
                            ->label('Secondary Logo')
                            ->image()
                            ->directory('brands/logos')
                            ->disk(config('branding.asset_disk')),
                        FileUpload::make('favicon_path')
                            ->label('Favicon')
                            ->directory('brands/favicons')
                            ->disk(config('branding.asset_disk'))
                            ->helperText('ICO/PNG supported. Served with tenant CDN caching.'),
                    ])->columns(3),
                Section::make('Theme Settings')
                    ->schema([
                        ColorPicker::make('theme.primary')->label('Primary Color')->default('#2563eb')->live(),
                        ColorPicker::make('theme.secondary')->label('Secondary Color')->default('#0f172a')->live(),
                        ColorPicker::make('theme.accent')->label('Accent Color')->default('#38bdf8')->live(),
                        ColorPicker::make('theme.text')->label('Text Color')->default('#0f172a')->live(),
                        TextInput::make('theme_settings.font_family')
                            ->label('Font Family')
                            ->default('Inter')
                            ->maxLength(64),
                        TextInput::make('theme_settings.button_radius')
                            ->numeric()
                            ->label('Button Radius')
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(6),
                        Placeholder::make('theme_preview_placeholder')
                            ->label('Preview')
                            ->content(function (Get $get): HtmlString {
                                $primary = $get('theme.primary') ?? '#2563eb';
                                $secondary = $get('theme.secondary') ?? '#0f172a';
                                $text = $get('theme.text') ?? '#0f172a';
                                $accent = $get('theme.accent') ?? '#38bdf8';

                                $style = sprintf('background: linear-gradient(90deg, %s 0%%, %s 100%%); color: %s;', $primary, $secondary, $text);

                                $html = <<<HTML
                                    <div class="rounded-lg border border-gray-200 shadow-sm" style="{$style}">
                                        <div class="p-4">
                                            <p class="font-semibold">Live Preview</p>
                                            <p class="text-sm opacity-90">Buttons use accent {$accent}; text renders with {$text}.</p>
                                            <button class="mt-3 inline-flex items-center rounded-md px-3 py-2 text-sm font-medium" style="background: {$accent}; color: {$text};">
                                                Preview Button
                                            </button>
                                        </div>
                                    </div>
                                HTML;

                                return new HtmlString($html);
                            })
                            ->columnSpanFull()
                            ->reactive(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->sortable(),
                TextColumn::make('domain')->label('Primary Domain')->toggleable(),
                BadgeColumn::make('domains_count')
                    ->counts('domains')
                    ->label('Custom Domains')
                    ->colors([
                        'primary',
                        'success' => fn (int $count) => $count > 0,
                    ]),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                // Additional filters can be added as needed.
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

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'view' => Pages\ViewBrand::route('/{record}'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
