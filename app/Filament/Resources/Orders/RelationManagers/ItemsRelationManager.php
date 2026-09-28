<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Actions\WeighOrderItem;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\OrderHistory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

/**
 * Staff mostly come here to fill in day prices or the weight after cutting.
 * Every change recalculates the order's totals and lands in its history.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Producten';

    protected static ?string $modelLabel = 'lijn';

    /** The line as it was before the edit modal saved, for the history. */
    private ?array $before = null;

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
                    ->label('Besteld')
                    ->numeric()
                    ->minValue(0.001)
                    ->required()
                    ->suffix(fn (OrderItem $record) => $record->unit->short()),
                TextInput::make('delivered_quantity')
                    ->label('Geleverd (gewogen)')
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn (OrderItem $record) => $record->unit->short())
                    ->helperText('Leeg = zoals besteld. Dit wordt aangerekend.'),
                TextInput::make('unit_price')
                    ->label('Prijs per eenheid (excl. btw)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('€')
                    ->helperText('Leeg = dagprijs, nog te bepalen.'),
                TextInput::make('note')->label('Opmerking')->maxLength(255),
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
                    ->label('Besteld')
                    ->formatStateUsing(fn (OrderItem $record) => Money::quantity($record->quantity, $record->unit))
                    ->alignEnd(),
                TextInputColumn::make('delivered_quantity')
                    ->label('Geleverd')
                    ->getStateUsing(fn (OrderItem $record) => WeighOrderItem::format($record->delivered_quantity))
                    ->placeholder(fn (OrderItem $record) => WeighOrderItem::format($record->quantity))
                    ->suffix(fn (OrderItem $record) => $record->unit->short())
                    ->inputMode('decimal')
                    ->rules(['nullable', WeighOrderItem::RULE])
                    ->validationMessages(['regex' => 'Vul een gewicht in zoals 2,35.'])
                    ->disabled(fn () => $this->isReadOnly())
                    ->updateStateUsing(function (OrderItem $record, $state) {
                        app(WeighOrderItem::class)->handle($record, $state, auth()->user());
                        $this->dispatch('refresh-order-totals');

                        return WeighOrderItem::format($record->delivered_quantity);
                    })
                    ->width('9rem'),
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
                EditAction::make()
                    ->before(fn (OrderItem $record) => $this->before = OrderHistory::line($record->fresh()))
                    ->after(fn (OrderItem $record) => $this->recalculate(OrderHistory::lineDiff($this->before ?? [], OrderHistory::line($record)))),
                DeleteAction::make()
                    ->after(fn (OrderItem $record) => $this->recalculate([
                        ['field' => 'removed', 'product' => $record->product_name, 'unit' => $record->unit->value, 'quantity' => $record->quantity],
                    ])),
            ]);
    }

    private function recalculate(array $changes): void
    {
        /** @var Order $order */
        $order = $this->getOwnerRecord();
        $order->unsetRelation('items');
        $order->recalculate();

        OrderHistory::record($order, OrderEvent::LINES, auth()->user(), $changes);

        // The totals and history live on the parent page; tell it to re-read.
        $this->dispatch('refresh-order-totals');
    }
}
