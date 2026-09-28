<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Filament\Pages\PickingList as PickingListPage;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\PickingList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class PickingListTest extends TestCase
{
    private CustomerType $horeca;

    private CustomerType $retail;

    private PriceListItem $cod;

    private PriceListItem $oysters;

    private CarbonImmutable $day;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->horeca = CustomerType::factory()->create();
        $this->retail = CustomerType::factory()->create();
        $this->day = CarbonImmutable::today()->addDays(3);

        $list = PriceList::factory()->create();
        $list->customerTypes()->attach([$this->horeca->id, $this->retail->id]);
        $fish = ProductCategory::create(['name' => 'Vis', 'slug' => 'vis', 'sort_order' => 1]);
        $shellfish = ProductCategory::create(['name' => 'Schaaldieren', 'slug' => 'schaaldieren', 'sort_order' => 2]);
        $this->cod = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Kabeljauw', 'product_category_id' => $fish->id])->id, 'price' => 20]);
        $this->oysters = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Oesters', 'unit' => 'piece', 'product_category_id' => $shellfish->id])->id, 'price' => 1.5]);
    }

    private function order(CustomerType $type, string $name, array $lines, ?string $date = null, array $notes = []): void
    {
        $user = CustomerUser::factory()->for(Customer::factory()->for($type, 'type')->create(['name' => $name]))->create();
        app(PlaceOrder::class)->handle($user, $lines, $date ?? $this->day->toDateString(), null, $notes);
    }

    public function test_it_adds_up_the_day_per_product_and_leaves_out_cancelled_orders(): void
    {
        $this->order($this->horeca, 'Zeezicht', [$this->cod->id => 2.5, $this->oysters->id => 24], notes: [$this->cod->id => 'zonder vel']);
        $this->order($this->retail, 'Bakkerij', [$this->cod->id => 1.25]);
        $this->order($this->horeca, 'Geannuleerd', [$this->cod->id => 10]);
        $this->order($this->horeca, 'Andere dag', [$this->cod->id => 7], $this->day->addDay()->toDateString());
        Order::whereHas('customer', fn ($query) => $query->where('name', 'Geannuleerd'))->update(['status' => 'cancelled']);

        $list = PickingList::for($this->day, $this->superAdmin());
        $totals = $list->totals();

        $this->assertSame(['Bakkerij', 'Zeezicht'], $list->orders->map->customer->pluck('name')->all());
        $this->assertSame(['Vis', 'Schaaldieren'], $totals->keys()->all());
        $this->assertSame(['name' => 'Kabeljauw', 'quantity' => '3,75 kg', 'orders' => 2, 'notes' => ['Zeezicht: zonder vel']], $totals['Vis'][0]);
        $this->assertSame('24 st.', $totals['Schaaldieren'][0]['quantity']);
        $this->assertSame(2, $list->unconfirmed());
    }

    public function test_staff_only_see_their_customer_types(): void
    {
        $this->order($this->horeca, 'Zeezicht', [$this->cod->id => 2]);
        $this->order($this->retail, 'Bakkerij', [$this->cod->id => 1]);

        $staff = $this->staffWith(['orders.view']);
        $staff->customerTypes()->attach($this->retail);

        $this->assertSame(['Bakkerij'], PickingList::for($this->day, $staff)->orders->map->customer->pluck('name')->all());

        $this->actingAs($staff)
            ->get('/admin/dagoverzicht/afdrukken?date='.$this->day->toDateString())
            ->assertOk()
            ->assertSee('Bakkerij')
            ->assertDontSee('Zeezicht')
            ->assertSee('1 kg');
    }

    public function test_the_page_opens_on_the_next_day_with_orders_and_can_step_between_days(): void
    {
        $this->order($this->horeca, 'Zeezicht', [$this->cod->id => 2]);
        $this->order($this->horeca, 'Later', [$this->oysters->id => 12], $this->day->addDays(2)->toDateString());
        $this->actingAs($this->superAdmin());

        Livewire::test(PickingListPage::class)
            ->assertSet('date', $this->day->toDateString())
            ->assertSee('Zeezicht')
            ->assertDontSee('Later')
            ->callAction('next')
            ->assertSet('date', $this->day->addDays(2)->toDateString())
            ->assertSee('12 st.')
            ->callAction('previous')
            ->assertSet('date', $this->day->toDateString());
    }

    public function test_only_staff_who_may_see_orders_get_in(): void
    {
        $this->actingAs($this->staffWith(['customers.view']));

        $this->get('/admin/dagoverzicht')->assertForbidden();
        $this->get('/admin/dagoverzicht/afdrukken?date=2026-01-01')->assertForbidden();
    }
}
