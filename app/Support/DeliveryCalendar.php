<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\DeliveryException;
use App\Models\DeliverySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Which delivery dates a customer can choose right now. One place decides
 * this — the portal's date picker, the server-side check in PlaceOrder and
 * the admin preview all ask here.
 *
 * Resolution: the customer's own schedule, else their type's, else the
 * default. No schedule at all means no rules: any date from today is fine.
 */
final class DeliveryCalendar
{
    /** @param Collection<int, DeliveryException> $exceptions */
    private function __construct(
        public readonly ?DeliverySchedule $schedule,
        private readonly array $days,
        private readonly int $horizonDays,
        private readonly Collection $exceptions,
    ) {}

    public static function for(Customer $customer): self
    {
        $customer->loadMissing('deliverySchedule', 'type.deliverySchedule');

        $schedule = $customer->deliverySchedule
            ?? $customer->type?->deliverySchedule
            ?? DeliverySchedule::default();

        return self::fromSchedule($schedule);
    }

    public static function fromSchedule(?DeliverySchedule $schedule): self
    {
        return self::fromRules(
            $schedule?->days() ?? [],
            $schedule?->horizon_days ?? 21,
            $schedule,
        );
    }

    /** From raw rules — the admin preview uses this on unsaved form state. */
    public static function fromRules(array $days, int $horizonDays, ?DeliverySchedule $schedule = null): self
    {
        $horizonDays = max(1, min(120, $horizonDays));
        $today = CarbonImmutable::today();

        $exceptions = DeliveryException::query()
            ->overlapping($today->toDateString(), $today->addDays($horizonDays)->toDateString())
            ->orderBy('starts_on')
            ->get();

        return new self($schedule, DeliverySchedule::normaliseDays($days), $horizonDays, $exceptions);
    }

    /** Without any schedule the customer picks freely (and may leave the date empty). */
    public function hasRules(): bool
    {
        return $this->schedule !== null || collect($this->days)->contains('enabled', true);
    }

    public function minimumOrderAmount(): ?float
    {
        return $this->schedule?->minimum_order_amount === null ? null : (float) $this->schedule->minimum_order_amount;
    }

    /**
     * @return list<array{date: CarbonImmutable, cutoff: CarbonImmutable, extra: bool, reason: ?string}>
     */
    public function options(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $options = [];

        for ($offset = 0; $offset <= $this->horizonDays; $offset++) {
            $date = $now->startOfDay()->addDays($offset);
            $option = $this->optionFor($date);

            if ($option && $now->lessThan($option['cutoff'])) {
                $options[] = $option;
            }
        }

        return $options;
    }

    public function allows(string $date, ?CarbonImmutable $now = null): bool
    {
        return collect($this->options($now))->contains(fn (array $option) => $option['date']->toDateString() === $date);
    }

    /** @return Collection<int, DeliveryException> closures customers should be told about */
    public function upcomingClosures(): Collection
    {
        return $this->exceptions->filter(fn (DeliveryException $e) => $e->isClosure())->values();
    }

    /** @return array{date: CarbonImmutable, cutoff: CarbonImmutable, extra: bool, reason: ?string}|null */
    private function optionFor(CarbonImmutable $date): ?array
    {
        $day = $date->toDateString();

        $extra = $this->exceptions->first(fn (DeliveryException $e) => ! $e->isClosure()
            && $e->starts_on->toDateString() === $day);

        if ($extra) {
            return [
                'date' => $date,
                // An extra day without its own deadline follows the usual one: the day before at 16:00.
                'cutoff' => $extra->cutoff_at?->toImmutable() ?? $date->subDay()->setTime(16, 0),
                'extra' => true,
                'reason' => $extra->t('reason'),
            ];
        }

        // An extra day is the more specific rule, so it wins over a closure around it.
        $closed = $this->exceptions->first(fn (DeliveryException $e) => $e->isClosure()
            && $e->starts_on->toDateString() <= $day && $e->ends_on->toDateString() >= $day);

        if ($closed) {
            return null;
        }

        $rule = $this->days[$date->isoWeekday()];

        if (! $rule['enabled']) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $rule['cutoff_time']));

        return [
            'date' => $date,
            'cutoff' => $date->subDays($rule['cutoff_days'])->setTime($hour, $minute),
            'extra' => false,
            'reason' => null,
        ];
    }
}
