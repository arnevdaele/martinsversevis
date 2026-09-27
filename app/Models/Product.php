<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use App\Enums\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_category_id', 'name', 'slug', 'sku', 'description', 'origin', 'unit', 'vat_rate', 'image', 'translations', 'is_active', 'sort_order'])]
class Product extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['name', 'description'];

    /** Mirrors the column defaults, so a fresh instance behaves like a stored one. */
    protected $attributes = ['is_active' => true, 'unit' => 'kg', 'vat_rate' => 6, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }
}
