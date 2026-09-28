<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\Unit;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Who changed what on an order, and when. Changes are worked out by comparing
 * two {@see snapshot()}s, so every place that touches an order records them the
 * same way: take a snapshot, change the order, {@see record()} the difference.
 *
 * A change is a small array, e.g. ['field' => 'quantity', 'product' => 'Zalmfilet',
 * 'unit' => 'kg', 'from' => '2.000', 'to' => '2.500']; {@see describe()} turns it
 * into a sentence in the current language.
 */
final class OrderHistory
{
    /** Staff edits that follow each other this closely are kept as one entry. */
    private const MERGE_MINUTES = 15;

    public static function snapshot(Order $order): array
    {
        $order->unsetRelation('items');

        return [
            'delivery_date' => $order->requested_delivery_date?->toDateString(),
            'customer_note' => $order->customer_note,
            'internal_note' => $order->internal_note,
            'status' => $order->status?->value,
            'lines' => $order->items()->get()
                ->mapWithKeys(fn (OrderItem $item) => [self::lineKey($item) => self::line($item)])
                ->all(),
        ];
    }

    /** One line on its own, for staff editing a single item. */
    public static function line(OrderItem $item): array
    {
        return [
            'product' => $item->product_name,
            'unit' => $item->unit->value,
            'quantity' => $item->quantity,
            'delivered' => $item->delivered_quantity,
            'price' => $item->unit_price,
            'note' => $item->note,
        ];
    }

    public static function lineKey(OrderItem $item): string
    {
        return ($item->product_id ?? 'name:'.$item->product_name).'|'.$item->unit->value;
    }

    /** @return list<array<string, mixed>> */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (['delivery_date', 'status', 'customer_note', 'internal_note'] as $field) {
            if (array_key_exists($field, $before) && ($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changes[] = ['field' => $field, 'from' => $before[$field], 'to' => $after[$field]];
            }
        }

        $old = $before['lines'] ?? [];
        $new = $after['lines'] ?? [];

        foreach ($new as $key => $line) {
            if (! isset($old[$key])) {
                $changes[] = ['field' => 'added', ...self::about($line), 'quantity' => $line['quantity']];

                continue;
            }

            $changes = [...$changes, ...self::lineDiff($old[$key], $line)];
        }

        foreach (array_diff_key($old, $new) as $line) {
            $changes[] = ['field' => 'removed', ...self::about($line), 'quantity' => $line['quantity']];
        }

        return $changes;
    }

    /** @return list<array<string, mixed>> */
    public static function lineDiff(array $before, array $after): array
    {
        $changes = [];

        foreach (['quantity', 'delivered', 'price', 'note'] as $field) {
            if (! self::same($before[$field] ?? null, $after[$field] ?? null)) {
                $changes[] = ['field' => $field, ...self::about($after), 'from' => $before[$field] ?? null, 'to' => $after[$field] ?? null];
            }
        }

        return $changes;
    }

    /**
     * Writes an entry. A staff member weighing one line after another gets one
     * entry for the lot, not one per line.
     */
    public static function record(Order $order, string $event, User|CustomerUser|null $actor, array $changes = []): ?OrderEvent
    {
        if ($changes === [] && in_array($event, [OrderEvent::LINES, OrderEvent::EDITED], true)) {
            return null;
        }

        if ($event === OrderEvent::LINES && $actor instanceof User) {
            $last = OrderEvent::where('order_id', $order->id)->latest('id')->first();

            if ($last?->event === OrderEvent::LINES && $last->user_id === $actor->id && $last->updated_at->gt(now()->subMinutes(self::MERGE_MINUTES))) {
                $merged = self::merge($last->details ?? [], $changes);
                $merged === [] ? $last->delete() : $last->update(['details' => $merged]);

                return $merged === [] ? null : $last;
            }
        }

        return OrderEvent::create([
            'order_id' => $order->id,
            'event' => $event,
            'user_id' => $actor instanceof User ? $actor->id : null,
            'customer_user_id' => $actor instanceof CustomerUser ? $actor->id : null,
            'actor_name' => $actor?->name,
            'details' => $changes ?: null,
        ]);
    }

    /** A second change to the same thing keeps the first "from" and the latest "to". */
    private static function merge(array $existing, array $new): array
    {
        foreach ($new as $change) {
            $index = collect($existing)->search(fn (array $old) => $old['field'] === $change['field']
                && ($old['product'] ?? null) === ($change['product'] ?? null)
                && array_key_exists('from', $old));

            if ($index === false || ! array_key_exists('from', $change)) {
                $existing[] = $change;

                continue;
            }

            $existing[$index]['to'] = $change['to'];

            if (self::same($existing[$index]['from'], $existing[$index]['to'])) {
                unset($existing[$index]);
            }
        }

        return array_values($existing);
    }

    public static function describe(array $change): string
    {
        $unit = Unit::tryFrom($change['unit'] ?? '') ?? Unit::Piece;
        $quantity = fn ($value) => $value === null ? '—' : Money::quantity($value, $unit);
        $price = fn ($value) => $value === null ? __('orders.day_price') : Money::format($value).' / '.$unit->short();
        $date = fn ($value) => $value === null ? __('orders.history.no_date') : CarbonImmutable::parse($value)->translatedFormat('l j F');
        $status = fn ($value) => OrderStatus::tryFrom((string) $value)?->getLabel() ?? '—';
        $product = $change['product'] ?? '';

        return match ($change['field']) {
            'added' => __('orders.history.added', ['product' => $product, 'quantity' => $quantity($change['quantity'])]),
            'removed' => __('orders.history.removed', ['product' => $product, 'quantity' => $quantity($change['quantity'])]),
            'quantity' => __('orders.history.quantity', ['product' => $product, 'from' => $quantity($change['from']), 'to' => $quantity($change['to'])]),
            'delivered' => $change['to'] === null
                ? __('orders.history.delivered_cleared', ['product' => $product])
                : __('orders.history.delivered', ['product' => $product, 'quantity' => $quantity($change['to'])]),
            'price' => __('orders.history.price', ['product' => $product, 'from' => $price($change['from']), 'to' => $price($change['to'])]),
            'note' => blank($change['to'])
                ? __('orders.history.note_cleared', ['product' => $product])
                : __('orders.history.note', ['product' => $product, 'note' => $change['to']]),
            'delivery_date' => __('orders.history.delivery_date', ['from' => $date($change['from']), 'to' => $date($change['to'])]),
            'status' => __('orders.history.status', ['from' => $status($change['from']), 'to' => $status($change['to'])]),
            'customer_note' => blank($change['to'])
                ? __('orders.history.customer_note_cleared')
                : __('orders.history.customer_note', ['note' => $change['to']]),
            'internal_note' => __('orders.history.internal_note'),
            'notified' => __('orders.history.notified'),
            'message' => __('orders.history.message', ['message' => $change['to']]),
            default => (string) $change['field'],
        };
    }

    /** Product name and unit, so a change still reads right once the line is gone. */
    private static function about(array $line): array
    {
        return ['product' => $line['product'], 'unit' => $line['unit']];
    }

    /** Decimals come back as "2.000" or 2.0; compare them as numbers. */
    private static function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (blank($a) ? null : $a) === (blank($b) ? null : $b);
    }
}
