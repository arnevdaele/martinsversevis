<?php

namespace App\Models;

use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'number', 'customer_id', 'customer_user_id', 'status', 'requested_delivery_date',
    'customer_note', 'internal_note', 'subtotal', 'vat_total', 'total',
    'has_unpriced_items', 'handled_by', 'submitted_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'requested_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'has_unpriced_items' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(CustomerUser::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->visibleCustomerTypeIds();

        if ($ids !== null) {
            $query->whereHas('customer', fn (Builder $q) => $q->whereIn('customer_type_id', $ids));
        }
    }

    /**
     * "2026-000123": readable on the phone, sortable, and unique per year.
     * Not locked (Postgres refuses FOR UPDATE on an aggregate); the unique
     * index catches a race and {@see PlaceOrder} retries.
     */
    public static function nextNumber(): string
    {
        $year = now()->year;

        $last = DB::table('orders')
            ->where('number', 'like', "{$year}-%")
            ->max('number');

        $sequence = $last ? ((int) substr($last, 5)) + 1 : 1;

        return sprintf('%d-%06d', $year, $sequence);
    }

    /** Recompute totals from the items; unpriced (day price) lines count as unknown. */
    public function recalculate(): void
    {
        $this->loadMissing('items');

        $subtotal = 0.0;
        $vat = 0.0;

        foreach ($this->items as $item) {
            if ($item->line_total === null) {
                continue;
            }
            $subtotal += (float) $item->line_total;
            $vat += $item->vatAmount();
        }

        $this->forceFill([
            'subtotal' => round($subtotal, 2),
            'vat_total' => round($vat, 2),
            'total' => round($subtotal + $vat, 2),
            'has_unpriced_items' => $this->items->contains(fn (OrderItem $item) => $item->unit_price === null),
        ])->save();
    }

    /**
     * Base and VAT per rate, for delivery notes and the accounting export.
     * Built from the same per-line rounding as {@see recalculate()}, so it adds up to the totals.
     *
     * @return array<string, array{base: float, vat: float}> keyed by rate, e.g. "6.00", lowest first
     */
    public function vatBreakdown(): array
    {
        $this->loadMissing('items');

        $rates = [];

        foreach ($this->items as $item) {
            if ($item->line_total === null) {
                continue;
            }
            $rate = number_format((float) $item->vat_rate, 2, '.', '');
            $rates[$rate]['base'] = round(($rates[$rate]['base'] ?? 0) + (float) $item->line_total, 2);
            $rates[$rate]['vat'] = round(($rates[$rate]['vat'] ?? 0) + $item->vatAmount(), 2);
        }

        ksort($rates, SORT_NUMERIC);

        return $rates;
    }
}
