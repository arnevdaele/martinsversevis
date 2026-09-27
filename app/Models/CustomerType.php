<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_system', 'notification_emails', 'delivery_schedule_id', 'sort_order'])]
class CustomerType extends Model
{
    use HasFactory;

    /** Mirrors the column defaults, so a fresh instance behaves like a stored one. */
    protected $attributes = ['is_system' => false, 'sort_order' => 0];

    public const BUSINESS = 'business';

    public const PRIVATE = 'private';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'notification_emails' => 'array',
        ];
    }

    public function deliverySchedule(): BelongsTo
    {
        return $this->belongsTo(DeliverySchedule::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function priceLists(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class);
    }

    /** Staff limited to this type. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
