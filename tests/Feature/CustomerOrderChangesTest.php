<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Enums\OrderStatus;
use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\DeliverySchedule;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\OrderChanges;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerOrderChangesTest extends TestCase
{
    private CustomerUser $user;

    private PriceListItem $cod;

    private PriceListItem $turbot;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 6 October 2031, 10:00. Delivery on Tuesday, order before Monday 16:00.
        $this->at('2031-10-06 10:00:00');
        $days = collect(DeliverySchedule::blankDays([2, 4]))
            ->map(fn (array $day) => ['cutoff_days' => 1, 'cutoff_time' => '16:00'] + $day)
            ->all();
        DeliverySchedule::create(['name' => 'Standaard', 'days' => $days, 'is_default' => true]);

        $this->user = CustomerUser::factory()->for(Customer::factory())->create();
        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($this->user->customer->customer_type_id);
        $this->cod = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Kabeljauw'])->id, 'price' => 20]);
        $this->turbot = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Tarbot'])->id, 'price' => null]);
    }

    private function at(string $moment): void
    {
        CarbonImmutable::setTestNow($moment);
        Carbon::setTestNow($moment);
    }

    private function order(): Order
    {
        Mail::fake();
        $order = app(PlaceOrder::class)->handle($this->user, [$this->cod->id => 2, $this->turbot->id => 1], '2031-10-07', 'Achterdeur');
        Mail::fake();

        return $order;
    }

    public function test_the_customer_changes_an_order_before_the_deadline_and_staff_hear_about_it(): void
    {
        $order = $this->order();
        $staff = $this->staffWith(['orders.receive-notifications']);
        // Staff already priced the turbot; a change keeps that price.
        $order->items()->where('product_id', $this->turbot->product_id)->update(['unit_price' => 30]);

        $this->actingAs($this->user, 'customer')
            ->put("/portal/bestellingen/{$order->id}", [
                'lines' => [$this->cod->id => 3, $this->turbot->id => 2],
                'notes' => [$this->cod->id => 'gefileerd'],
                'requested_delivery_date' => '2031-10-09',
                'customer_note' => 'Voordeur',
            ])
            ->assertRedirect("/portal/bestellingen/{$order->id}")
            ->assertSessionHas('success');

        $order->refresh()->load('items');
        $this->assertSame('2031-10-09', $order->requested_delivery_date->toDateString());
        $this->assertSame('Voordeur', $order->customer_note);
        $this->assertSame(['3.000', '2.000'], $order->items->sortBy('product_name')->pluck('quantity')->values()->all());
        $this->assertEquals(60 + 60, (float) $order->subtotal);
        $this->assertSame('gefileerd', $order->items->firstWhere('product_id', $this->cod->product_id)->note);

        Mail::assertQueued(OrderReceived::class, function (OrderReceived $mail) use ($staff) {
            $html = $mail->render();

            return $mail->hasTo($staff->email) && $mail->event === OrderReceived::CHANGED
                && str_contains($mail->envelope()->subject, 'gewijzigd') && str_contains($html, 'De klant heeft deze bestelling aangepast');
        });
        Mail::assertQueued(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->changed
            && str_contains($mail->render(), 'Je wijziging is goed ontvangen'));
    }

    public function test_after_the_deadline_or_once_confirmed_the_order_is_locked(): void
    {
        $order = $this->order();
        $this->assertTrue(OrderChanges::allowed($order));
        $this->assertSame('2031-10-06 16:00', OrderChanges::deadline($order)->format('Y-m-d H:i'));

        $this->at('2031-10-06 16:00:00');
        $this->assertFalse(OrderChanges::allowed($order));

        $this->actingAs($this->user, 'customer')
            ->put("/portal/bestellingen/{$order->id}", ['lines' => [$this->cod->id => 5], 'requested_delivery_date' => '2031-10-09'])
            ->assertSessionHasErrors('order');
        $this->post("/portal/bestellingen/{$order->id}/annuleren")->assertSessionHasErrors('order');
        $this->get("/portal?edit={$order->id}")->assertRedirect("/portal/bestellingen/{$order->id}");

        $this->at('2031-10-06 10:00:00');
        $order->update(['status' => OrderStatus::Confirmed]);
        $this->assertFalse(OrderChanges::allowed($order));
        Mail::assertNothingQueued();
    }

    public function test_the_customer_cancels_and_staff_get_a_mail_without_lines(): void
    {
        $order = $this->order();
        $staff = $this->staffWith(['orders.receive-notifications']);

        $this->actingAs($this->user, 'customer')
            ->post("/portal/bestellingen/{$order->id}/annuleren")
            ->assertRedirect("/portal/bestellingen/{$order->id}");

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        Mail::assertQueued(OrderReceived::class, function (OrderReceived $mail) use ($staff) {
            $html = $mail->render();

            return $mail->hasTo($staff->email) && $mail->event === OrderReceived::CANCELLED
                && str_contains($html, 'zelf geannuleerd') && ! str_contains($html, 'Kabeljauw');
        });
        Mail::assertNotQueued(OrderConfirmation::class);
    }

    public function test_the_form_opens_with_the_order_and_the_order_page_says_until_when(): void
    {
        $order = $this->order();

        $this->actingAs($this->user, 'customer')
            ->get("/portal?edit={$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('editing.number', $order->number)
                ->where('editing.deliveryDate', '2031-10-07')
                ->where('editing.customerNote', 'Achterdeur')
                ->where("editing.lines.{$this->cod->id}", 2)
                ->where('editing.until', 'Wijzigen kan tot maandag 6 oktober 16:00.')
                ->where('reorder', null));

        $this->get("/portal/bestellingen/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.changeable', true)
                ->where('changeUntil', 'Wijzigen of annuleren kan tot maandag 6 oktober 16:00.'));
    }

    public function test_nobody_changes_another_customers_order(): void
    {
        $order = $this->order();
        $other = CustomerUser::factory()->create();

        $this->actingAs($other, 'customer')
            ->put("/portal/bestellingen/{$order->id}", ['lines' => [$this->cod->id => 5]])
            ->assertNotFound();
        $this->post("/portal/bestellingen/{$order->id}/annuleren")->assertNotFound();
        $this->get("/portal?edit={$order->id}")->assertNotFound();

        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }
}
