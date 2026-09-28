<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\PriceListItem;
use App\Support\CustomerPrices;
use App\Support\DeliveryCalendar;
use App\Support\Locales;
use App\Support\Money;
use App\Support\OrderChanges;
use App\Support\OrderRecipients;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Turns a basket from the portal into an order. The basket only carries
 * price list item ids and quantities; everything else — whether the customer
 * may see that list, the price, the unit — is looked up again here.
 */
class PlaceOrder
{
    /**
     * @param  array<int|string, float|int|string>  $lines  price_list_item_id => quantity
     * @param  array<int|string, string|null>  $notes  price_list_item_id => note
     */
    public function handle(
        CustomerUser $author,
        array $lines,
        ?string $requestedDeliveryDate = null,
        ?string $customerNote = null,
        array $notes = [],
    ): Order {
        $customer = $author->customer;
        [$lines, $items] = $this->validLines($customer, $lines);
        $this->checkDelivery($customer, $lines, $items, $requestedDeliveryDate);

        $order = $this->create($author, $lines, $items, $requestedDeliveryDate, $customerNote, $notes);

        $this->notify($order, OrderReceived::PLACED);

        return $order;
    }

    /**
     * The customer changes their own order: same checks as a new one, and only
     * while {@see OrderChanges} allows it. Lines are replaced wholesale, at
     * today's prices; a day price staff already filled in is kept.
     *
     * @param  array<int|string, float|int|string>  $lines
     * @param  array<int|string, string|null>  $notes
     */
    public function update(Order $order, CustomerUser $author, array $lines, ?string $requestedDeliveryDate = null, ?string $customerNote = null, array $notes = []): Order
    {
        $this->ensureChangeable($order, $author);

        [$lines, $items] = $this->validLines($author->customer, $lines);
        $this->checkDelivery($author->customer, $lines, $items, $requestedDeliveryDate);

        DB::transaction(function () use ($order, $author, $lines, $items, $requestedDeliveryDate, $customerNote, $notes) {
            // Staff may be confirming this very order right now; whoever locks first wins.
            $this->ensureChangeable(Order::lockForUpdate()->findOrFail($order->id), $author);

            $staffPrices = $order->items()->whereNotNull('unit_price')->pluck('unit_price', 'product_id');
            $order->items()->delete();
            $order->update(['requested_delivery_date' => $requestedDeliveryDate, 'customer_note' => $customerNote]);
            $this->writeItems($order, $lines, $items, $notes, $staffPrices->all());
            $order->unsetRelation('items')->recalculate();
        });

        $order->refresh();
        $this->notify($order, OrderReceived::CHANGED);

        return $order;
    }

    /** The customer calls it off; staff hear about it, the customer sees it on screen. */
    public function cancel(Order $order, CustomerUser $author): void
    {
        DB::transaction(function () use ($order, $author) {
            $this->ensureChangeable(Order::lockForUpdate()->findOrFail($order->id), $author);
            $order->update(['status' => OrderStatus::Cancelled]);
        });

        foreach (OrderRecipients::for($order) as $address) {
            Mail::to($address)->locale(Locales::default())->queue(new OrderReceived($order, OrderReceived::CANCELLED));
        }
    }

    private function ensureChangeable(Order $order, CustomerUser $author): void
    {
        abort_unless((int) $order->customer_id === (int) $author->customer_id, 404);

        if (! OrderChanges::allowed($order)) {
            throw ValidationException::withMessages(['order' => __('orders.errors.locked')]);
        }
    }

    /**
     * Positive quantities for items the customer may order, in whole units where needed.
     *
     * @return array{0: array<int|string, float>, 1: Collection<int, PriceListItem>}
     */
    private function validLines(Customer $customer, array $lines): array
    {
        $lines = array_filter(
            array_map(fn ($qty) => (float) $qty, $lines),
            fn (float $qty) => $qty > 0,
        );

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('orders.errors.empty')]);
        }

        // Only the items the portal showed: the customer's own price per product.
        $items = CustomerPrices::for($customer)->keyBy('id');

        $errors = [];

        foreach ($lines as $itemId => $quantity) {
            $item = $items->get($itemId);

            if (! $item) {
                // A list was switched off or an item removed while the basket sat open.
                $errors["lines.{$itemId}"] = __('orders.errors.unavailable');

                continue;
            }

            if (! $item->product->unit->allowsDecimals() && floor($quantity) != $quantity) {
                $errors["lines.{$itemId}"] = __('orders.errors.whole', ['product' => $item->product->name]);
            }

            if ($item->min_quantity !== null && $quantity < (float) $item->min_quantity) {
                $errors["lines.{$itemId}"] = __('orders.errors.minimum', [
                    'product' => $item->product->name,
                    'min' => rtrim(rtrim((string) $item->min_quantity, '0'), '.'),
                ]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$lines, $items];
    }

    /**
     * The delivery date must still be open (deadlines pass while a basket sits
     * in a browser), and the priced part of the order must reach the minimum.
     */
    private function checkDelivery(Customer $customer, array $lines, $items, ?string $date): void
    {
        $calendar = DeliveryCalendar::for($customer);

        if ($calendar->hasRules()) {
            if (blank($date)) {
                throw ValidationException::withMessages(['requested_delivery_date' => __('delivery.errors.required')]);
            }

            if (! $calendar->allows($date)) {
                throw ValidationException::withMessages(['requested_delivery_date' => __('delivery.errors.unavailable')]);
            }
        }

        $minimum = $calendar->minimumOrderAmount();

        if ($minimum !== null) {
            $subtotal = collect($lines)->sum(fn ($quantity, $itemId) => (float) ($items->get($itemId)->price ?? 0) * $quantity);

            if ($subtotal < $minimum) {
                throw ValidationException::withMessages(['lines' => __('delivery.errors.minimum', ['amount' => Money::format($minimum)])]);
            }
        }
    }

    private function create(CustomerUser $author, array $lines, $items, ?string $date, ?string $note, array $notes): Order
    {
        // Numbers come from max()+1; two orders in the same instant collide on
        // the unique index, and the loser simply takes the next number.
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($author, $lines, $items, $date, $note, $notes) {
                    $order = Order::create([
                        'number' => Order::nextNumber(),
                        'customer_id' => $author->customer_id,
                        'customer_user_id' => $author->id,
                        'status' => OrderStatus::New,
                        'requested_delivery_date' => $date,
                        'customer_note' => $note,
                        'submitted_at' => now(),
                    ]);

                    $this->writeItems($order, $lines, $items, $notes);
                    $order->recalculate();

                    return $order;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Snapshots every line. `$keepPrices` (product_id => price) holds day
     * prices staff already filled in on an order being changed.
     *
     * @param  array<int, string>  $keepPrices
     */
    private function writeItems(Order $order, array $lines, Collection $items, array $notes, array $keepPrices = []): void
    {
        foreach ($lines as $itemId => $quantity) {
            /** @var PriceListItem $item */
            $item = $items->get($itemId);

            $order->items()->create([
                'product_id' => $item->product_id,
                'price_list_id' => $item->price_list_id,
                'product_name' => $item->product->name,
                'translations' => $item->product->translationsOf('name', as: 'product_name') ?: null,
                'sku' => $item->product->sku,
                'unit' => $item->product->unit,
                'unit_price' => $item->price ?? $keepPrices[$item->product_id] ?? null,
                'vat_rate' => $item->product->vat_rate,
                'quantity' => $quantity,
                'note' => filled($notes[$itemId] ?? null) ? mb_substr($notes[$itemId], 0, 255) : null,
            ]);
        }
    }

    private function notify(Order $order, string $event): void
    {
        // Staff work in the base language, whatever the customer ordered in.
        foreach (OrderRecipients::for($order) as $address) {
            Mail::to($address)->locale(Locales::default())->queue(new OrderReceived($order, $event));
        }

        $author = $order->customerUser;

        if ($author?->receives_order_confirmations) {
            // Passing the model (not the address) picks up its preferred language.
            Mail::to($author)->queue(new OrderConfirmation($order, changed: $event === OrderReceived::CHANGED));
        }
    }
}
