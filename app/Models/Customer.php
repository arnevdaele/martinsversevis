<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'customer_type_id', 'delivery_schedule_id', 'locale', 'name', 'contact_name', 'email', 'phone', 'vat_number',
    'street', 'postal_code', 'city', 'country', 'delivery_instructions', 'internal_notes', 'is_active',
])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /** Mirrors the column defaults, so a fresh instance behaves like a stored one. */
    protected $attributes = ['is_active' => true, 'country' => 'BE', 'locale' => 'nl'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class, 'customer_type_id');
    }

    /** Overrides the type's schedule for this one customer. */
    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(CustomerUser::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Lists granted to this customer on top of the ones their type sees. */
    public function extraPriceLists(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class);
    }

    /**
     * Every list this customer may order from right now: active, in its
     * validity window, and granted either through the type or directly.
     *
     * @return Builder<PriceList>
     */
    public function visiblePriceLists(): Builder
    {
        return PriceList::query()
            ->currentlyValid()
            ->where(fn (Builder $query) => $query
                ->whereHas('customerTypes', fn (Builder $q) => $q->whereKey($this->customer_type_id))
                ->orWhereHas('customers', fn (Builder $q) => $q->whereKey($this->getKey())))
            ->orderBy('name');
    }

    /** Only the customers of types the given staff member is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->visibleCustomerTypeIds();

        if ($ids !== null) {
            $query->whereIn('customer_type_id', $ids);
        }
    }

    public function address(): string
    {
        return collect([$this->street, trim("{$this->postal_code} {$this->city}")])
            ->filter()
            ->implode(', ');
    }
}
