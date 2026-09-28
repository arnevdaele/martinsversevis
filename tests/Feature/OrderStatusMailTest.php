<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderStatusChanged;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrderStatusMailTest extends TestCase
{
    private CustomerUser $chef;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $type = CustomerType::factory()->create();
        $this->chef = CustomerUser::factory()->for(Customer::factory()->for($type, 'type'))->create(['locale' => 'fr']);

        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($type);
        $cod = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Kabeljauw', 'translations' => ['fr' => ['name' => 'Cabillaud']]])->id, 'price' => 20.00]);
        $turbot = $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Tarbot'])->id, 'price' => null]);

        $this->order = app(PlaceOrder::class)->handle($this->chef, [$cod->id => 1.5, $turbot->id => 2]);
        Mail::fake();
        $this->actingAs($this->superAdmin());
    }

    public function test_confirming_mails_the_customer_with_the_final_prices(): void
    {
        // Staff fill in the day price first; the mail shows it.
        $this->order->items()->where('product_name', 'Tarbot')->first()->update(['unit_price' => 35]);
        $this->order->unsetRelation('items')->recalculate();

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('status-confirmed', data: ['notify' => true, 'note' => 'Tarbot is extra vers vandaag.'])
            ->assertHasNoActionErrors();

        $this->assertSame('confirmed', $this->order->fresh()->status->value);
        Mail::assertQueued(OrderStatusChanged::class, function (OrderStatusChanged $mail) {
            $html = $mail->locale($mail->locale)->render();

            return $mail->hasTo($this->chef->email)
                && $mail->locale === 'fr'
                && str_contains($html, 'Votre commande est confirmée')
                && str_contains($html, 'Cabillaud')
                && str_contains($html, '70,00')
                && str_contains($html, 'Tarbot is extra vers vandaag.')
                && ! str_contains($html, 'prix du jour');
        });
    }

    public function test_the_admin_can_choose_not_to_tell(): void
    {
        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('status-confirmed', data: ['notify' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('confirmed', $this->order->fresh()->status->value);
        Mail::assertNothingQueued();
    }

    public function test_the_toggle_follows_the_customers_own_preference(): void
    {
        $this->chef->update(['receives_order_confirmations' => false]);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->mountAction('status-cancelled')
            ->assertActionDataSet(['notify' => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('cancelled', $this->order->fresh()->status->value);
        Mail::assertNotQueued(OrderStatusChanged::class);
    }

    public function test_cancelling_renders_without_the_lines_and_delivered_mails_only_when_asked(): void
    {
        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('status-cancelled', data: ['notify' => true, 'note' => 'Geen tarbot te krijgen.']);

        Mail::assertQueued(OrderStatusChanged::class, function (OrderStatusChanged $mail) {
            $this->chef->update(['locale' => 'nl']);
            $html = $mail->locale('nl')->render();

            return str_contains($html, 'Je bestelling is geannuleerd')
                && str_contains($html, 'Geen tarbot te krijgen.')
                && ! str_contains($html, 'Kabeljauw');
        });

        $other = $this->order->replicate(['number'])->fill(['number' => '2099-000001', 'status' => 'new']);
        $other->save();

        Livewire::test(ViewOrder::class, ['record' => $other->getRouteKey()])
            ->callAction('status-delivered', data: ['notify' => false])
            ->assertHasNoActionErrors();

        $this->assertSame('delivered', $other->fresh()->status->value);
        Mail::assertQueuedCount(1);
    }
}
