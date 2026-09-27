<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'translations', 'is_active', 'valid_from', 'valid_until'])]
class PriceList extends Model
{
    use HasFactory, HasTranslations;

    protected array $translatable = ['name', 'description'];

    /** Mirrors the column defaults, so a fresh instance behaves like a stored one. */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class)->orderBy('sort_order');
    }

    public function customerTypes(): BelongsToMany
    {
        return $this->belongsToMany(CustomerType::class);
    }

    /** Customers granted this list individually. */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }

    public function scopeCurrentlyValid(Builder $query): void
    {
        $today = today()->toDateString();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today));
    }
}
