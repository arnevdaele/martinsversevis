<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['price_list_id', 'product_id', 'price', 'min_quantity', 'note', 'translations', 'sort_order'])]
class PriceListItem extends Model
{
    use HasTranslations;

    protected array $translatable = ['note'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_quantity' => 'decimal:3',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** No price means "dagprijs": the customer can order, staff price it afterwards. */
    public function isDayPrice(): bool
    {
        return $this->price === null;
    }
}
