<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Notifications\CustomerInvitation;
use App\Notifications\CustomerResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalTest extends TestCase
{
    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/portal')->assertRedirect('/portal/login');
        $this->get('/')->assertRedirect('/portal');
    }

    public function test_a_customer_can_log_in(): void
    {
        $user = CustomerUser::factory()->create();

        $this->post('/portal/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/portal');

        $this->assertAuthenticatedAs($user, 'customer');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_a_staff_session_is_not_a_portal_session(): void
    {
        $this->actingAs($this->superAdmin())->get('/portal')->assertRedirect('/portal/login');
    }

    public function test_inactive_logins_and_customers_are_refused(): void
    {
        $user = CustomerUser::factory()->create(['is_active' => false]);
        $this->post('/portal/login', ['email' => $user->email, 'password' => 'secret-password'])->assertSessionHasErrors('email');
        $this->assertGuest('customer');

        $other = CustomerUser::factory()->create();
        $this->actingAs($other, 'customer')->get('/portal')->assertOk();

        // Archiving the company takes effect on the next request, not the next login.
        $other->customer->delete();
        $this->get('/portal')->assertRedirect('/portal/login');
        $this->assertGuest('customer');
    }

    public function test_the_catalogue_holds_only_the_lists_this_customer_may_see(): void
    {
        $type = CustomerType::factory()->create();
        $customer = Customer::factory()->for($type, 'type')->create();
        $user = CustomerUser::factory()->for($customer)->create();

        $viaType = PriceList::factory()->create(['name' => 'Via type']);
        $viaType->customerTypes()->attach($type);
        $direct = PriceList::factory()->create(['name' => 'Direct']);
        $direct->customers()->attach($customer);
        $inactive = PriceList::factory()->create(['is_active' => false]);
        $inactive->customerTypes()->attach($type);
        $future = PriceList::factory()->create(['valid_from' => now()->addWeek()]);
        $future->customerTypes()->attach($type);
        $otherType = PriceList::factory()->create();
        $otherType->customerTypes()->attach(CustomerType::factory()->create());

        foreach ([$viaType, $direct, $inactive, $future, $otherType] as $list) {
            $list->items()->create(['product_id' => Product::factory()->create()->id, 'price' => 10]);
        }

        $this->actingAs($user, 'customer')
            ->get('/portal')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Order/Create')
                ->where('hasLists', true)
                ->has('items', 2));
    }

    public function test_placing_an_order_from_the_portal(): void
    {
        Mail::fake();
        $user = CustomerUser::factory()->create();
        $list = PriceList::factory()->create();
        $list->customerTypes()->attach($user->customer->customer_type_id);
        $item = $list->items()->create(['product_id' => Product::factory()->create()->id, 'price' => 12.5]);

        $response = $this->actingAs($user, 'customer')->post('/portal/bestellingen', [
            'lines' => [$item->id => '2'],
            'requested_delivery_date' => now()->addDay()->toDateString(),
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect("/portal/bestellingen/{$order->id}");
        $this->assertSame('25.00', $order->subtotal);
    }

    public function test_colleagues_share_order_history_but_others_do_not(): void
    {
        $user = CustomerUser::factory()->create();
        $colleague = CustomerUser::factory()->for($user->customer)->create();
        $stranger = CustomerUser::factory()->create();
        $order = Order::create(['number' => '2026-000001', 'customer_id' => $user->customer_id, 'customer_user_id' => $user->id, 'submitted_at' => now()]);

        $this->actingAs($colleague, 'customer')->get("/portal/bestellingen/{$order->id}")->assertOk();
        $this->actingAs($stranger, 'customer')->get("/portal/bestellingen/{$order->id}")->assertNotFound();
    }

    public function test_an_invitation_lets_the_customer_choose_a_password_and_logs_them_in(): void
    {
        Notification::fake();
        $user = CustomerUser::factory()->invited()->create();

        $user->sendInvitation();

        $token = null;
        Notification::assertSentTo($user, CustomerInvitation::class, function (CustomerInvitation $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/portal/wachtwoord', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect('/portal');

        $this->assertAuthenticatedAs($user, 'customer');
        $this->assertTrue($user->fresh()->hasAcceptedInvitation());
    }

    public function test_invitation_and_reset_mails_render(): void
    {
        $user = CustomerUser::factory()->create();

        $invitation = (string) (new CustomerInvitation('token-123'))->toMail($user)->render();
        $this->assertStringContainsString('/portal/wachtwoord/token-123', $invitation);
        $this->assertStringContainsString(e($user->customer->name), $invitation);

        $reset = (string) (new CustomerResetPassword('token-456'))->toMail($user)->render();
        $this->assertStringContainsString('/portal/wachtwoord/token-456', $reset);
    }

    public function test_a_customer_can_change_their_password(): void
    {
        $user = CustomerUser::factory()->create();

        $this->actingAs($user, 'customer')
            ->put('/portal/account/wachtwoord', ['current_password' => 'wrong', 'password' => 'another-password', 'password_confirmation' => 'another-password'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user, 'customer')
            ->put('/portal/account/wachtwoord', ['current_password' => 'secret-password', 'password' => 'another-password', 'password_confirmation' => 'another-password'])
            ->assertSessionHasNoErrors();
    }
}
