<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Actions\WeighOrderItem;
use App\Enums\OrderStatus;
use App\Filament\Pages\PickingList;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Orders\Widgets\OrderHistory as OrderHistoryWidget;
use App\Mail\OrderReceived;
use App\Mail\OrderStatusChanged;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class WeightsAndHistoryTest extends TestCase
{
    private CustomerUser $user;

    private PriceListItem $cod;

    private PriceListItem $turbot;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->user = CustomerUser::factory()->for(Customer::factory())->create();
        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($this->user->customer->customer_type_id);
        $this->cod = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Kabeljauw', 'unit' => 'kg', 'vat_rate' => 6])->id, 'price' => 20]);
        $this->turbot = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Tarbot', 'unit' => 'kg', 'vat_rate' => 6])->id, 'price' => 30]);
    }

    private function order(): Order
    {
        return app(PlaceOrder::class)->handle($this->user, [$this->cod->id => 2, $this->turbot->id => 1]);
    }

    private function codLine(Order $order): OrderItem
    {
        return $order->items()->where('product_id', $this->cod->product_id)->firstOrFail();
    }

    public function test_placing_an_order_starts_its_history(): void
    {
        $order = $this->order();

        $event = $order->events()->sole();
        $this->assertSame(OrderEvent::PLACED, $event->event);
        $this->assertSame($this->user->id, $event->customer_user_id);
        $this->assertSame($this->user->name, $event->actor_name);
    }

    public function test_a_weighed_quantity_is_what_gets_billed(): void
    {
        $order = $this->order();
        $staff = $this->superAdmin();
        $this->actingAs($staff);

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->call('updateTableColumnState', 'delivered_quantity', (string) $this->codLine($order)->id, '2,35')
            ->assertDispatched('refresh-order-totals');

        $line = $this->codLine($order);
        $this->assertSame('2.350', $line->delivered_quantity);
        $this->assertEquals(47.00, (float) $line->line_total);
        $this->assertEquals(47 + 30, (float) $order->fresh()->subtotal);

        $event = $order->events()->first();
        $this->assertSame(OrderEvent::LINES, $event->event);
        $this->assertSame($staff->id, $event->user_id);
        $this->assertStringContainsString('Kabeljauw gewogen', $event->lines()[0]);

        // The customer sees the weight, with what they ordered underneath.
        $this->actingAs($this->user, 'customer')
            ->get("/portal/bestellingen/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.items.0.ordered', fn ($ordered) => str_contains($ordered, 'besteld'))
                ->where('order.items.1.ordered', null));
    }

    public function test_a_weighed_line_rounds_half_cents_up(): void
    {
        $order = $this->order();
        $line = $this->codLine($order);
        $line->update(['unit_price' => 21.90]);

        app(WeighOrderItem::class)->handle($line, '2,15', $this->superAdmin());

        // 21,90 × 2,15 = 47,085: a float product would round that down.
        $this->assertEquals(47.09, (float) $line->fresh()->line_total);
    }

    public function test_rubbish_is_refused_and_an_empty_field_means_as_ordered(): void
    {
        $order = $this->order();
        $staff = $this->superAdmin();
        $line = $this->codLine($order);

        try {
            app(WeighOrderItem::class)->handle($line, 'twee kilo', $staff);
            $this->fail('A non-number was accepted.');
        } catch (ValidationException) {
        }

        app(WeighOrderItem::class)->handle($line, '1.8', $staff);
        app(WeighOrderItem::class)->handle($line->fresh(), '', $staff);

        $this->assertNull($line->fresh()->delivered_quantity);
        $this->assertEquals(40.00, (float) $line->fresh()->line_total);
        // Weighing and un-weighing within minutes cancels out in the history.
        $this->assertSame([OrderEvent::PLACED], $order->events()->pluck('event')->all());
    }

    public function test_weighing_line_after_line_is_one_history_entry(): void
    {
        $order = $this->order();
        $staff = $this->superAdmin();

        foreach ($order->items as $item) {
            app(WeighOrderItem::class)->handle($item, '1,5', $staff);
        }
        app(WeighOrderItem::class)->handle($this->codLine($order), '2,1', $staff);

        $event = $order->events()->first();
        $this->assertSame(2, $order->events()->count());
        $this->assertCount(2, $event->details);
        $this->assertEquals(2.1, (float) collect($event->details)->firstWhere('product', 'Kabeljauw')['to']);
    }

    public function test_weights_can_be_entered_on_the_picking_list(): void
    {
        $order = app(PlaceOrder::class)->handle($this->user, [$this->cod->id => 2], today()->addDays(2)->toDateString());
        $this->actingAs($this->superAdmin());

        Livewire::test(PickingList::class, ['date' => today()->addDays(2)->toDateString()])
            ->assertSee('Kabeljauw')
            ->call('weigh', $this->codLine($order)->id, '1,95')
            ->assertNotified();

        $this->assertSame('1.950', $this->codLine($order)->delivered_quantity);
        $this->assertEquals(39.00, (float) $order->fresh()->subtotal);
    }

    public function test_staff_who_may_only_view_cannot_weigh(): void
    {
        $order = app(PlaceOrder::class)->handle($this->user, [$this->cod->id => 2], today()->addDays(2)->toDateString());
        $this->actingAs($this->staffWith(['orders.view']));

        Livewire::test(PickingList::class, ['date' => today()->addDays(2)->toDateString()])
            ->call('weigh', $this->codLine($order)->id, '1,95')
            ->assertForbidden();

        $this->assertNull($this->codLine($order)->delivered_quantity);
    }

    public function test_a_customer_change_is_recorded_and_listed_in_the_staff_mail(): void
    {
        $order = $this->order();
        $staff = $this->staffWith(['orders.receive-notifications']);
        app(WeighOrderItem::class)->handle($order->items()->where('product_id', $this->turbot->product_id)->first(), '1,2', $this->superAdmin());

        app(PlaceOrder::class)->update($order, $this->user, [$this->cod->id => 3, $this->turbot->id => 1], null, 'Voordeur');

        // The turbot wasn't changed, so its weight stays.
        $this->assertSame('1.200', $order->items()->where('product_id', $this->turbot->product_id)->value('delivered_quantity'));

        $event = $order->events()->first();
        $this->assertSame(OrderEvent::CHANGED, $event->event);
        $this->assertSame(['customer_note', 'quantity'], collect($event->details)->pluck('field')->all());

        Mail::assertQueued(OrderReceived::class, function (OrderReceived $mail) use ($staff) {
            $html = $mail->render();

            return $mail->hasTo($staff->email) && $mail->event === OrderReceived::CHANGED
                && str_contains($html, 'Wat is er gewijzigd')
                && str_contains($html, 'Kabeljauw: 2 kg → 3 kg')
                && str_contains($html, 'Opmerking: "Voordeur"')
                && str_contains($html, 'besteld: 1 kg');
        });
    }

    public function test_status_changes_edits_and_line_edits_are_recorded_and_shown(): void
    {
        $order = $this->order();
        $this->actingAs($staff = $this->superAdmin());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('status-confirmed', data: ['notify' => true, 'note' => 'Tot dinsdag'])
            ->assertHasNoActionErrors()
            ->assertDispatched('refresh-order-totals');
        Mail::assertQueued(OrderStatusChanged::class);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['internal_note' => 'Klaar om 7u', 'requested_delivery_date' => '2031-10-09'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->callTableAction('edit', $this->codLine($order), data: ['quantity' => 2, 'unit_price' => 22])
            ->assertHasNoTableActionErrors()
            ->callTableAction('delete', $order->items()->where('product_id', $this->turbot->product_id)->first());

        $events = $order->events()->get();
        $this->assertSame([OrderEvent::LINES, OrderEvent::EDITED, OrderEvent::STATUS, OrderEvent::PLACED], $events->pluck('event')->all());
        $this->assertSame(['status', 'notified', 'message'], collect($events[2]->details)->pluck('field')->all());
        $this->assertSame(['delivery_date', 'internal_note'], collect($events[1]->details)->pluck('field')->all());
        $this->assertSame(['price', 'removed'], collect($events[0]->details)->pluck('field')->all());
        $this->assertTrue($events->every(fn (OrderEvent $event) => in_array($event->actor_name, [$staff->name, $this->user->name], true)));

        Livewire::test(OrderHistoryWidget::class, ['record' => $order])
            ->assertSee('Status gewijzigd')
            ->assertSee('Status: Nieuw → Bevestigd')
            ->assertSee('Bericht aan de klant: &quot;Tot dinsdag&quot;', false)
            ->assertSee('Leverdatum: geen datum → donderdag 9 oktober')
            ->assertSee('Tarbot verwijderd (was 1 kg)')
            ->assertSee('Kabeljauw, prijs: € 20,00 / kg → € 22,00 / kg');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }
}
