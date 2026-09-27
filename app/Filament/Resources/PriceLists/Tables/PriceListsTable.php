<?php

namespace App\Filament\Resources\PriceLists\Tables;

use App\Filament\Resources\PriceLists\PriceListResource;
use App\Models\PriceList;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PriceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('items')->with(['customerTypes', 'customers']))
            ->defaultSort('name')
            // A click on a list opens its prices; settings are the second tab there.
            ->recordUrl(fn (PriceList $record) => PriceListResource::getUrl('prices', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Prijslijst')->description(fn (PriceList $record) => $record->description)->searchable()->weight('medium'),
                TextColumn::make('audience')
                    ->label('Voor')
                    ->state(fn (PriceList $record) => [
                        ...$record->customerTypes->pluck('name')->all(),
                        ...$record->customers->map(fn ($customer) => "Klant: {$customer->name}")->all(),
                    ])
                    ->badge()
                    ->color(fn (string $state) => str_starts_with($state, 'Klant:') ? 'warning' : 'gray')
                    ->placeholder('Niemand'),
                TextColumn::make('items_count')->label('Producten')->alignCenter(),
                TextColumn::make('validity')
                    ->label('Geldig')
                    ->state(fn (PriceList $record) => match (true) {
                        $record->valid_from && $record->valid_until => $record->valid_from->format('d/m').' – '.$record->valid_until->format('d/m/Y'),
                        (bool) $record->valid_from => 'vanaf '.$record->valid_from->format('d/m/Y'),
                        (bool) $record->valid_until => 't.e.m. '.$record->valid_until->format('d/m/Y'),
                        default => 'Altijd',
                    })
                    ->color('gray')
                    ->toggleable(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->recordActions([
                Action::make('prices')
                    ->label('Prijzen')
                    ->icon('heroicon-o-currency-euro')
                    ->url(fn (PriceList $record) => PriceListResource::getUrl('prices', ['record' => $record])),
                Action::make('settings')
                    ->label('Instellingen')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('gray')
                    ->url(fn (PriceList $record) => PriceListResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (PriceList $record) => auth()->user()->can('update', $record)),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nog geen prijslijsten')
            ->emptyStateDescription('Maak een prijslijst, kies voor welke klanttypes ze geldt en vul de prijzen in.');
    }
}
