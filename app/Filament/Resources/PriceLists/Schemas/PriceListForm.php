<?php

namespace App\Filament\Resources\PriceLists\Schemas;

use App\Filament\Support\Translations;
use App\Models\PriceList;
use App\Support\ListPrices;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Prijslijst')
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')->label('Naam')->required()->maxLength(255),
                        Textarea::make('description')
                            ->label('Omschrijving')
                            ->helperText('Staat bovenaan de lijst in het portaal.')
                            ->rows(2),
                    ]),

                Section::make('Geldigheid')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')->label('Actief')->default(true),
                        DatePicker::make('valid_from')->label('Geldig vanaf')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('valid_until')
                            ->label('Geldig tot en met')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('valid_from'),
                    ]),

                Translations::section(fn (string $locale) => [
                    TextInput::make(Translations::field($locale, 'name'))->label('Naam')->maxLength(255),
                    Textarea::make(Translations::field($locale, 'description'))->label('Omschrijving')->rows(2),
                ]),

                Section::make('Wie ziet deze lijst?')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('customerTypes')
                            ->label('Klanttypes')
                            ->relationship('customerTypes', 'name')
                            ->multiple()
                            ->preload()
                            ->helperText('Alle klanten van deze types zien de lijst.'),
                        Select::make('customers')
                            ->label('Of enkel voor bepaalde klanten')
                            ->relationship('customers', 'name')
                            ->multiple()
                            ->searchable()
                            ->helperText('Hun prijzen in deze lijst gaan voor op die van hun klanttype. Zo geef je één klant een andere prijs.'),
                    ]),

                Section::make('Beginnen met prijzen')
                    ->description('Optioneel: neem de prijzen van een bestaande lijst over, eventueel duurder of goedkoper. Daarna kan je alles nog aanpassen.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->visibleOn('create')
                    ->schema([
                        Select::make('start.from')
                            ->label('Prijzen overnemen van')
                            ->options(fn () => PriceList::orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Leeg beginnen')
                            ->live()
                            ->dehydrated(false),
                        TextInput::make('start.percentage')
                            ->label('Aanpassing')
                            ->helperText('15 = 15% duurder')
                            ->numeric()
                            ->default(0)
                            ->suffix('%')
                            ->visible(fn (Get $get) => filled($get('start.from')))
                            ->dehydrated(false),
                        Select::make('start.step')
                            ->label('Afronden')
                            ->options(ListPrices::steps())
                            ->default('0.10')
                            ->native(false)
                            ->visible(fn (Get $get) => filled($get('start.from')))
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
