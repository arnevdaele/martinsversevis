<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Customers who order most weeks, and haven't yet this week although by now
 * they usually have: worth a phone call before the deadline passes.
 *
 * "Usually by now" is the median moment of their first order in a week
 * (weekday and time) over the last {@see self::WEEKS} weeks.
 */
final class RegularCustomers
{
    /** How many past weeks are looked at. */
    public const WEEKS = 8;

    /** In how many of those a customer must have ordered to count as a regular. */
    public const MIN_WEEKS = 4;

    /**
     * @return Collection<int, array{customer: Customer, weeks: int, usually: CarbonImmutable, lastOrder: CarbonImmutable}>
     */
    public static function missing(User $user, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $thisWeek = $now->startOfWeek(CarbonInterface::MONDAY);

        $orders = Order::query()
            ->visibleTo($user)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('submitted_at', '>=', $thisWeek->subWeeks(self::WEEKS))
            ->whereHas('customer', fn ($query) => $query->where('is_active', true))
            ->get(['customer_id', 'submitted_at']);

        $missing = $orders->groupBy('customer_id')
            ->reject(fn (Collection $orders) => $orders->contains(fn (Order $order) => $order->submitted_at->gte($thisWeek)))
            ->map(function (Collection $orders, int $customerId) use ($thisWeek) {
                // Minutes into the week of each week's first order.
                $firsts = $orders
                    ->groupBy(fn (Order $order) => $order->submitted_at->toImmutable()->startOfWeek(CarbonInterface::MONDAY)->toDateString())
                    ->map(fn (Collection $week) => $week->min(fn (Order $order) => (int) $order->submitted_at->toImmutable()
                        ->startOfWeek(CarbonInterface::MONDAY)->diffInMinutes($order->submitted_at)))
                    ->sort()
                    ->values();

                return [
                    'customer_id' => $customerId,
                    'weeks' => $firsts->count(),
                    'usually' => $thisWeek->addMinutes($firsts[intdiv($firsts->count() - 1, 2)] ?? 0),
                    'lastOrder' => $orders->max('submitted_at')->toImmutable(),
                ];
            })
            ->filter(fn (array $habit) => $habit['weeks'] >= self::MIN_WEEKS && $habit['usually']->lte($now))
            ->sortBy(fn (array $habit) => $habit['usually'])
            ->values();

        $customers = Customer::with('type')->findMany($missing->pluck('customer_id'))->keyBy('id');

        return $missing->map(fn (array $habit) => [
            'customer' => $customers->get($habit['customer_id']),
            'weeks' => $habit['weeks'],
            'usually' => $habit['usually'],
            'lastOrder' => $habit['lastOrder'],
        ]);
    }
}
