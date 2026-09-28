<?php

namespace App\Models;

use App\Support\OrderHistory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line in an order's history: who did what, and what changed. See {@see OrderHistory}. */
#[Fillable(['order_id', 'event', 'user_id', 'customer_user_id', 'actor_name', 'details'])]
class OrderEvent extends Model
{
    public const PLACED = 'placed';

    public const CHANGED = 'changed';

    public const CANCELLED = 'cancelled';

    public const STATUS = 'status';

    public const LINES = 'lines';

    public const EDITED = 'edited';

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function byCustomer(): bool
    {
        return in_array($this->event, [self::PLACED, self::CHANGED, self::CANCELLED], true);
    }

    /** Staff-facing title, in the admin's language. */
    public function title(): string
    {
        return match ($this->event) {
            self::PLACED => 'Bestelling geplaatst',
            self::CHANGED => 'Gewijzigd door de klant',
            self::CANCELLED => 'Geannuleerd door de klant',
            self::STATUS => 'Status gewijzigd',
            self::LINES => 'Producten aangepast',
            self::EDITED => 'Bestelling bewerkt',
            default => $this->event,
        };
    }

    /** @return list<string> */
    public function lines(): array
    {
        return array_map(OrderHistory::describe(...), $this->details ?? []);
    }
}
