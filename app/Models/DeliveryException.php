<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A deviation from every schedule: a closure (holidays, no delivery for a
 * stretch of days) or an extra delivery day (Christmas Eve on a Wednesday).
 */
#[Fillable(['kind', 'starts_on', 'ends_on', 'cutoff_at', 'reason', 'translations'])]
class DeliveryException extends Model
{
    use HasTranslations;

    public const CLOSED = 'closed';

    public const EXTRA = 'extra';

    protected array $translatable = ['reason'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'cutoff_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // An extra delivery day is a single date; a closure without an end is a single day.
        static::saving(function (DeliveryException $exception) {
            if ($exception->kind === self::EXTRA || $exception->ends_on === null) {
                $exception->ends_on = $exception->starts_on;
            }

            if ($exception->kind === self::CLOSED) {
                $exception->cutoff_at = null;
            }
        });
    }

    public function isClosure(): bool
    {
        return $this->kind === self::CLOSED;
    }

    /** Anything touching the window from $from to $until. */
    public function scopeOverlapping(Builder $query, string $from, string $until): void
    {
        $query->whereDate('starts_on', '<=', $until)->whereDate('ends_on', '>=', $from);
    }
}
