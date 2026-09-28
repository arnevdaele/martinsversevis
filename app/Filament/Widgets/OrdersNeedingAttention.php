<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** New orders to confirm and confirmed ones with empty day prices, soonest delivery first. */
class OrdersNeedingAttention extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()->can('viewAny', Order::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Nog te behandelen')
            ->query(fn () => Order::query()
                ->visibleTo(auth()->user())
                ->with('customer.type')
                ->where(fn (Builder $query) => $query
                    ->where('status', OrderStatus::New)
                    ->orWhere(fn (Builder $query) => $query->where('status', OrderStatus::Confirmed)->where('has_unpriced_items', true)))
                ->orderByRaw('requested_delivery_date is null, requested_delivery_date')
                ->orderBy('submitted_at'))
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->poll('60s')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('number')->label('Nummer')->weight('medium'),
                TextColumn::make('customer.name')->label('Klant')->description(fn (Order $record) => $record->customer->type?->name),
                TextColumn::make('requested_delivery_date')->label('Levering')->date('D d/m')->placeholder('—'),
                TextColumn::make('submitted_at')->label('Geplaatst')->since()->tooltip(fn (Order $record) => $record->submitted_at?->format('d/m/Y H:i')),
                TextColumn::make('todo')
                    ->label('Te doen')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Order $record) => array_values(array_filter([
                        $record->status === OrderStatus::New ? 'Bevestigen' : null,
                        $record->has_unpriced_items ? 'Dagprijzen invullen' : null,
                    ]))),
                TextColumn::make('total')->label('Totaal')->money('EUR', locale: 'nl_BE')->alignEnd(),
            ])
            ->emptyStateHeading('Niets te behandelen')
            ->emptyStateDescription('Alle bestellingen zijn bevestigd en geprijsd.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
