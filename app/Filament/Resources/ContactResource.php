<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use App\Models\ContactTag;
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
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->maxLength(50),
                        Forms\Components\Select::make('company_id')
                            ->label('Company')
                            ->relationship('company', 'name', modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('domain')
                                    ->maxLength(255)
                                    ->helperText('Optional domain used to auto-link contacts.'),
                            ])
                            ->createOptionAction(fn (Forms\Components\Actions\Action $action) => $action->label('Create Company')),
                        Forms\Components\TagsInput::make('tags')
                            ->label('Tags')
                            ->placeholder('Add tags')
                            ->suggestions(fn () => ContactTag::query()->pluck('name', 'name')->all())
                            ->helperText('Use tags to segment contacts for automation.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Compliance')
                    ->schema([
                        Forms\Components\Toggle::make('gdpr_marketing_opt_in')
                            ->label('Marketing consent')
                            ->required(),
                        Forms\Components\Toggle::make('gdpr_tracking_opt_in')
                            ->label('Tracking consent')
                            ->required(),
                        Forms\Components\Placeholder::make('gdpr_consent_recorded_at')
                            ->label('Consent recorded at')
                            ->content(fn (?Contact $record) => $record?->gdpr_consent_recorded_at?->toDayDateTimeString() ?? 'Not recorded'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addButtonLabel('Add attribute')
                            ->reorderable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company')
                    ->sortable(),
                Tables\Columns\TagsColumn::make('tags.name')
                    ->label('Tags'),
                Tables\Columns\IconColumn::make('gdpr_marketing_opt_in')
                    ->label('Marketing')
                    ->boolean(),
                Tables\Columns\IconColumn::make('gdpr_tracking_opt_in')
                    ->label('Tracking')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->since()
                    ->label('Updated'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Company')
                    ->relationship('company', 'name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('tag')
                    ->label('Tag')
                    ->multiple()
                    ->relationship('tags', 'name'),
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
            ->defaultSort('name');
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
        $query = parent::getEloquentQuery()->with(['company', 'tags']);

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
}
