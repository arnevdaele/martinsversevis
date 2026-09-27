<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Filament\Support\CustomerTypeScope;
use App\Models\PriceList;
use App\Support\Locales;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Klant')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Naam')
                                    ->helperText('Bedrijfsnaam, of voor- en achternaam bij een particulier.')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('contact_name')->label('Contactpersoon')->maxLength(255),
                                TextInput::make('vat_number')->label('Btw-nummer')->maxLength(40)->placeholder('BE0123456789'),
                                TextInput::make('email')->label('E-mail (algemeen)')->email()->maxLength(255),
                                TextInput::make('phone')->label('Telefoon')->tel()->maxLength(40),
                            ]),

                        Section::make('Adres & levering')
                            ->columns(6)
                            ->schema([
                                TextInput::make('street')->label('Straat en nummer')->maxLength(255)->columnSpan(6),
                                TextInput::make('postal_code')->label('Postcode')->maxLength(20)->columnSpan(2),
                                TextInput::make('city')->label('Gemeente')->maxLength(255)->columnSpan(3),
                                Select::make('country')
                                    ->label('Land')
                                    ->options(['BE' => 'België', 'NL' => 'Nederland', 'FR' => 'Frankrijk', 'LU' => 'Luxemburg', 'DE' => 'Duitsland'])
                                    ->default('BE')
                                    ->required()
                                    ->columnSpan(1),
                                Textarea::make('delivery_instructions')
                                    ->label('Leverinstructies')
                                    ->helperText('Bv. levering via achteringang, vóór 9u.')
                                    ->rows(2)
                                    ->columnSpan(6),
                            ]),

                        Section::make('Interne notities')
                            ->description('Enkel zichtbaar in het beheer.')
                            ->collapsible()
                            ->schema([
                                Textarea::make('internal_notes')->hiddenLabel()->rows(3),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Type & prijzen')
                            ->schema([
                                Select::make('customer_type_id')
                                    ->label('Klanttype')
                                    ->relationship('type', 'name', fn (Builder $query) => CustomerTypeScope::query($query))
                                    ->required()
                                    ->live()
                                    ->native(false)
                                    ->helperText('Bepaalt welke prijslijsten deze klant ziet.'),

                                Select::make('extraPriceLists')
                                    ->label('Eigen prijslijsten')
                                    ->relationship('extraPriceLists', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->helperText('Prijzen hierin gaan voor op die van het klanttype. Makkelijker: de knop "Eigen prijzen" bovenaan.'),

                                TextEntry::make('type_lists')
                                    ->label('Via het klanttype')
                                    ->state(function (Get $get) {
                                        $names = PriceList::whereHas('customerTypes', fn ($q) => $q->whereKey($get('customer_type_id')))
                                            ->orderBy('name')
                                            ->pluck('name');

                                        return $names->isEmpty() ? 'Geen prijslijsten gekoppeld.' : $names->implode(', ');
                                    })
                                    ->visible(fn (Get $get) => filled($get('customer_type_id'))),
                            ]),

                        Section::make('Taal & levering')
                            ->schema([
                                Select::make('locale')
                                    ->label('Taal')
                                    ->options(Locales::all())
                                    ->default(Locales::default())
                                    ->required()
                                    ->native(false)
                                    ->helperText('Voor het portaal en de e-mails. Elke login kan dit nog zelf wijzigen.'),
                                Select::make('delivery_schedule_id')
                                    ->label('Eigen leverschema')
                                    ->relationship('deliverySchedule', 'name')
                                    ->placeholder('Zoals het klanttype')
                                    ->helperText('Enkel invullen als deze klant afwijkt, bv. op een andere ronde.'),
                            ]),

                        Section::make('Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Actief')
                                    ->helperText('Uit = niemand van deze klant kan nog inloggen of bestellen.')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
