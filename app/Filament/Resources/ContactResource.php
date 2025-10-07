<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesAuthorization;
use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use App\Services\ContactService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ContactResource extends Resource
{
    use HandlesAuthorization;

    protected static ?string $model = Contact::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Contact Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->rules([
                                fn (?Contact $record) => Rule::unique('contacts', 'email')
                                    ->where('tenant_id', app('currentTenant')?->getKey())
                                    ->whereNull('deleted_at')
                                    ->ignore($record?->getKey()),
                            ]),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                            ->multiple()
                            ->preload()
                            ->saveRelationships(false)
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\ColorPicker::make('color')
                                    ->nullable(),
                            ])
                            ->helperText('Tags are scoped per tenant and redact sensitive metadata.'),
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('GDPR Consent')
                    ->schema([
                        Forms\Components\Toggle::make('gdpr_consent')
                            ->label('Consent Granted')
                            ->required()
                            ->live(),
                        Forms\Components\DateTimePicker::make('gdpr_consented_at')
                            ->label('Consent Timestamp')
                            ->seconds(false)
                            ->native(false),
                        Forms\Components\TextInput::make('gdpr_consent_method')
                            ->label('Consent Method')
                            ->required(fn (Forms\Get $get) => (bool) $get('gdpr_consent'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('gdpr_consent_source')
                            ->label('Consent Source')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('gdpr_notes')
                            ->label('Consent Notes')
                            ->rows(3)
                            ->helperText('Notes are hashed in logs and responses to protect PII.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('company.name')->label('Company'),
                Tables\Columns\IconColumn::make('gdpr_consent')
                    ->label('GDPR Consent')
                    ->boolean(),
                Tables\Columns\BadgeColumn::make('tags.name')
                    ->label('Tags')
                    ->colors(['primary']),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('company_id')
                    ->relationship('company', 'name')
                    ->label('Company'),
                SelectFilter::make('gdpr_consent')
                    ->options([
                        '1' => 'Consent granted',
                        '0' => 'Consent missing',
                    ]),
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
            ]);
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
        $query = parent::getEloquentQuery();

        if (app()->bound('currentTenant')) {
            $query->where('tenant_id', app('currentTenant')->getKey());
        }

        return $query;
    }

    protected static function deleteContact(Contact $contact): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Authentication required.');
        }

        /** @var ContactService $service */
        $service = App::make(ContactService::class);
        $service->delete($contact, $user);
    }

    public static function canViewAny(): bool
    {
        return static::userCan('contacts.manage') || static::userCan('contacts.view');
    }

    public static function canCreate(): bool
    {
        return static::userCan('contacts.manage');
    }

    public static function canEdit($record): bool
    {
        return static::userCan('contacts.manage');
    }

    public static function canDelete($record): bool
    {
        return static::userCan('contacts.manage');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
