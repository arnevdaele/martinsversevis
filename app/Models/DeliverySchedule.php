<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * When a customer can have their order delivered. Each weekday is either off
 * or on with an order deadline: "delivery on Tuesday, order before Monday 16:00"
 * is stored as {enabled: true, cutoff_days: 1, cutoff_time: "16:00"}.
 */
#[Fillable(['name', 'description', 'is_default', 'days', 'horizon_days', 'minimum_order_amount'])]
class DeliverySchedule extends Model
{
    protected $attributes = ['is_default' => false, 'horizon_days' => 21];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'days' => 'array',
            'horizon_days' => 'integer',
            'minimum_order_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Exactly one default: setting it here clears it everywhere else.
        static::saved(function (DeliverySchedule $schedule) {
            if ($schedule->is_default) {
                static::whereKeyNot($schedule->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function customerTypes(): HasMany
    {
        return $this->hasMany(CustomerType::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /** @return array<int, array{enabled: bool, cutoff_days: int, cutoff_time: string}> keyed by ISO weekday */
    public function days(): array
    {
        return self::normaliseDays($this->days ?? []);
    }

    /**
     * Also used on unsaved form state, so the admin preview and the portal
     * read the rules the same way.
     *
     * @return array<int, array{enabled: bool, cutoff_days: int, cutoff_time: string}>
     */
    public static function normaliseDays(array $days): array
    {
        $normalised = [];

        foreach (range(1, 7) as $weekday) {
            $day = $days[$weekday] ?? $days[(string) $weekday] ?? [];
            $normalised[$weekday] = [
                'enabled' => (bool) ($day['enabled'] ?? false),
                'cutoff_days' => max(0, min(6, (int) ($day['cutoff_days'] ?? 1))),
                'cutoff_time' => preg_match('/^\d{2}:\d{2}/', (string) ($day['cutoff_time'] ?? '')) ? substr($day['cutoff_time'], 0, 5) : '16:00',
            ];
        }

        return $normalised;
    }

    /** @return array<int, array{enabled: bool, cutoff_days: int, cutoff_time: string}> */
    public static function blankDays(array $enabled = [2, 3, 4, 5, 6]): array
    {
        return collect(range(1, 7))
            ->mapWithKeys(fn (int $weekday) => [$weekday => [
                'enabled' => in_array($weekday, $enabled, true),
                'cutoff_days' => 1,
                'cutoff_time' => '16:00',
            ]])
            ->all();
    }
}
