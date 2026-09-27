<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Mail\OrderConfirmation;
use App\Mail\OrderReceived;
use App\Models\Customer;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Notifications\CustomerInvitation;
use App\Notifications\CustomerResetPassword;
use App\Support\Locales;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalisationTest extends TestCase
{
    /** Every customer-facing file must have the same keys in every language. */
    public function test_language_files_are_complete(): void
    {
        $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
            $keys = [];
            foreach ($lines as $key => $value) {
                $keys = [...$keys, ...(is_array($value) ? $flatten($value, "{$prefix}{$key}.") : ["{$prefix}{$key}"])];
            }

            return $keys;
        };

        foreach (['portal', 'orders', 'units', 'delivery'] as $file) {
            $base = $flatten(require lang_path(Locales::default()."/{$file}.php"));

            foreach (Locales::translated() as $locale) {
                $other = $flatten(require lang_path("{$locale}/{$file}.php"));
                $this->assertEqualsCanonicalizing($base, $other, "lang/{$locale}/{$file}.php is out of step with the base language.");
            }
        }
    }

    private function frenchCustomer(): CustomerUser
    {
        $customer = Customer::factory()->create(['locale' => 'fr']);
        $user = CustomerUser::factory()->for($customer)->create();

        $category = ProductCategory::create(['name' => 'Vis', 'slug' => 'vis', 'translations' => ['fr' => ['name' => 'Poissons']]]);
        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($customer->customer_type_id);
        $list->items()->create(['product_id' => Product::factory()->create([
            'name' => 'Kabeljauwfilet', 'product_category_id' => $category->id,
            'translations' => ['fr' => ['name' => 'Filet de cabillaud']],
        ])->id, 'price' => 20]);
        // No French name: falls back to Dutch.
        $list->items()->create(['product_id' => Product::factory()->create(['name' => 'Tongschar'])->id, 'price' => 6]);

        return $user;
    }

    public function test_the_portal_speaks_the_customers_language_with_fallback(): void
    {
        $user = $this->frenchCustomer();

        $this->actingAs($user, 'customer')
            ->get('/portal')
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'fr')
                ->where('t.nav.order', 'Commander')
                ->where('items.0.name', 'Filet de cabillaud')
                ->where('items.0.category.name', 'Poissons')
                ->where('items.1.name', 'Tongschar')
                ->where('items.0.priceLabel', "20,00\u{00A0}€"));
    }

    public function test_guests_get_their_browser_language_and_can_switch(): void
    {
        $this->get('/portal/login', ['Accept-Language' => 'fr-BE,fr;q=0.9'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));

        $this->post('/portal/taal/nl')->assertRedirect();
        $this->get('/portal/login', ['Accept-Language' => 'fr-BE'])
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'nl'));

        $this->post('/portal/taal/de')->assertNotFound();
    }

    public function test_switching_language_while_signed_in_is_remembered_on_the_login(): void
    {
        $user = CustomerUser::factory()->create();

        $this->actingAs($user, 'customer')->post('/portal/taal/fr');

        $this->assertSame('fr', $user->fresh()->locale);
        $this->assertSame('fr', $user->fresh()->preferredLocale());
    }

    public function test_the_customer_gets_french_mail_and_staff_get_dutch(): void
    {
        Mail::fake();
        $user = $this->frenchCustomer();
        $staff = $this->staffWith(['orders.receive-notifications']);

        $order = app(PlaceOrder::class)->handle($user, [$user->customer->visiblePriceLists()->first()->items()->first()->id => 2]);

        $this->assertSame(['fr' => ['product_name' => 'Filet de cabillaud']], $order->items->first()->translations);

        Mail::assertQueued(OrderConfirmation::class, function (OrderConfirmation $mail) use ($user) {
            $html = $mail->locale($mail->locale)->render();

            return $mail->hasTo($user->email) && $mail->locale === 'fr'
                && str_contains($html, 'Merci pour votre commande') && str_contains($html, 'Filet de cabillaud');
        });

        Mail::assertQueued(OrderReceived::class, function (OrderReceived $mail) use ($staff) {
            $html = $mail->render();

            return $mail->hasTo($staff->email) && $mail->locale === 'nl'
                && str_contains($html, 'Nieuwe bestelling') && str_contains($html, 'Kabeljauwfilet');
        });
    }

    public function test_the_invitation_is_in_the_logins_language(): void
    {
        Notification::fake();
        $user = CustomerUser::factory()->for(Customer::factory()->create(['locale' => 'fr']))->create();

        $user->sendInvitation();

        Notification::assertSentTo($user, CustomerInvitation::class, function (CustomerInvitation $notification, array $channels, CustomerUser $notifiable, ?string $locale) {
            return $locale === 'fr';
        });
    }

    public function test_customer_mails_reply_to_the_client_and_staff_mails_to_the_customer(): void
    {
        config(['mail.customer_reply_to.address' => 'info@martinsversevis.test']);
        $user = CustomerUser::factory()->create();
        $order = Order::create(['number' => '2026-000001', 'customer_id' => $user->customer_id, 'customer_user_id' => $user->id]);

        $this->assertTrue((new OrderConfirmation($order))->hasReplyTo('info@martinsversevis.test'));
        $this->assertTrue((new OrderReceived($order))->hasReplyTo($user->email));
        $this->assertSame('info@martinsversevis.test', (new CustomerInvitation('x'))->toMail($user)->replyTo[0][0]);

        config(['mail.customer_reply_to.address' => null]);
        $this->assertFalse((new OrderConfirmation($order))->hasReplyTo('info@martinsversevis.test'));
    }

    public function test_every_outgoing_mail_goes_through_the_quota(): void
    {
        foreach ([
            new OrderReceived(new Order),
            new OrderConfirmation(new Order),
            new CustomerInvitation('x'),
            new CustomerResetPassword('x'),
        ] as $mail) {
            $middleware = $mail->middleware();
            $this->assertInstanceOf(RateLimited::class, $middleware[0], $mail::class);
        }
    }
}
