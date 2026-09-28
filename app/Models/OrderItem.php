<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use App\Enums\Unit;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'price_list_id', 'product_name', 'sku', 'unit', 'unit_price', 'vat_rate', 'quantity', 'delivered_quantity', 'line_total', 'note', 'translations'])]
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
            'delivered_quantity' => 'decimal:3',
            'line_total' => 'decimal:2',
        ];
    }

    /** The VAT on this line, rounded per line like the order totals. Zero while unpriced. */
    public function vatAmount(): float
    {
        return $this->line_total === null ? 0.0 : Money::cents((float) $this->line_total * (float) $this->vat_rate / 100);
    }

    protected static function booted(): void
    {
        // Staff may fill in a day price or the weighed quantity afterwards; the line follows.
        static::saving(function (OrderItem $item) {
            $item->line_total = $item->unit_price === null
                ? null
                : Money::cents((float) $item->unit_price * (float) $item->billedQuantity());
        });
    }

    /** What the customer pays for: the weighed quantity once known, else what they ordered. */
    public function billedQuantity(): string
    {
        return $this->delivered_quantity ?? $this->quantity;
    }

    /** Weighed, and not exactly what was ordered. */
    public function deliveredDiffers(): bool
    {
        return $this->delivered_quantity !== null && (float) $this->delivered_quantity !== (float) $this->quantity;
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
