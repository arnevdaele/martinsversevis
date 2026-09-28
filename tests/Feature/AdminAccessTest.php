<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\DeliveryExceptions\Pages\ManageDeliveryExceptions;
use App\Filament\Resources\DeliverySchedules\Pages\CreateDeliverySchedule;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\DeliveryException;
use App\Models\DeliverySchedule;
use App\Models\Order;
use App\Models\User;
use App\Support\Grants;
use App\Support\Permissions;
use Database\Seeders\DemoSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    public function test_every_admin_page_loads_for_a_super_admin(): void
    {
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::where('email', 'admin@martinsversevis.test')->first());

        $customer = Customer::first();
        $pages = [
            '/admin', '/admin/orders', '/admin/customers', '/admin/customers/create', "/admin/customers/{$customer->id}/edit",
            '/admin/customer-types', '/admin/products', '/admin/product-categories', '/admin/price-lists', '/admin/price-lists/create',
            '/admin/price-lists/1', '/admin/price-lists/1/settings', '/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/create',
            '/admin/delivery-schedules', '/admin/delivery-schedules/create', '/admin/delivery-schedules/1/edit', '/admin/delivery-exceptions',
            '/admin/products', '/admin/customer-types', '/admin/failed-jobs',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_inactive_staff_cannot_enter_the_admin(): void
    {
        $user = $this->superAdmin();
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_resources_follow_permissions(): void
    {
        $this->actingAs($this->staffWith(['orders.view']));

        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/customers')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/price-lists')->assertForbidden();
    }

    public function test_staff_limited_to_a_type_only_see_those_customers_and_orders(): void
    {
        $horeca = CustomerType::factory()->create();
        $mine = Customer::factory()->for($horeca, 'type')->create();
        $theirs = Customer::factory()->create();
        $myOrder = Order::create(['number' => '2026-000001', 'customer_id' => $mine->id, 'submitted_at' => now()]);
        $theirOrder = Order::create(['number' => '2026-000002', 'customer_id' => $theirs->id, 'submitted_at' => now()]);

        $sales = $this->staffWith(['customers.view', 'customers.update', 'orders.view']);
        $sales->customerTypes()->attach($horeca);
        $this->actingAs($sales);

        Livewire::test(ListCustomers::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);
        Livewire::test(ListOrders::class, ['activeTab' => 'all'])->assertCanSeeTableRecords([$myOrder])->assertCanNotSeeTableRecords([$theirOrder]);

        $this->get("/admin/customers/{$mine->id}/edit")->assertOk();
        $this->get("/admin/customers/{$theirs->id}/edit")->assertNotFound();
        $this->get("/admin/orders/{$theirOrder->id}")->assertNotFound();
    }

    public function test_super_admin_cannot_delete_themselves_or_system_types(): void
    {
        $admin = $this->superAdmin();
        $system = CustomerType::factory()->create(['is_system' => true]);

        $this->assertFalse($admin->can('delete', $admin));
        $this->assertFalse($admin->can('delete', $system));
        $this->assertTrue($admin->can('delete', CustomerType::factory()->create()));
        $this->assertFalse($admin->can('create', Order::class));
    }

    public function test_only_super_admins_touch_super_admins(): void
    {
        $manager = $this->staffWith(['users.view', 'users.update', 'users.delete']);
        $owner = $this->superAdmin();

        $this->assertFalse($manager->can('update', $owner));
        $this->assertFalse($manager->can('delete', $owner));
        $this->assertTrue($this->superAdmin()->can('update', $owner));
    }

    public function test_a_limited_admin_cannot_grant_what_they_do_not_hold(): void
    {
        $manager = $this->staffWith(['users.view', 'users.update', 'customers.view']);
        $grants = Grants::for($manager);

        $this->assertTrue($grants->canGrantPermission('customers.view'));
        $this->assertFalse($grants->canGrantPermission('roles.update'));
        $this->assertFalse($grants->canGrantRole(Role::findByName(Permissions::SUPER_ADMIN_ROLE)));
        $this->assertFalse($grants->canGrantRole(Role::findByName('Beheerder')));

        // What they could not choose is preserved; what they chose outside their reach is dropped.
        $this->assertEqualsCanonicalizing(
            ['roles.update', 'customers.view'],
            Grants::merge(['roles.update'], ['customers.view', 'orders.delete'], $grants->permissions()),
        );
    }

    public function test_editing_a_user_through_the_form_cannot_escalate(): void
    {
        $manager = $this->staffWith(['users.view', 'users.update', 'customers.view']);
        $colleague = User::factory()->create();
        $this->actingAs($manager);

        Livewire::test(EditUser::class, ['record' => $colleague->getRouteKey()])
            ->fillForm([
                'role_names' => [Permissions::SUPER_ADMIN_ROLE],
                'permissions_customers' => ['customers.view'],
                'permissions_roles' => ['roles.update'],
            ])
            ->call('save');

        $colleague->refresh();
        $this->assertFalse($colleague->isSuperAdmin());
        $this->assertTrue($colleague->hasDirectPermission('customers.view'));
        $this->assertFalse($colleague->hasDirectPermission('roles.update'));
    }

    public function test_role_permissions_are_saved_from_the_matrix(): void
    {
        $this->actingAs($this->superAdmin());
        $role = Role::findByName('Verkoop');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['permissions_products' => ['products.view', 'products.update']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($role->fresh()->hasPermissionTo('products.update'));
        $this->assertTrue($role->fresh()->hasPermissionTo('orders.view'), 'Other groups keep their permissions.');
    }

    public function test_type_restricted_staff_can_edit_their_customers(): void
    {
        $horeca = CustomerType::factory()->create();
        $customer = Customer::factory()->for($horeca, 'type')->create();
        $sales = $this->staffWith(['customers.view', 'customers.update']);
        $sales->customerTypes()->attach($horeca);

        $this->actingAs($sales);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm(['name' => 'Nieuwe naam'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nieuwe naam', $customer->fresh()->name);
    }

    public function test_a_delivery_schedule_is_created_from_the_day_rows(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateDeliverySchedule::class)
            ->assertSchemaStateSet(['days.2.enabled' => true, 'days.1.enabled' => false, 'is_default' => true])
            ->fillForm(['name' => 'Horeca', 'minimum_order_amount' => 40])
            // Set the Monday row the way the browser does, one field at a time.
            ->set('data.days.1.enabled', true)
            ->set('data.days.1.cutoff_days', 0)
            ->set('data.days.1.cutoff_time', '09:30')
            ->call('create')
            ->assertHasNoFormErrors();

        $schedule = DeliverySchedule::firstWhere('name', 'Horeca');
        $this->assertTrue($schedule->is_default);
        $this->assertSame(['enabled' => true, 'cutoff_days' => 0, 'cutoff_time' => '09:30'], $schedule->days()[1]);
        $this->assertTrue($schedule->days()[6]['enabled']);
        $this->assertFalse($schedule->days()[7]['enabled']);
    }

    public function test_an_extra_delivery_day_is_a_single_date(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageDeliveryExceptions::class)
            ->callAction('create', data: [
                'kind' => DeliveryException::EXTRA,
                'starts_on' => now()->addWeek()->toDateString(),
                'reason' => 'Oudejaar',
                'translations' => ['fr' => ['reason' => 'Réveillon']],
            ])
            ->assertHasNoActionErrors();

        $extra = DeliveryException::first();
        $this->assertTrue($extra->starts_on->isSameDay($extra->ends_on));
        $this->assertSame('Réveillon', $extra->t('reason', 'fr'));
    }

    public function test_delivery_needs_its_own_permission(): void
    {
        $this->actingAs($this->staffWith(['orders.view']));
        $this->get('/admin/delivery-schedules')->assertForbidden();

        $this->actingAs($this->staffWith(['delivery.view']));
        $this->get('/admin/delivery-schedules')->assertOk();
    }
}
