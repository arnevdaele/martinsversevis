<?php

namespace Tests\Feature;

use App\Actions\ChangeOrderStatus;
use App\Actions\PlaceOrder;
use App\Actions\WeighOrderItem;
use App\Enums\OrderStatus;
use App\Filament\Pages\PickingList;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderStatusChanged;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\CompanyDetails;
use App\Support\OrderExport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryNotesAndExportTest extends TestCase
{
    private CustomerUser $user;

    private PriceListItem $cod;

    private PriceListItem $wine;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        CompanyDetails::save(['name' => 'Martins Verse Vis', 'address' => ['Vismijn 1', '8380 Zeebrugge'], 'vat_number' => 'BE0123.456.789']);

        $this->user = CustomerUser::factory()->for(Customer::factory()->state(['name' => 'De Haven', 'vat_number' => 'BE0999.888.777']))->create();
        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($this->user->customer->customer_type_id);
        $this->cod = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Kabeljauw', 'unit' => 'kg', 'vat_rate' => 6])->id, 'price' => 21.90]);
        $this->wine = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Witte wijn', 'unit' => 'piece', 'vat_rate' => 21])->id, 'price' => 10]);
    }

    private function order(?string $date = null): Order
    {
        $order = app(PlaceOrder::class)->handle($this->user, [$this->cod->id => 2, $this->wine->id => 3], $date ?? today()->addDays(2)->toDateString());
        app(WeighOrderItem::class)->handle($order->items()->where('product_id', $this->cod->product_id)->first(), '2,15', $this->superAdmin());

        return $order->fresh();
    }

    public function test_a_delivery_note_shows_what_went_out_with_vat_per_rate(): void
    {
        $order = $this->order();

        $this->assertSame(['6.00' => ['base' => 47.09, 'vat' => 2.83], '21.00' => ['base' => 30.0, 'vat' => 6.3]], $order->vatBreakdown());

        $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.delivery-notes.order', $order))
            ->assertOk()
            ->assertSeeInOrder(['Martins Verse Vis', 'Vismijn 1', 'BE0123.456.789', 'Leveringsbon', $order->number, 'De Haven', 'BE0999.888.777'])
            ->assertSeeInOrder(['Kabeljauw', '2 kg', '2,15 kg', '€ 21,90 / kg', '6%', '€ 47,09'])
            ->assertSeeInOrder(['Maatstaf 6%', '€ 47,09', 'Btw 6%', '€ 2,83', 'Maatstaf 21%', '€ 30,00', 'Btw 21%', '€ 6,30'])
            ->assertSeeInOrder(['Totaal incl. btw', '€ 86,22']);
    }

    public function test_staff_only_get_delivery_notes_for_orders_they_may_see(): void
    {
        $order = $this->order();
        $other = $this->staffWith(['orders.view']);
        $other->customerTypes()->attach(CustomerType::factory()->create());

        $this->actingAs($this->staffWith(['customers.view']))
            ->get(route('filament.admin.delivery-notes.order', $order))
            ->assertForbidden();
        $this->get(route('filament.admin.pages.bedrijfsgegevens'))->assertForbidden();
        $this->actingAs($this->staffWith(['settings.update']))->get(route('filament.admin.pages.bedrijfsgegevens'))->assertOk();
        $this->actingAs($other)
            ->get(route('filament.admin.delivery-notes.order', $order))
            ->assertForbidden();
    }

    public function test_a_day_of_delivery_notes_each_in_the_customers_language(): void
    {
        $date = today()->addDays(2)->toDateString();
        $dutch = $this->order($date);
        $dutchUser = $this->user;
        $this->user = CustomerUser::factory()->for(Customer::factory()->state(['customer_type_id' => $dutchUser->customer->customer_type_id, 'locale' => 'fr']))->create();
        $french = $this->order($date);
        $cancelled = $this->order($date);
        $cancelled->update(['status' => OrderStatus::Cancelled]);

        $html = $this->actingAs($this->superAdmin())
            ->get(route('filament.admin.delivery-notes.day', ['date' => $date]))
            ->assertOk()
            ->assertDontSee($cancelled->number)
            ->getContent();

        $sheets = collect(explode('<section class="sheet">', $html))->skip(1);
        $this->assertCount(2, $sheets);
        $sheet = fn (Order $order) => $sheets->first(fn (string $sheet) => str_contains($sheet, $order->number));
        $this->assertStringContainsString('Totaal incl. btw', $sheet($dutch));
        $this->assertStringContainsString('Bon de livraison', $sheet($french));
        $this->assertStringContainsString('Date de livraison :', $sheet($french));

        Livewire::test(PickingList::class, ['date' => $date])->assertActionVisible('delivery-notes');
        Livewire::test(ViewOrder::class, ['record' => $dutch->getRouteKey()])->assertActionVisible('delivery-note');
    }

    public function test_customers_get_their_delivery_note_once_the_order_is_delivered(): void
    {
        $order = $this->order();
        $url = route('portal.orders.delivery-note', $order);

        $this->actingAs($this->user, 'customer')->get($url)->assertNotFound();
        $this->actingAs($this->user, 'customer')
            ->get(route('portal.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('deliveryNoteUrl', null));

        $order->update(['status' => OrderStatus::Delivered]);

        $this->actingAs($this->user, 'customer')
            ->get(route('portal.orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('deliveryNoteUrl', $url));
        $this->actingAs($this->user, 'customer')->get($url)->assertOk()->assertSee('2,15 kg');

        $stranger = CustomerUser::factory()->for(Customer::factory())->create();
        $this->actingAs($stranger, 'customer')->get($url)->assertNotFound();
    }

    public function test_marking_an_order_delivered_can_mail_the_weighed_lines(): void
    {
        $order = $this->order();

        $this->assertTrue(ChangeOrderStatus::canNotify($order, OrderStatus::Delivered));
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Delivered, $this->superAdmin(), notify: true);

        Mail::assertQueued(OrderStatusChanged::class, function (OrderStatusChanged $mail) {
            $html = $mail->render();

            return $mail->hasTo($this->user->email)
                && str_contains($html, 'Je bestelling is geleverd')
                && str_contains($html, '2,15 kg (besteld: 2 kg)')
                && str_contains($html, '€ 86,22');
        });
    }

    public function test_the_export_per_order_splits_vat_per_rate(): void
    {
        $inPeriod = $this->order('2031-03-10');
        $inPeriod->update(['status' => OrderStatus::Delivered]);
        $this->order('2031-04-02')->update(['status' => OrderStatus::Delivered]);
        $this->order('2031-03-12')->update(['status' => OrderStatus::Cancelled]);
        $open = $this->order('2031-03-11');

        $orders = OrderExport::orders($this->superAdmin(), CarbonImmutable::parse('2031-03-01'), CarbonImmutable::parse('2031-03-31'), [OrderStatus::Delivered]);
        $this->assertSame([$inPeriod->id], $orders->modelKeys());

        $rows = OrderExport::rows($orders, OrderExport::PER_ORDER);
        $this->assertSame(['Maatstaf 6%', 'Btw 6%', 'Maatstaf 21%', 'Btw 21%', 'Totaal excl. btw', 'Btw', 'Totaal incl. btw', 'Nog te prijzen'], array_slice($rows[0], 10));
        $this->assertSame([$inPeriod->number, '10/03/2031'], array_slice($rows[1], 0, 2));
        $this->assertSame(['De Haven', 'BE0999.888.777'], array_slice($rows[1], 4, 2));
        $this->assertSame(['47,09', '2,83', '30,00', '6,30', '77,09', '9,13', '86,22', 'nee'], array_slice($rows[1], 10));

        $both = OrderExport::orders($this->superAdmin(), CarbonImmutable::parse('2031-03-01'), CarbonImmutable::parse('2031-03-31'), [OrderStatus::Delivered, OrderStatus::New]);
        $this->assertSame([$inPeriod->id, $open->id], $both->modelKeys());
    }

    public function test_the_export_per_line_bills_the_weighed_quantity(): void
    {
        $order = $this->order('2031-03-10');
        $orders = OrderExport::orders($this->superAdmin(), CarbonImmutable::parse('2031-03-10'), CarbonImmutable::parse('2031-03-10'), [OrderStatus::New]);

        $rows = OrderExport::rows($orders, OrderExport::PER_LINE);
        $cod = collect($rows)->firstWhere(6, 'Kabeljauw');
        $this->assertSame(['kg', '2,000', '2,150', '2,150', '21,90', '6%', '47,09', '2,83', '49,92'], array_slice($cod, 8));
        $this->assertSame($order->number, $cod[0]);

        $csv = OrderExport::csv([['Klant', 'Bedrag'], ['=HYPERLINK("x")', '1,50']]);
        $this->assertSame("\u{FEFF}Klant;Bedrag\n\"'=HYPERLINK(\"\"x\"\")\";1,50\n", $csv);
    }

    public function test_the_export_only_contains_what_the_user_may_see(): void
    {
        $this->order('2031-03-10');
        $restricted = $this->staffWith(['orders.view', 'orders.export']);
        $restricted->customerTypes()->attach(CustomerType::factory()->create());

        $this->assertCount(0, OrderExport::orders($restricted, CarbonImmutable::parse('2031-03-01'), CarbonImmutable::parse('2031-03-31'), [OrderStatus::New]));
    }

    public function test_the_export_action_downloads_a_csv_for_those_allowed(): void
    {
        $this->order('2031-03-10');

        $this->actingAs($this->staffWith(['orders.view']));
        Livewire::test(ListOrders::class)->assertActionHidden('export');

        $this->actingAs($this->staffWith(['orders.view', 'orders.export']));
        Livewire::test(ListOrders::class)
            ->assertActionVisible('export')
            ->callAction('export', data: ['from' => '2031-03-01', 'until' => '2031-03-31', 'statuses' => ['new'], 'layout' => OrderExport::PER_LINE])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('bestellingen-lijnen-2031-03-01-2031-03-31.csv');

        Livewire::test(ListOrders::class)
            ->callAction('export', data: ['from' => '2031-03-01', 'until' => '2031-03-31', 'statuses' => ['delivered'], 'layout' => OrderExport::PER_ORDER])
            ->assertNotified('Geen bestellingen in die periode.')
            ->assertNoFileDownloaded();
    }
}
