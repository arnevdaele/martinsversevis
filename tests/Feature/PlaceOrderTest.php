<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Enums\Unit;
use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlaceOrderTest extends TestCase
{
    private CustomerType $horeca;

    private CustomerUser $chef;

    private PriceListItem $cod;

    private PriceListItem $turbot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->horeca = CustomerType::factory()->create(['notification_emails' => ['keuken@example.test', 'KEUKEN@example.test ']]);
        $this->chef = CustomerUser::factory()->for(Customer::factory()->for($this->horeca, 'type'))->create();

        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($this->horeca);

        $this->cod = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Kabeljauw'])->id, 'price' => 20.00]);
        $this->turbot = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Tarbot'])->id, 'price' => null]);
    }

    public function test_it_snapshots_items_and_calculates_totals(): void
    {
        Mail::fake();

        $order = app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1.5, $this->turbot->id => 2], '2099-01-01', 'Achterdeur', [$this->cod->id => 'zonder vel']);

        $this->assertSame($this->chef->customer_id, $order->customer_id);
        $this->assertCount(2, $order->items);
        $this->assertSame('30.00', $order->subtotal);
        $this->assertSame('1.80', $order->vat_total);
        $this->assertSame('31.80', $order->total);
        $this->assertTrue($order->has_unpriced_items);
        $this->assertSame('zonder vel', $order->items->firstWhere('product_name', 'Kabeljauw')->note);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{6}$/', $order->number);

        // A later price change never rewrites what was ordered.
        $this->cod->update(['price' => 99]);
        $this->assertSame('20.00', $order->items()->where('product_name', 'Kabeljauw')->first()->unit_price);
    }

    public function test_order_numbers_count_up(): void
    {
        Mail::fake();

        $first = app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1]);
        $second = app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1]);

        $this->assertSame((int) substr($first->number, 5) + 1, (int) substr($second->number, 5));
    }

    public function test_it_mails_staff_who_may_see_the_type_and_the_types_extra_addresses(): void
    {
        Mail::fake();

        $allTypes = $this->staffWith(['orders.receive-notifications']);
        $thisType = $this->staffWith(['orders.receive-notifications']);
        $thisType->customerTypes()->attach($this->horeca);
        $otherType = $this->staffWith(['orders.receive-notifications']);
        $otherType->customerTypes()->attach(CustomerType::factory()->create());
        $noPermission = $this->staffWith(['orders.view']);
        $inactive = $this->staffWith(['orders.receive-notifications']);
        $inactive->update(['is_active' => false]);

        app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1]);

        Mail::assertQueued(OrderReceived::class, 3);
        foreach ([$allTypes->email, $thisType->email, 'keuken@example.test'] as $address) {
            Mail::assertQueued(OrderReceived::class, fn ($mail) => $mail->hasTo($address));
        }
        foreach ([$otherType, $noPermission, $inactive] as $user) {
            Mail::assertNotQueued(OrderReceived::class, fn ($mail) => $mail->hasTo($user->email));
        }
        Mail::assertQueued(OrderConfirmation::class, fn ($mail) => $mail->hasTo($this->chef->email));
    }

    public function test_both_mails_render(): void
    {
        Mail::fake();
        $order = app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1.5, $this->turbot->id => 2], '2099-01-01', "Lijn één\nlijn twee", [$this->cod->id => 'zonder | vel']);

        $received = (new OrderReceived($order))->render();
        $this->assertStringContainsString($order->number, $received);
        $this->assertStringContainsString('Kabeljauw', $received);
        $this->assertStringContainsString('€ 30,00', $received);
        $this->assertStringContainsString('Dagprijs', $received);

        $confirmation = (new OrderConfirmation($order))->render();
        $this->assertStringContainsString($order->number, $confirmation);
        $this->assertStringContainsString("/portal/bestellingen/{$order->id}", $confirmation);
    }

    public function test_confirmation_can_be_switched_off(): void
    {
        Mail::fake();
        $this->chef->update(['receives_order_confirmations' => false]);

        app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1]);

        Mail::assertNotQueued(OrderConfirmation::class);
    }

    public function test_items_from_a_list_the_customer_cannot_see_are_refused(): void
    {
        $secret = PriceList::factory()->create();
        $item = $secret->items()->create(['product_id' => Product::factory()->create()->id, 'price' => 1]);

        $this->expectException(ValidationException::class);

        app(PlaceOrder::class)->handle($this->chef, [$item->id => 1]);
    }

    public function test_inactive_products_and_expired_lists_are_refused(): void
    {
        $this->cod->product->update(['is_active' => false]);

        try {
            app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1]);
            $this->fail('Inactive product was accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey("lines.{$this->cod->id}", $e->errors());
        }

        $this->cod->product->update(['is_active' => true]);
        $this->cod->priceList->update(['valid_until' => now()->subDay()]);

        $this->expectException(ValidationException::class);
        app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1]);
    }

    public function test_minimum_quantity_and_whole_units_are_enforced(): void
    {
        $this->cod->update(['min_quantity' => 2]);
        $this->turbot->product->update(['unit' => Unit::Piece]);

        try {
            app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 1, $this->turbot->id => 1.5]);
            $this->fail('Invalid quantities were accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey("lines.{$this->cod->id}", $e->errors());
            $this->assertArrayHasKey("lines.{$this->turbot->id}", $e->errors());
        }
    }

    public function test_an_empty_basket_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(PlaceOrder::class)->handle($this->chef, [$this->cod->id => 0]);
    }

    public function test_staff_price_edit_updates_the_line_and_totals(): void
    {
        Mail::fake();
        $order = app(PlaceOrder::class)->handle($this->chef, [$this->turbot->id => 2]);

        $order->items()->first()->update(['unit_price' => 30]);
        $order->unsetRelation('items');
        $order->recalculate();

        $this->assertSame('60.00', $order->fresh()->subtotal);
        $this->assertFalse($order->fresh()->has_unpriced_items);
    }
}
