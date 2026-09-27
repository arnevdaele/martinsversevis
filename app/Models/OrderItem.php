<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use App\Enums\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'price_list_id', 'product_name', 'sku', 'unit', 'unit_price', 'vat_rate', 'quantity', 'line_total', 'note', 'translations'])]
class OrderItem extends Model
{
    use HasTranslations;

    /** Snapshot of the product name per language; staff read the base column. */
    protected array $translatable = ['product_name'];

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'unit_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'quantity' => 'decimal:3',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Staff may fill in a day price afterwards; the line follows.
        static::saving(function (OrderItem $item) {
            $item->line_total = $item->unit_price === null
                ? null
                : round((float) $item->unit_price * (float) $item->quantity, 2);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
