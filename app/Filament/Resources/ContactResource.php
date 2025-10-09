<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Brand;
use App\Models\Contact;
use App\Services\ContactService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 0;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Select::make('company_id')
                    ->relationship('company', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->label('Company')
                    ->helperText('Link the contact to an existing company within the tenant and brand.'),
                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions())
                    ->searchable()
                    ->preload()
                    ->helperText('Optional brand scope; defaults to the active brand context.'),
                Forms\Components\TagsInput::make('tags')
                    ->label('Tags')
                    ->suggestions(['vip', 'gdpr', 'billing', 'product', 'beta'])
                    ->placeholder('Add up to 10 lowercase tags')
                    ->helperText('Tags are lowercased automatically and stored per tenant.'),
                Forms\Components\Toggle::make('gdpr_marketing_opt_in')
                    ->label('Marketing consent (GDPR)')
                    ->default(true)
                    ->inline(false)
                    ->required(),
                Forms\Components\Toggle::make('gdpr_data_processing_opt_in')
                    ->label('Data processing consent (GDPR)')
                    ->default(true)
                    ->inline(false)
                    ->required(),
                Forms\Components\KeyValue::make('metadata')
                    ->label('Metadata')
                    ->helperText('NON-PRODUCTION: free-form key/value pairs for demos.')
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('company.name')->label('Company')->toggleable(),
                Tables\Columns\TextColumn::make('brand.name')->label('Brand')->toggleable(),
                Tables\Columns\BadgeColumn::make('tags')->separator(', ')->label('Tags')->toggleable(),
                Tables\Columns\IconColumn::make('gdpr_marketing_opt_in')->boolean()->label('Marketing GDPR'),
                Tables\Columns\IconColumn::make('gdpr_data_processing_opt_in')->boolean()->label('Processing GDPR'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => static::brandOptions()),
                Tables\Filters\SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->label('Company'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->action(fn (Contact $record) => static::deleteContact($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records->each(fn (Contact $contact) => static::deleteContact($contact))),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['company', 'brand']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['company', 'brand']);
    }

    protected static function deleteContact(Contact $contact): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var ContactService $service */
        $service = App::make(ContactService::class);
        $service->delete($contact, $user, request()?->header('X-Correlation-ID'));
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
