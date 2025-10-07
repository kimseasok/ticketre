<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\CompanyResource\Pages;
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
use Illuminate\Validation\Rule;

class CompanyResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Company Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                fn (?Company $record) => Rule::unique('companies', 'name')
                                    ->where('tenant_id', app('currentTenant')?->getKey())
                                    ->whereNull('deleted_at')
                                    ->ignore($record?->getKey()),
                            ]),
                        Forms\Components\TextInput::make('domain')
                            ->label('Domain')
                            ->maxLength(255)
                            ->rules([
                                fn (?Company $record) => Rule::unique('companies', 'domain')
                                    ->where('tenant_id', app('currentTenant')?->getKey())
                                    ->whereNull('deleted_at')
                                    ->ignore($record?->getKey()),
                            ]),
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label('Domain')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->label('Updated'),
            ])
            ->filters([
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
            ]);
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
        $query = parent::getEloquentQuery();

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        return $query;
    }

    protected static function deleteCompany(Company $company): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var CompanyService $service */
        $service = App::make(CompanyService::class);
        $service->delete($company, $user);
    }

    public static function canViewAny(): bool
    {
        return static::userCan('contacts.manage') || static::userCan('contacts.view') || static::userCan('companies.view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('contacts.manage') || static::userCan('companies.manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCan('contacts.manage') || static::userCan('companies.manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCan('contacts.manage') || static::userCan('companies.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
