<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\UsersRelationManager;
use App\Filament\Resources\CustomerTypes\Pages\ManageCustomerTypes;
use App\Filament\Resources\DeliveryExceptions\Pages\ManageDeliveryExceptions;
use App\Filament\Resources\DeliverySchedules\Pages\EditDeliverySchedule;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager as OrderItemsRelationManager;
use App\Filament\Resources\PriceLists\Pages\CreatePriceList;
use App\Filament\Resources\PriceLists\Pages\EditPriceList;
use App\Filament\Resources\PriceLists\Pages\ManagePrices;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\DeliveryException;
use App\Models\DeliverySchedule;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Notifications\CustomerInvitation;
use App\Support\DeliveryCalendar;
use Database\Seeders\DemoSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Opens and submits every form in the admin. Page-load tests miss what only
 * breaks inside a modal or on save, so each create/edit is exercised here.
 */
class AdminFormsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::firstWhere('email', 'admin@martinsversevis.test'));
    }

    public function test_every_edit_modal_on_the_simple_resources_opens_and_saves(): void
    {
        foreach ([
            [ManageProducts::class, Product::first()],
            [ManageProductCategories::class, ProductCategory::first()],
            [ManageCustomerTypes::class, CustomerType::first()],
            [ManageDeliveryExceptions::class, DeliveryException::first()],
        ] as [$page, $record]) {
            Livewire::test($page)
                ->callAction(TestAction::make('edit')->table($record))
                ->assertHasNoActionErrors();
        }
    }

    public function test_create_modals_on_the_simple_resources(): void
    {
        Livewire::test(ManageCustomerTypes::class)
            ->callAction('create', data: ['name' => 'Traiteur', 'slug' => 'traiteur', 'notification_emails' => ['a@b.test']])
            ->assertHasNoActionErrors();

        Livewire::test(ManageDeliveryExceptions::class)
            ->callAction('create', data: ['kind' => DeliveryException::CLOSED, 'starts_on' => now()->addMonth()->toDateString()])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('customer_types', ['slug' => 'traiteur']);
        $this->assertSame(2, DeliveryException::where('kind', DeliveryException::CLOSED)->count());
    }

    public function test_customer_pages_and_portal_logins(): void
    {
        Notification::fake();

        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Vishandel Noord', 'customer_type_id' => CustomerType::first()->id, 'locale' => 'fr', 'country' => 'BE'])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = Customer::firstWhere('name', 'Vishandel Noord');
        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])->call('save')->assertHasNoFormErrors();

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => EditCustomer::class])
            ->callAction(TestAction::make('create')->table(), data: ['name' => 'Luc', 'email' => 'luc@noord.test'])
            ->assertHasNoActionErrors();

        $login = CustomerUser::firstWhere('email', 'luc@noord.test');
        Notification::assertSentTo($login, CustomerInvitation::class);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => EditCustomer::class])
            ->callAction(TestAction::make('edit')->table($login), data: ['locale' => 'nl'])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('invite')->table($login))
            ->assertHasNoActionErrors();

        Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => EditCustomer::class])->assertOk();
    }

    public function test_price_list_pages_and_actions(): void
    {
        $business = PriceList::firstWhere('name', 'Zakelijk standaard');

        Livewire::test(CreatePriceList::class)
            ->fillForm(['name' => 'Kerst', 'customerTypes' => [CustomerType::first()->id], 'start' => ['from' => $business->id, 'percentage' => 10, 'step' => '0.10']])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(PriceListResource::getUrl('prices', ['record' => PriceList::firstWhere('name', 'Kerst')]));

        $list = PriceList::firstWhere('name', 'Kerst');
        $this->assertSame($business->items()->count(), $list->items()->count(), 'Started from the business list.');
        $this->assertSame('27.00', $list->items()->where('product_id', Product::firstWhere('slug', 'kabeljauwfilet')->id)->value('price'), '24,50 + 10% rounded up to 10 cent.');

        Livewire::test(EditPriceList::class, ['record' => $list->getRouteKey()])->call('save')->assertHasNoFormErrors();

        Livewire::test(ManagePrices::class, ['record' => $list->getRouteKey()])
            ->callAction('adjust', data: ['percentage' => -10, 'step' => '0'])
            ->assertHasNoActionErrors()
            ->callAction('copyFrom', data: ['from' => $business->id, 'percentage' => 0, 'step' => '0', 'overwrite' => '1'])
            ->assertHasNoActionErrors();

        $this->assertSame('24.50', $list->items()->where('product_id', Product::firstWhere('slug', 'kabeljauwfilet')->id)->value('price'));
    }

    public function test_order_pages_status_and_items(): void
    {
        $order = app(PlaceOrder::class)->handle(
            CustomerUser::firstWhere('email', 'jan@particulier.test'),
            [Customer::firstWhere('name', 'Jan Peeters')->visiblePriceLists()->first()->items()->first()->id => 1],
            collect(DeliveryCalendar::for(Customer::firstWhere('name', 'Jan Peeters'))->options())->first()['date']->toDateString(),
        );

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('status-confirmed')
            ->assertHasNoActionErrors();
        $this->assertSame('confirmed', $order->fresh()->status->value);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['internal_note' => 'Klaar om 7u'])
            ->call('save')
            ->assertHasNoFormErrors();

        Livewire::test(OrderItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->callAction(TestAction::make('edit')->table($order->items->first()), data: ['quantity' => 3])
            ->assertHasNoActionErrors();
    }

    public function test_staff_role_and_schedule_forms(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Tom', 'email' => 'tom@mvv.test', 'password' => 'a-long-password', 'role_names' => ['Verkoop']])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertTrue(User::firstWhere('email', 'tom@mvv.test')->hasRole('Verkoop'));

        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'Magazijn', 'permissions_orders' => ['orders.view']])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(EditDeliverySchedule::class, ['record' => DeliverySchedule::first()->getRouteKey()])
            ->callAction(TestAction::make('preset')->schemaComponent('days'), data: ['weekdays' => [1, 3], 'cutoff_days' => 2, 'cutoff_time' => '12:00'])
            ->call('save')
            ->assertHasNoFormErrors();
    }
}
