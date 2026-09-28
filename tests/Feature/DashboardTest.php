<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\OrdersNeedingAttention;
use App\Filament\Widgets\OrdersOverview;
use App\Filament\Widgets\RegularCustomersMissing;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Order;
use App\Support\RegularCustomers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    private CustomerType $horeca;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 8 October 2031, 11:00.
        CarbonImmutable::setTestNow('2031-10-08 11:00:00');
        Carbon::setTestNow('2031-10-08 11:00:00');
        $this->horeca = CustomerType::factory()->create();
    }

    private function order(Customer $customer, string $submitted, array $attributes = []): Order
    {
        static $number = 0;

        return Order::create($attributes + [
            'number' => sprintf('2031-%06d', ++$number),
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed,
            'submitted_at' => $submitted,
        ]);
    }

    public function test_the_dashboard_shows_the_days_and_what_needs_handling(): void
    {
        $customer = Customer::factory()->for($this->horeca, 'type')->create(['name' => 'Zeezicht']);
        $this->order($customer, '2031-10-07 09:00', ['requested_delivery_date' => '2031-10-08']);
        $this->order($customer, '2031-10-07 10:00', ['requested_delivery_date' => '2031-10-09', 'status' => OrderStatus::New]);
        $unpriced = $this->order($customer, '2031-10-06 10:00', ['requested_delivery_date' => '2031-10-10', 'has_unpriced_items' => true]);
        $this->order($customer, '2031-10-06 11:00', ['requested_delivery_date' => '2031-10-09', 'status' => OrderStatus::Cancelled]);
        $this->actingAs($this->superAdmin());

        $this->get('/admin')->assertOk()->assertSeeLivewire(OrdersNeedingAttention::class)->assertSeeLivewire(RegularCustomersMissing::class);

        Livewire::test(OrdersOverview::class)
            ->assertSeeInOrder(['Levering vandaag', '1', 'Levering morgen', '1', '1 nog niet bevestigd', 'Te bevestigen', '1', 'Dagprijzen in te vullen', '1']);

        Livewire::test(OrdersNeedingAttention::class)
            ->assertCanSeeTableRecords(Order::where('status', OrderStatus::New)->get()->push($unpriced), inOrder: true)
            ->assertCountTableRecords(2);
    }

    public function test_it_finds_regulars_who_are_late_this_week(): void
    {
        $late = Customer::factory()->for($this->horeca, 'type')->create(['name' => 'Laat', 'phone' => '+32 470 12 34 56']);
        $done = Customer::factory()->for($this->horeca, 'type')->create(['name' => 'Al besteld']);
        $friday = Customer::factory()->for($this->horeca, 'type')->create(['name' => 'Vrijdagklant']);
        $rare = Customer::factory()->for($this->horeca, 'type')->create(['name' => 'Af en toe']);

        foreach (range(1, 6) as $weeksAgo) {
            $monday = CarbonImmutable::parse('2031-10-06')->subWeeks($weeksAgo);
            $this->order($late, $monday->setTime(9, 0));
            $this->order($done, $monday->addDay()->setTime(8, 0));
            $this->order($friday, $monday->addDays(4)->setTime(8, 0));
        }
        $this->order($done, '2031-10-07 08:00');
        $this->order($rare, '2031-09-29 08:00');
        $this->order($rare, '2031-09-22 08:00');

        $missing = RegularCustomers::missing($this->superAdmin());

        $this->assertSame(['Laat'], $missing->map(fn ($row) => $row['customer']->name)->all());
        $this->assertSame(6, $missing[0]['weeks']);
        $this->assertSame('2031-10-06 09:00', $missing[0]['usually']->format('Y-m-d H:i'));

        $this->actingAs($this->superAdmin());
        Livewire::test(RegularCustomersMissing::class)->assertSee('Laat')->assertSee('tel:+32470123456', false)->assertDontSee('Vrijdagklant');
    }

    public function test_staff_only_see_their_customer_types(): void
    {
        $other = Customer::factory()->for(CustomerType::factory(), 'type')->create();
        $this->order($other, '2031-10-07 09:00', ['status' => OrderStatus::New]);
        foreach (range(1, 5) as $weeksAgo) {
            $this->order($other, CarbonImmutable::parse('2031-10-06 08:00')->subWeeks($weeksAgo));
        }

        $staff = $this->staffWith(['orders.view']);
        $staff->customerTypes()->attach($this->horeca);
        $this->actingAs($staff);

        Livewire::test(OrdersNeedingAttention::class)->assertCountTableRecords(0);
        $this->assertCount(0, RegularCustomers::missing($staff));
    }

    public function test_staff_without_orders_access_get_an_empty_dashboard(): void
    {
        $this->actingAs($this->staffWith(['customers.view']));

        $this->get('/admin')->assertOk()->assertDontSeeLivewire(OrdersOverview::class)->assertDontSeeLivewire(OrdersNeedingAttention::class);
    }
}
