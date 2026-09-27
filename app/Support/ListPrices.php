<?php

namespace App\Support;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Writing prices into a list. A product is in a list when it has a price
 * or is marked as day price; clearing both takes it out. Used by the price
 * list page, the product form and the "start from another list" option.
 */
final class ListPrices
{
    public static function item(Product $product, PriceList $list): ?PriceListItem
    {
        return PriceListItem::where('price_list_id', $list->id)->where('product_id', $product->id)->first();
    }

    public static function set(Product $product, PriceList $list, ?float $price, bool $dayPrice): ?PriceListItem
    {
        $item = self::item($product, $list);

        if ($price === null && ! $dayPrice) {
            $item?->delete();

            return null;
        }

        // A day price has no fixed amount; ticking it clears the price.
        $price = $dayPrice ? null : round($price, 2);

        if ($item) {
            $item->update(['price' => $price]);

            return $item;
        }

        return $list->items()->create(['product_id' => $product->id, 'price' => $price]);
    }

    /** "24,50", "24.5", "" → float|null. */
    public static function parse(mixed $input): ?float
    {
        $input = trim((string) $input);

        return $input === '' ? null : round((float) str_replace(',', '.', $input), 2);
    }

    /**
     * Fill one list from another: price × (1 + percentage/100), rounded up
     * to a nice step. Day prices stay day prices.
     *
     * @return int number of prices written
     */
    public static function copy(PriceList $from, PriceList $to, float $percentage, float $step, bool $overwrite): int
    {
        if ($from->is($to)) {
            return 0;
        }

        return DB::transaction(function () use ($from, $to, $percentage, $step, $overwrite) {
            $existing = $to->items()->get()->keyBy('product_id');
            $written = 0;

            foreach ($from->items()->get() as $source) {
                $target = $existing->get($source->product_id);

                if ($target && ! $overwrite) {
                    continue;
                }

                $price = $source->price === null ? null : self::round((float) $source->price * (1 + $percentage / 100), $step);

                $target
                    ? $target->update(['price' => $price])
                    : $to->items()->create(['product_id' => $source->product_id, 'price' => $price, 'min_quantity' => $source->min_quantity]);

                $written++;
            }

            return $written;
        });
    }

    /** Raise or lower every fixed price in a list. @return int prices changed */
    public static function adjust(PriceList $list, float $percentage, float $step): int
    {
        return DB::transaction(function () use ($list, $percentage, $step) {
            $items = $list->items()->whereNotNull('price')->get();

            foreach ($items as $item) {
                $item->update(['price' => self::round((float) $item->price * (1 + $percentage / 100), $step)]);
            }

            return $items->count();
        });
    }

    /** Round up to the step (0.10 → € 24,53 becomes € 24,60); 0 = to the cent. */
    public static function round(float $price, float $step): float
    {
        if ($step <= 0) {
            return round($price, 2);
        }

        return round(ceil(round($price / $step, 6)) * $step, 2);
    }

    /** @return array<string, string> rounding choices for forms */
    public static function steps(): array
    {
        return ['0' => 'Op de cent', '0.05' => 'Naar boven op 5 cent', '0.10' => 'Naar boven op 10 cent', '0.50' => 'Naar boven op 50 cent', '1' => 'Naar boven op de euro'];
    }
}
