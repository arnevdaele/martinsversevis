<?php

namespace App\Filament\Resources\PriceLists\Schemas;

use App\Filament\Support\Translations;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                            ->helperText('Alle klanten van deze types.'),
                        Select::make('customers')
                            ->label('Individuele klanten')
                            ->relationship('customers', 'name')
                            ->multiple()
                            ->searchable()
                            ->helperText('Enkel voor een uitzondering, bovenop hun eigen type.'),
                    ]),
            ]);
    }
}
