<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Support\Facades\Storage;

/**
 * What a customer may order, flattened for the portal: one row per price list
 * item, so a product that sits in two of their lists shows up in both.
 */
final class PortalCatalogue
{
    /** @return array{lists: list<array>, items: list<array>} */
    public static function for(Customer $customer): array
    {
        $lists = $customer->visiblePriceLists()->get();

        $items = PriceListItem::query()
            ->whereIn('price_list_id', $lists->pluck('id'))
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product.category')
            ->get()
            ->sortBy(fn (PriceListItem $item) => [
                $item->product->category?->sort_order ?? PHP_INT_MAX,
                $item->sort_order,
                $item->product->name,
            ])
            ->values();

        return [
            'lists' => $lists->map(fn (PriceList $list) => [
                'id' => $list->id,
                'name' => $list->t('name'),
                'description' => $list->t('description'),
            ])->all(),
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
