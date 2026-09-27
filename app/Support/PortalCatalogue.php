<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\PriceListItem;
use Illuminate\Support\Facades\Storage;

/**
 * What a customer may order, flattened for the portal: one row per product,
 * at the price {@see CustomerPrices} settles on.
 */
final class PortalCatalogue
{
    /** @return array{hasLists: bool, items: list<array>} */
    public static function for(Customer $customer): array
    {
        $items = CustomerPrices::for($customer)
            ->sortBy(fn (PriceListItem $item) => [
                $item->product->category?->sort_order ?? PHP_INT_MAX,
                $item->product->sort_order,
                $item->product->name,
            ])
            ->values();

        return [
            'hasLists' => $customer->visiblePriceLists()->exists(),
            'items' => $items->map(fn (PriceListItem $item) => self::item($item))->all(),
        ];
    }

    private static function item(PriceListItem $item): array
    {
        $product = $item->product;

        return [
            'id' => $item->id,
            'priceListId' => $item->price_list_id,
            'productId' => $item->product_id,
            'name' => $product->t('name'),
            // Search matches the Dutch name too: staff and customers may use either on the phone.
            'search' => mb_strtolower(implode(' ', array_filter([$product->t('name'), $product->name, $product->sku, $product->origin]))),
            'sku' => $product->sku,
            'description' => $product->t('description'),
            'origin' => $product->origin,
            'image' => $product->image ? Storage::disk('public')->url($product->image) : null,
            'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->t('name')] : null,
            'unit' => $product->unit->short(),
            'allowsDecimals' => $product->unit->allowsDecimals(),
            'price' => $item->price === null ? null : (float) $item->price,
            'priceLabel' => $item->price === null ? null : Money::format($item->price),
            'vatRate' => (float) $product->vat_rate,
            'minQuantity' => $item->min_quantity === null ? null : (float) $item->min_quantity,
            'note' => $item->t('note'),
        ];
    }
}
