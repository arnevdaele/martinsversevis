<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\CustomerTypeScope;
use App\Filament\Support\PermissionMatrix;
use App\Models\User;
use App\Support\Grants;
use App\Support\Permissions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $actor */
        $actor = auth()->user();
        $actorIsRestricted = $actor->visibleCustomerTypeIds() !== null;

        return $schema
            ->columns(3)
            ->components([
                Section::make('Account')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Naam')->required()->maxLength(255),
                        TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                        TextInput::make('phone')->label('Telefoon')->tel()->maxLength(40),
                        TextInput::make('password')
                            ->label('Wachtwoord')
                            ->password()
                            ->revealable()
                            ->minLength(12)
                            ->required(fn (?User $record) => $record === null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (?User $record) => $record
                                ? 'Laat leeg om het huidige wachtwoord te behouden.'
                                : 'Minstens 12 tekens. Geef het persoonlijk door; de gebruiker kan het wijzigen in het profiel.'),
                    ]),

                Section::make('Status')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Actief')
                            ->helperText('Uit = kan niet meer inloggen. Beter dan verwijderen: de historiek blijft.')
                            ->default(true)
                            ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                    ]),

                Section::make('Rollen')
                    ->description('Een rol is een vaste set rechten. Beheer rollen onder Beheer → Rollen.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('role_names')
                            ->hiddenLabel()
                            ->options(fn () => Role::where('guard_name', 'web')->orderBy('name')->pluck('name', 'name'))
                            ->descriptions(fn () => Role::where('guard_name', 'web')->withCount('permissions')->get()
                                ->mapWithKeys(fn (Role $role) => [$role->name => $role->name === Permissions::SUPER_ADMIN_ROLE
                                    ? 'Volledige toegang, ook tot beheerders en rollen.'
                                    : "{$role->permissions_count} rechten"]))
                            ->disableOptionWhen(fn (string $value) => ! Grants::for($actor)->canGrantRole(Role::findByName($value, 'web')))
                            ->in(fn () => Role::where('guard_name', 'web')->pluck('name')->all())
                            ->columns(3)
                            ->dehydrated(false),
                    ]),

                Section::make('Zichtbare klanttypes')
                    ->description('Beperk tot welke klanten en bestellingen deze beheerder toegang heeft. Leeg = alle klanttypes. Bepaalt ook voor welke bestellingen een e-mail volgt.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('customerTypes')
                            ->hiddenLabel()
                            ->relationship('customerTypes', 'name', fn (Builder $query) => CustomerTypeScope::query($query))
                            ->multiple()
                            ->preload()
                            ->placeholder('Alle klanttypes')
                            // Someone who only sees Horeca must not create a colleague who sees everything.
                            ->required($actorIsRestricted)
                            ->helperText($actorIsRestricted ? 'Je kan enkel de klanttypes toekennen die je zelf ziet.' : null),
                    ]),

                Section::make('Extra rechten')
                    ->description('Rechtstreeks aan deze persoon, bovenop de rollen. Meestal niet nodig.')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([PermissionMatrix::fields()]),
            ]);
    }
}
