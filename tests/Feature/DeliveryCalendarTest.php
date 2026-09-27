<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\DeliveryException;
use App\Models\DeliverySchedule;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\DeliveryCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryCalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Monday 6 October 2031, 10:00.
        CarbonImmutable::setTestNow('2031-10-06 10:00:00');
        Carbon::setTestNow('2031-10-06 10:00:00');
    }

    private function schedule(array $enabled = [2, 4], int $cutoffDays = 1, string $time = '16:00', array $attributes = []): DeliverySchedule
    {
        $days = collect(DeliverySchedule::blankDays($enabled))
            ->map(fn (array $day) => ['cutoff_days' => $cutoffDays, 'cutoff_time' => $time] + $day)
            ->all();

        return DeliverySchedule::create(['name' => 'Test', 'days' => $days] + $attributes);
    }

    private function dates(DeliveryCalendar $calendar, int $take = 4): array
    {
        return array_map(fn (array $o) => $o['date']->toDateString(), array_slice($calendar->options(), 0, $take));
    }

    public function test_weekdays_and_deadlines(): void
    {
        $calendar = DeliveryCalendar::fromSchedule($this->schedule([2, 4]));

        // Tuesday 7 is open until Monday 16:00 — it is Monday 10:00.
        $this->assertSame(['2031-10-07', '2031-10-09', '2031-10-14', '2031-10-16'], $this->dates($calendar));
        $this->assertSame('2031-10-06 16:00', $calendar->options()[0]['cutoff']->format('Y-m-d H:i'));

        // After the deadline Tuesday drops off.
        CarbonImmutable::setTestNow('2031-10-06 16:00:00');
        $this->assertSame('2031-10-09', $this->dates($calendar, 1)[0]);
    }

    public function test_same_day_and_multi_day_deadlines(): void
    {
        $sameDay = DeliveryCalendar::fromSchedule($this->schedule([1], 0, '11:00'));
        $this->assertSame('2031-10-06', $this->dates($sameDay, 1)[0], 'Monday itself, until 11:00.');

        $twoDays = DeliveryCalendar::fromSchedule($this->schedule([3], 2, '12:00'));
        $this->assertSame('2031-10-08', $this->dates($twoDays, 1)[0], 'Wednesday, ordered by Monday 12:00.');
    }

    public function test_the_horizon_limits_how_far_ahead(): void
    {
        $calendar = DeliveryCalendar::fromSchedule($this->schedule([1, 2, 3, 4, 5, 6, 7], 0, '23:00', ['horizon_days' => 3]));

        $this->assertCount(4, $calendar->options());
    }

    public function test_closures_remove_days_and_extra_days_add_them(): void
    {
        $schedule = $this->schedule([2, 4]);
        DeliveryException::create(['kind' => DeliveryException::CLOSED, 'starts_on' => '2031-10-07', 'ends_on' => '2031-10-12', 'reason' => 'Verlof']);
        DeliveryException::create(['kind' => DeliveryException::EXTRA, 'starts_on' => '2031-10-11', 'cutoff_at' => '2031-10-10 12:00']);

        $calendar = DeliveryCalendar::fromSchedule($schedule);

        // Tuesday and Thursday are closed; the extra Saturday inside the closure still counts.
        $this->assertSame(['2031-10-11', '2031-10-14'], $this->dates($calendar, 2));
        $this->assertTrue($calendar->options()[0]['extra']);
        $this->assertSame('2031-10-10 12:00', $calendar->options()[0]['cutoff']->format('Y-m-d H:i'));
        $this->assertCount(1, $calendar->upcomingClosures());
    }

    public function test_the_schedule_comes_from_customer_then_type_then_default(): void
    {
        $default = $this->schedule([2], attributes: ['is_default' => true]);
        $typeSchedule = $this->schedule([3]);
        $own = $this->schedule([4]);

        $type = CustomerType::factory()->create(['delivery_schedule_id' => $typeSchedule->id]);
        $customer = Customer::factory()->for($type, 'type')->create();

        $this->assertTrue(DeliveryCalendar::for($customer)->schedule->is($typeSchedule));

        $customer->update(['delivery_schedule_id' => $own->id]);
        $this->assertTrue(DeliveryCalendar::for($customer->fresh())->schedule->is($own));

        $plain = Customer::factory()->create();
        $this->assertTrue(DeliveryCalendar::for($plain)->schedule->is($default));
    }

    public function test_without_any_schedule_there_are_no_rules(): void
    {
        $this->assertFalse(DeliveryCalendar::for(Customer::factory()->create())->hasRules());
    }

    public function test_only_one_default_schedule(): void
    {
        $first = $this->schedule(attributes: ['is_default' => true]);
        $second = $this->schedule(attributes: ['is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_placing_an_order_respects_the_calendar_and_the_minimum(): void
    {
        Mail::fake();
        $this->schedule([2, 4], attributes: ['is_default' => true, 'minimum_order_amount' => 50]);

        $user = CustomerUser::factory()->create();
        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($user->customer->customer_type_id);
        /** @var PriceListItem $item */
        $item = $list->items()->create(['product_id' => Product::factory()->create()->id, 'price' => 10]);

        $attempt = function (array $lines, ?string $date) use ($user) {
            try {
                app(PlaceOrder::class)->handle($user, $lines, $date);

                return null;
            } catch (ValidationException $e) {
                return array_key_first($e->errors());
            }
        };

        $this->assertSame('requested_delivery_date', $attempt([$item->id => 10], null), 'A date is required.');
        $this->assertSame('requested_delivery_date', $attempt([$item->id => 10], '2031-10-08'), 'Wednesday is not a delivery day.');
        $this->assertSame('lines', $attempt([$item->id => 2], '2031-10-07'), 'Below the € 50 minimum.');
        $this->assertNull($attempt([$item->id => 5], '2031-10-07'));
    }
}
