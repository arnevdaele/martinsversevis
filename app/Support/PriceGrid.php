<?php

namespace App\Support;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One product's price in one list, typed as a single value — the same way in
 * the price grid, the product form and anywhere else a cell is edited:
 *
 *   ""          not in this list
 *   "24,50"     this price (comma or point)
 *   "d"         in the list at day price ("dagprijs")
 */
final class PriceGrid
{
    public const DAY_PRICE_INPUTS = ['d', 'dag', 'dagprijs'];

    /** Validation rule for a cell. */
    public const RULE = 'regex:/^\s*(d|dag|dagprijs|\d{1,6}([.,]\d{1,2})?)\s*$/i';

    /**
     * @return array{listed: bool, price: ?float}
     */
    public static function parse(?string $input): array
    {
        $input = mb_strtolower(trim((string) $input));

        if ($input === '') {
            return ['listed' => false, 'price' => null];
        }

        if (in_array($input, self::DAY_PRICE_INPUTS, true)) {
            return ['listed' => true, 'price' => null];
        }

        if (! preg_match('/^\d{1,6}([.,]\d{1,2})?$/', $input)) {
            throw new InvalidArgumentException("Not a price: {$input}");
        }

        return ['listed' => true, 'price' => round((float) str_replace(',', '.', $input), 2)];
    }

    /** What a cell shows: "24,50", "dagprijs" or "" (not listed). */
    public static function display(?PriceListItem $item): string
    {
        if (! $item) {
            return '';
        }

        return $item->price === null ? 'dagprijs' : number_format((float) $item->price, 2, ',', '');
    }

    /** Apply one cell. New items go to the end of the list. */
    public static function set(Product $product, PriceList $list, ?string $input): void
    {
        $value = self::parse($input);

        $item = PriceListItem::where('price_list_id', $list->id)->where('product_id', $product->id)->first();

        if (! $value['listed']) {
            $item?->delete();

            return;
        }

        if ($item) {
            $item->update(['price' => $value['price']]);

            return;
        }

        $list->items()->create([
            'product_id' => $product->id,
            'price' => $value['price'],
            'sort_order' => (int) $list->items()->max('sort_order') + 1,
        ]);
    }

    /**
     * Fill one list from another: price × (1 + percentage/100), rounded up to
     * a nice step. Day prices stay day prices.
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
            $sort = (int) $to->items()->max('sort_order');
            $written = 0;

            foreach ($from->items()->get() as $source) {
                $price = $source->price === null ? null : self::round((float) $source->price * (1 + $percentage / 100), $step);
                $target = $existing->get($source->product_id);

                if ($target && ! $overwrite) {
                    continue;
                }

                if ($target) {
                    $target->update(['price' => $price]);
                } else {
                    $to->items()->create(['product_id' => $source->product_id, 'price' => $price, 'sort_order' => ++$sort]);
                }

                $written++;
            }

            return $written;
        });
    }

    /** Round up to the step (0.10 → € 24,53 becomes € 24,60); 0 = cents. */
    public static function round(float $price, float $step): float
    {
        if ($step <= 0) {
            return round($price, 2);
        }

        return round(ceil(round($price / $step, 6)) * $step, 2);
    }
}
