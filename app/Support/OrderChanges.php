<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;

/**
 * Until when a customer may change or cancel an order themselves: while staff
 * haven't confirmed it yet, and before the order deadline of its delivery day
 * (the same deadline the portal showed when the day was picked). Without
 * delivery rules there is no deadline; the order stays open until staff
 * confirm it or its date has come.
 */
final class OrderChanges
{
    public static function allowed(Order $order, ?CarbonImmutable $now = null): bool
    {
        if ($order->status !== OrderStatus::New) {
            return false;
        }

        $now ??= CarbonImmutable::now();
        $calendar = DeliveryCalendar::for($order->customer);

        if (! $calendar->hasRules()) {
            return $order->requested_delivery_date === null || $order->requested_delivery_date->isAfter($now->startOfDay());
        }

        return self::deadline($order, $now) !== null;
    }

    /** The cutoff of the order's delivery day, or null when that has passed or doesn't apply. */
    public static function deadline(Order $order, ?CarbonImmutable $now = null): ?CarbonImmutable
    {
        if ($order->requested_delivery_date === null) {
            return null;
        }

        $date = $order->requested_delivery_date->toDateString();
        $option = collect(DeliveryCalendar::for($order->customer)->options($now))
            ->first(fn (array $option) => $option['date']->toDateString() === $date);

        return $option['cutoff'] ?? null;
    }
}
