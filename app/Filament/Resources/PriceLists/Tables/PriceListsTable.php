<?php

namespace App\Filament\Resources\PriceLists\Tables;

use App\Models\PriceList;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PriceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['items', 'customers'])->with('customerTypes'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Prijslijst')->description(fn (PriceList $record) => $record->description)->searchable()->weight('medium'),
                TextColumn::make('customerTypes.name')->label('Klanttypes')->badge()->color('gray')->placeholder('Geen'),
                TextColumn::make('customers_count')->label('Extra klanten')->alignCenter()->toggleable(),
                TextColumn::make('items_count')->label('Producten')->alignCenter(),
                TextColumn::make('validity')
                    ->label('Geldig')
                    ->state(fn (PriceList $record) => match (true) {
                        $record->valid_from && $record->valid_until => $record->valid_from->format('d/m').' – '.$record->valid_until->format('d/m/Y'),
                        (bool) $record->valid_from => 'vanaf '.$record->valid_from->format('d/m/Y'),
                        (bool) $record->valid_until => 't.e.m. '.$record->valid_until->format('d/m/Y'),
                        default => 'Altijd',
                    })
                    ->color('gray'),
                IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Dupliceren')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->visible(fn () => auth()->user()->can('create', PriceList::class))
                    ->schema([
                        TextInput::make('name')->label('Naam van de kopie')->required()->maxLength(255),
                    ])
                    ->fillForm(fn (PriceList $record) => ['name' => "{$record->name} (kopie)"])
                    ->modalDescription('Kopieert alle producten en prijzen. De kopie staat nog niet actief en is aan niemand gekoppeld.')
                    ->action(function (PriceList $record, array $data) {
                        $copy = DB::transaction(function () use ($record, $data) {
                            // Copy real fields only: the table row also carries withCount() columns.
                            $copy = PriceList::create([
                                ...$record->only(['description', 'valid_from', 'valid_until', 'translations']),
                                'name' => $data['name'],
                                'is_active' => false,
                            ]);

                            foreach ($record->items()->get() as $item) {
                                $copy->items()->create($item->only(['product_id', 'price', 'min_quantity', 'note', 'sort_order']));
                            }

                            return $copy;
                        });

                        Notification::make()->success()->title("Kopie \"{$copy->name}\" aangemaakt")->send();
                    }),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Nog geen prijslijsten')
            ->emptyStateDescription('Maak een prijslijst, zet er producten met een prijs in en koppel ze aan een klanttype.');
    }
}
