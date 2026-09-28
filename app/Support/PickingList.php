<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Everything to be delivered on one day, for the people buying and packing:
 * what to buy (quantities per product, summed over all orders) and what goes
 * in which box (the orders themselves). Cancelled orders don't count; staff
 * only see the customers they may see.
 */
final class PickingList
{
    /** @param  EloquentCollection<int, Order>  $orders */
    private function __construct(public readonly CarbonImmutable $date, public readonly EloquentCollection $orders) {}

    public static function for(CarbonImmutable $date, User $user): self
    {
        $orders = self::query($user)
            ->whereDate('requested_delivery_date', $date)
            ->with(['customer.type', 'customerUser', 'items.product.category'])
            ->get()
            ->sortBy(fn (Order $order) => mb_strtolower($order->customer->name))
            ->values();

        return new self($date, $orders);
    }

    /** The first day from today on with something to deliver, else today. */
    public static function nextDate(User $user): CarbonImmutable
    {
        $next = self::query($user)->whereDate('requested_delivery_date', '>=', today())->min('requested_delivery_date');

        return CarbonImmutable::parse($next ?? today())->startOfDay();
    }

    /** The nearest delivery day with orders before or after the given one. */
    public static function neighbour(CarbonImmutable $date, User $user, bool $later): ?CarbonImmutable
    {
        $query = self::query($user)->whereDate('requested_delivery_date', $later ? '>' : '<', $date);
        $found = $later ? $query->min('requested_delivery_date') : $query->max('requested_delivery_date');

        return $found ? CarbonImmutable::parse($found)->startOfDay() : null;
    }

    /** Orders still waiting for a date, which this list can't show. */
    public static function withoutDate(User $user): int
    {
        return self::query($user)->whereNull('requested_delivery_date')->whereIn('status', [OrderStatus::New, OrderStatus::Confirmed])->count();
    }

    /** @return Builder<Order> */
    private static function query(User $user): Builder
    {
        return Order::query()->visibleTo($user)->where('status', '!=', OrderStatus::Cancelled);
    }

    /**
     * The purchase list: one line per product and unit, grouped by category.
     *
     * @return Collection<string, Collection<int, array{name: string, quantity: string, orders: int, notes: list<string>}>>
     */
    public function totals(): Collection
    {
        return $this->orders
            ->flatMap(fn (Order $order) => $order->items->map(fn (OrderItem $item) => [$order, $item]))
            ->groupBy(fn (array $pair) => ($pair[1]->product_id ?? 'name:'.$pair[1]->product_name).'|'.$pair[1]->unit->value)
            ->map(function (Collection $pairs) {
                /** @var OrderItem $item */
                $item = $pairs->first()[1];

                return [
                    'category' => $item->product?->category?->name ?? 'Overige',
                    'category_sort' => $item->product?->category?->sort_order ?? PHP_INT_MAX,
                    'sort' => [$item->product?->sort_order ?? 0, mb_strtolower($item->product_name)],
                    'name' => $item->product_name,
                    'quantity' => Money::quantity($pairs->sum(fn (array $pair) => (float) $pair[1]->quantity), $item->unit, 'nl'),
                    'orders' => $pairs->map(fn (array $pair) => $pair[0]->id)->unique()->count(),
                    'notes' => $pairs
                        ->filter(fn (array $pair) => filled($pair[1]->note))
                        ->map(fn (array $pair) => "{$pair[0]->customer->name}: {$pair[1]->note}")
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy([['category_sort', 'asc'], ['category', 'asc'], ['sort', 'asc']])
            ->groupBy('category')
            ->map(fn (Collection $lines) => $lines->map(fn (array $line) => collect($line)->only(['name', 'quantity', 'orders', 'notes'])->all())->values());
    }

    public function unconfirmed(): int
    {
        return $this->orders->where('status', OrderStatus::New)->count();
    }
}
