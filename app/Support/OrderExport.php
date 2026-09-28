<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Orders for the bookkeeper, as a CSV that Excel opens correctly in Belgium:
 * semicolons, decimal commas, UTF-8 with a BOM. One row per order (with base
 * and VAT per rate) or one row per line. Quantities and amounts are what is
 * billed, so weighed lines count as weighed.
 */
class OrderExport
{
    public const PER_ORDER = 'orders';

    public const PER_LINE = 'lines';

    /**
     * Orders delivered in the period; one without a delivery date counts on the day it was placed.
     *
     * @param  list<OrderStatus>  $statuses
     * @return Collection<int, Order>
     */
    public static function orders(User $user, CarbonImmutable $from, CarbonImmutable $until, array $statuses): Collection
    {
        return Order::query()
            ->visibleTo($user)
            ->whereIn('status', $statuses)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereDate('requested_delivery_date', '>=', $from->toDateString())
                    ->whereDate('requested_delivery_date', '<=', $until->toDateString()))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('requested_delivery_date')
                    ->whereDate('created_at', '>=', $from->toDateString())
                    ->whereDate('created_at', '<=', $until->toDateString())))
            ->with('items', 'customer.type')
            ->orderByRaw('coalesce(requested_delivery_date, created_at)')
            ->orderBy('number')
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<list<string>> the header first
     */
    public static function rows(Collection $orders, string $layout): array
    {
        return $layout === self::PER_LINE ? self::lines($orders) : self::perOrder($orders);
    }

    /** @param  list<list<string>>  $rows */
    public static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\u{FEFF}");

        foreach ($rows as $row) {
            // A name starting with "=" would be run as a formula when opened in Excel.
            $row = array_map(fn (string $cell) => preg_match('/^[=+\-@\t\r]/', $cell) ? "'{$cell}" : $cell, $row);
            fputcsv($handle, $row, ';', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** @return list<list<string>> */
    private static function perOrder(Collection $orders): array
    {
        $rates = $orders
            ->flatMap(fn (Order $order) => array_keys($order->vatBreakdown()))
            ->unique()
            ->sort(SORT_NUMERIC)
            ->values();

        $header = ['Bestelnummer', 'Leverdatum', 'Besteld op', 'Status', 'Klant', 'Btw-nummer', 'Straat', 'Postcode', 'Gemeente', 'Klanttype'];

        foreach ($rates as $rate) {
            $header[] = 'Maatstaf '.DeliveryNotes::rate($rate);
            $header[] = 'Btw '.DeliveryNotes::rate($rate);
        }

        $rows = [[...$header, 'Totaal excl. btw', 'Btw', 'Totaal incl. btw', 'Nog te prijzen']];

        foreach ($orders as $order) {
            $breakdown = $order->vatBreakdown();
            $row = [...self::orderColumns($order), $order->customer->street ?? '', $order->customer->postal_code ?? '', $order->customer->city ?? '', $order->customer->type?->name ?? ''];

            foreach ($rates as $rate) {
                $row[] = self::amount($breakdown[$rate]['base'] ?? 0);
                $row[] = self::amount($breakdown[$rate]['vat'] ?? 0);
            }

            $rows[] = [...$row, self::amount($order->subtotal), self::amount($order->vat_total), self::amount($order->total), $order->has_unpriced_items ? 'ja' : 'nee'];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private static function lines(Collection $orders): array
    {
        $rows = [[
            'Bestelnummer', 'Leverdatum', 'Besteld op', 'Status', 'Klant', 'Btw-nummer',
            'Product', 'Artikelcode', 'Eenheid', 'Besteld', 'Geleverd', 'Aangerekend', 'Eenheidsprijs', 'Btw-tarief',
            'Totaal excl. btw', 'Btw', 'Totaal incl. btw',
        ]];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                /** @var OrderItem $item */
                $rows[] = [
                    ...self::orderColumns($order),
                    $item->product_name,
                    $item->sku ?? '',
                    $item->unit->short(),
                    self::quantity($item->quantity),
                    $item->delivered_quantity === null ? '' : self::quantity($item->delivered_quantity),
                    self::quantity($item->billedQuantity()),
                    $item->unit_price === null ? '' : self::amount($item->unit_price),
                    DeliveryNotes::rate($item->vat_rate),
                    $item->line_total === null ? '' : self::amount($item->line_total),
                    $item->line_total === null ? '' : self::amount($item->vatAmount()),
                    $item->line_total === null ? '' : self::amount((float) $item->line_total + $item->vatAmount()),
                ];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private static function orderColumns(Order $order): array
    {
        return [
            $order->number,
            $order->requested_delivery_date?->format('d/m/Y') ?? '',
            $order->created_at->format('d/m/Y'),
            $order->status->getLabel(),
            $order->customer->name,
            $order->customer->vat_number ?? '',
        ];
    }

    private static function amount(float|string|null $amount): string
    {
        return number_format((float) $amount, 2, ',', '');
    }

    private static function quantity(float|string $quantity): string
    {
        return number_format((float) $quantity, 3, ',', '');
    }
}
