<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KbArticleResource\Pages;
use App\Models\KbArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class KbArticleResource extends Resource
{
    protected static ?string $model = KbArticle::class;

    protected static ?string $navigationGroup = 'Knowledge Base';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->default(fn () => app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : null)
                    ->required(),
                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name', function (Builder $query) {
                        if (app()->bound('currentBrand') && app('currentBrand')) {
                            $query->where('brand_id', app('currentBrand')->getKey());
                        }

                        return $query->orderBy('name');
                    })
                    ->searchable()
                    ->required(),
                Forms\Components\Select::make('author_id')
                    ->relationship('author', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->required(),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (Get $get, ?KbArticle $record) => Rule::unique('kb_articles', 'slug')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->where('brand_id', $get('brand_id'))
                        ->ignore($record?->getKey())),
                Forms\Components\Select::make('default_locale')
                    ->label('Default Locale')
                    ->options(fn (Get $get) => collect($get('translations'))
                        ->pluck('locale', 'locale')
                        ->filter()
                        ->toArray())
                    ->required()
                    ->helperText('Must match one of the translation locales.')
                    ->default('en')
                    ->reactive(),
                Forms\Components\Repeater::make('translations')
                    ->relationship('translations')
                    ->label('Translations')
                    ->columns(2)
                    ->collapsible()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->itemLabel(fn (array $state): ?string => $state['locale'] ?? null)
                    ->schema([
                        Forms\Components\TextInput::make('locale')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\RichEditor::make('content')
                            ->required()
                            ->columnSpan(2),
                        Forms\Components\Textarea::make('excerpt')
                            ->rows(3)
                            ->columnSpan(2),
                        Forms\Components\KeyValue::make('metadata')
                            ->columnSpan(2)
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->nullable()
                            ->columnSpan(2),
                    ])
                    ->columnSpanFull()
                    ->mutateRelationshipDataBeforeCreate(function (array $data, ?KbArticle $record) {
                        return array_merge($data, [
                            'tenant_id' => $record?->tenant_id ?? auth()->user()?->tenant_id,
                            'brand_id' => $record?->brand_id ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : auth()->user()?->brand_id),
                        ]);
                    })
                    ->mutateRelationshipDataBeforeSave(function (array $data, ?KbArticle $record) {
                        return array_merge($data, [
                            'tenant_id' => $record?->tenant_id ?? $data['tenant_id'] ?? auth()->user()?->tenant_id,
                            'brand_id' => $record?->brand_id ?? $data['brand_id'] ?? (app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand')->getKey() : auth()->user()?->brand_id),
                        ]);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('defaultTranslation.title')
                    ->label('Title')
                    ->limit(50)
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('translations', function (Builder $builder) use ($search) {
                            $builder->where('title', 'like', '%'.$search.'%');
                        });
                    }),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('category.name')->label('Category')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('defaultTranslation.status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('default_locale')->label('Default Locale'),
                Tables\Columns\TextColumn::make('defaultTranslation.published_at')->label('Published At')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ])->query(function (Builder $query, array $data) {
                    if (! $data['value']) {
                        return;
                    }

                    $query->whereHas('translations', function (Builder $builder) use ($data) {
                        $builder->where('status', $data['value'])
                            ->whereColumn('locale', 'kb_articles.default_locale');
                    });
                }),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKbArticles::route('/'),
            'create' => Pages\CreateKbArticle::route('/create'),
            'edit' => Pages\EditKbArticle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['category', 'brand', 'author', 'translations']);

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        if (app()->bound('currentBrand') && app('currentBrand')) {
            $query->where('brand_id', app('currentBrand')->getKey());
        }

        return $query;
    }
}
