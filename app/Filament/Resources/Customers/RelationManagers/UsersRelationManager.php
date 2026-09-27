<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\CustomerUser;
use App\Support\Locales;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The people who log in to the portal for this customer. */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Portaal-logins';

    protected static ?string $modelLabel = 'login';

    protected static ?string $pluralModelLabel = 'logins';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('manageLogins', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Naam')->required()->maxLength(255),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->unique(CustomerUser::class, 'email', ignoreRecord: true)
                    ->maxLength(255),
                Select::make('locale')
                    ->label('Taal')
                    ->options(Locales::all())
                    ->placeholder(fn () => 'Zoals de klant ('.Locales::label($this->getOwnerRecord()->locale).')')
                    ->helperText('Taal van de uitnodiging, het portaal en de e-mails.'),
                Toggle::make('is_active')->label('Actief')->default(true),
                Toggle::make('receives_order_confirmations')
                    ->label('Bevestiging per e-mail bij elke bestelling')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('customer'))
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->weight('medium'),
                TextColumn::make('email')->label('E-mail')->searchable()->color('gray')->copyable(),
                TextColumn::make('language')
                    ->label('Taal')
                    ->state(fn (CustomerUser $record) => strtoupper($record->preferredLocale()))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (CustomerUser $record) => match (true) {
                        ! $record->is_active => 'Uitgeschakeld',
                        $record->hasAcceptedInvitation() => 'Actief',
                        $record->invited_at !== null => 'Uitgenodigd',
                        default => 'Niet uitgenodigd',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Actief' => 'success',
                        'Uitgenodigd' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('receives_order_confirmations')->label('Bevestigingen')->boolean()->toggleable(),
                TextColumn::make('last_login_at')->label('Laatst aangemeld')->since()->placeholder('Nooit'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Login toevoegen')
                    ->modalHeading('Login toevoegen')
                    ->modalDescription('We sturen meteen een uitnodiging om een wachtwoord te kiezen.')
                    ->after(function (CustomerUser $record) {
                        $record->sendInvitation();
                        Notification::make()->success()->title("Uitnodiging verstuurd naar {$record->email}")->send();
                    }),
            ])
            ->recordActions([
                Action::make('invite')
                    ->label(fn (CustomerUser $record) => $record->hasAcceptedInvitation() ? 'Wachtwoordlink sturen' : 'Uitnodiging opnieuw sturen')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(fn (CustomerUser $record) => "Er gaat een link naar {$record->email} om een (nieuw) wachtwoord te kiezen. Een bestaand wachtwoord blijft werken tot het gewijzigd wordt.")
                    ->visible(fn (CustomerUser $record) => $record->is_active)
                    ->action(function (CustomerUser $record) {
                        $record->sendInvitation();
                        Notification::make()->success()->title("Verstuurd naar {$record->email}")->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nog geen logins')
            ->emptyStateDescription('Voeg de mensen toe die voor deze klant mogen bestellen.');
    }
}
