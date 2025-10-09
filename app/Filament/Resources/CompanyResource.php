<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Models\Brand;
use App\Models\Company;
use App\Services\CompanyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('domain')
                    ->label('Domain')
                    ->maxLength(255)
                    ->helperText('Optional domain used for automated contact matching.'),
                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions())
                    ->searchable()
                    ->preload()
                    ->helperText('Optional brand scope; defaults to the active context.'),
                Forms\Components\TagsInput::make('tags')
                    ->label('Tags')
                    ->suggestions(['vip', 'enterprise', 'onboarding', 'churn-risk'])
                    ->placeholder('Add lowercase tags')
                    ->helperText('Tags support brand-level filtering and are lowercased automatically.'),
                Forms\Components\KeyValue::make('metadata')
                    ->label('Metadata')
                    ->helperText('NON-PRODUCTION demo attributes such as ARR band or region.')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('domain')->label('Domain')->toggleable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->toggleable(),
                Tables\Columns\BadgeColumn::make('tags')->separator(', ')->label('Tags')->toggleable(),
                Tables\Columns\TextColumn::make('contacts_count')->counts('contacts')->label('Contacts')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->action(fn (Company $record) => static::deleteCompany($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records->each(fn (Company $company) => static::deleteCompany($company))),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('contacts')->with('brand'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('contacts')->with('brand');
    }

    protected static function deleteCompany(Company $company): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var CompanyService $service */
        $service = App::make(CompanyService::class);
        $service->delete($company, $user, request()?->header('X-Correlation-ID'));
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
