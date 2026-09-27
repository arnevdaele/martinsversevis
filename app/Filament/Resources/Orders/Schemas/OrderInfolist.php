<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Order;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Bestelling')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('submitted_at')->label('Geplaatst')->dateTime('d/m/Y H:i'),
                        TextEntry::make('requested_delivery_date')->label('Gewenste levering')->date('l d/m/Y')->placeholder('Niet opgegeven'),
                        TextEntry::make('customer_note')
                            ->label('Opmerking van de klant')
                            ->placeholder('Geen')
                            ->columnSpanFull(),
                        TextEntry::make('internal_note')
                            ->label('Interne notitie')
                            ->placeholder('Geen')
                            ->columnSpanFull(),
                        TextEntry::make('handler.name')->label('Behandeld door')->placeholder('Nog niemand'),
                    ]),

                Section::make('Klant')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('customer.name')
                            ->label('Klant')
                            ->weight('medium')
                            ->url(fn (Order $record) => auth()->user()->can('view', $record->customer)
                                ? CustomerResource::getUrl('edit', ['record' => $record->customer])
                                : null),
                        TextEntry::make('customer.type.name')->label('Type')->badge()->color('gray'),
                        TextEntry::make('customerUser.name')
                            ->label('Geplaatst door')
                            ->state(fn (Order $record) => $record->customerUser
                                ? "{$record->customerUser->name} — {$record->customerUser->email}"
                                : null)
                            ->placeholder('Onbekend'),
                        TextEntry::make('customer.phone')->label('Telefoon')->placeholder('—'),
                        TextEntry::make('address')
                            ->label('Adres')
                            ->state(fn (Order $record) => $record->customer->address() ?: null)
                            ->placeholder('—'),
                        TextEntry::make('customer.delivery_instructions')->label('Leverinstructies')->placeholder('—'),
                    ]),

                Section::make('Totalen')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('subtotal')->label('Subtotaal excl. btw')->money('EUR', locale: 'nl_BE'),
                        TextEntry::make('vat_total')->label('Btw')->money('EUR', locale: 'nl_BE'),
                        TextEntry::make('total')->label('Totaal incl. btw')->money('EUR', locale: 'nl_BE')->weight('bold'),
                        IconEntry::make('has_unpriced_items')
                            ->label('Nog te prijzen')
                            ->boolean()
                            ->trueColor('warning')
                            ->falseColor('success')
                            ->trueIcon('heroicon-o-exclamation-circle')
                            ->falseIcon('heroicon-o-check-circle')
                            ->helperText(fn (Order $record) => $record->has_unpriced_items
                                ? 'Vul de dagprijzen in bij de producten hieronder.'
                                : null),
                    ]),
            ]);
    }
}
