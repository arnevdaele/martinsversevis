<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Staff mostly come here to fill in day prices or correct a weight after
 * cutting. Every change recalculates the order's totals.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Producten';

    protected static ?string $modelLabel = 'lijn';

    public function isReadOnly(): bool
    {
        return ! auth()->user()->can('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('quantity')
                    ->label('Hoeveelheid')
                    ->numeric()
                    ->minValue(0.001)
                    ->required()
                    ->suffix(fn (OrderItem $record) => $record->unit->short()),
                TextInput::make('unit_price')
                    ->label('Prijs per eenheid (excl. btw)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->helperText('Leeg = dagprijs, nog te bepalen.'),
                TextInput::make('note')->label('Opmerking')->maxLength(255)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->paginated(false)
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->description(fn (OrderItem $record) => $record->note)
                    ->weight('medium'),
                TextColumn::make('sku')->label('Code')->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity')
                    ->label('Hoeveelheid')
                    ->formatStateUsing(fn (OrderItem $record) => Money::quantity($record->quantity, $record->unit))
                    ->alignEnd(),
                TextColumn::make('unit_price')
                    ->label('Prijs')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->placeholder('Dagprijs')
                    ->color(fn ($state) => $state === null ? 'warning' : null)
                    ->alignEnd(),
                TextColumn::make('vat_rate')->label('Btw')->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.').'%')->alignEnd()->toggleable(),
                TextColumn::make('line_total')
                    ->label('Totaal')
                    ->formatStateUsing(fn ($state) => Money::format($state))
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->recordActions([
                EditAction::make()->after(fn () => $this->recalculate()),
                DeleteAction::make()->after(fn () => $this->recalculate()),
            ]);
    }

    private function recalculate(): void
    {
        /** @var Order $order */
        $order = $this->getOwnerRecord();
        $order->unsetRelation('items');
        $order->recalculate();

        // The totals live on the parent page; tell it to re-read the record.
        $this->dispatch('refresh-order-totals');
    }
}
