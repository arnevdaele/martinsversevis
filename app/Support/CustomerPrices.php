<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\PriceListItem;
use Illuminate\Support\Collection;

/**
 * The one price a customer pays per product. A customer can see several
 * lists (their type's, plus lists made for them specifically); they still
 * get each product once:
 *
 *  1. A list linked to the customer directly beats the lists of their type —
 *     that is how a single customer gets an exception price.
 *  2. Within the same level, the lowest fixed price wins; a day price only
 *     when there is no fixed price.
 *
 * The portal shows exactly these items and PlaceOrder only accepts them, so a
 * customer can never order at a price they were not shown.
 */
final class CustomerPrices
{
    /**
     * @param  int|null  $withoutList  leave one list out: "what would they pay without their own list?"
     * @return Collection<int, PriceListItem> keyed by product id
     */
    public static function for(Customer $customer, ?int $withoutList = null): Collection
    {
        $lists = $customer->visiblePriceLists()
            ->when($withoutList, fn ($query) => $query->whereKeyNot($withoutList))
            ->get(['price_lists.id']);
        $ownListIds = $customer->extraPriceLists()->pluck('price_lists.id')->all();

        return PriceListItem::query()
            ->whereIn('price_list_id', $lists->pluck('id'))
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->with('product.category')
            ->get()
            ->groupBy('product_id')
            ->map(fn (Collection $candidates) => $candidates
                ->sortBy(fn (PriceListItem $item) => [
                    in_array($item->price_list_id, $ownListIds, true) ? 0 : 1,
                    $item->price === null ? 1 : 0,
                    (float) $item->price,
                ])
                ->first());
    }
}
