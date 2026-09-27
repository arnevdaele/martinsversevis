<?php

namespace Tests\Feature;

use App\Actions\PlaceOrder;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\PriceLists\Pages\ManagePrices;
use App\Filament\Resources\PriceLists\PriceListResource;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\PriceList;
use App\Models\Product;
use App\Support\CustomerPrices;
use App\Support\ListPrices;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

class PriceListsTest extends TestCase
{
    private CustomerType $type;

    private Customer $customer;

    private Product $cod;

    private PriceList $typeList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = CustomerType::factory()->create();
        $this->customer = Customer::factory()->for($this->type, 'type')->create();
        $this->cod = Product::factory()->create(['name' => 'Kabeljauw']);
        $this->typeList = PriceList::factory()->create(['name' => 'Horeca']);
        $this->typeList->customerTypes()->attach($this->type);
        $this->typeList->items()->create(['product_id' => $this->cod->id, 'price' => 20]);
    }

    private function ownList(float $price): PriceList
    {
        $own = PriceList::factory()->create(['name' => 'Eigen']);
        $own->customers()->attach($this->customer);
        $own->items()->create(['product_id' => $this->cod->id, 'price' => $price]);

        return $own;
    }

    public function test_a_customer_list_beats_the_type_list_even_when_dearer(): void
    {
        $this->ownList(23);

        $this->assertSame('23.00', CustomerPrices::for($this->customer)->get($this->cod->id)->price);
    }

    public function test_within_one_level_the_lowest_fixed_price_wins(): void
    {
        $second = PriceList::factory()->create();
        $second->customerTypes()->attach($this->type);
        $second->items()->create(['product_id' => $this->cod->id, 'price' => 18]);
        $third = PriceList::factory()->create();
        $third->customerTypes()->attach($this->type);
        $third->items()->create(['product_id' => $this->cod->id, 'price' => null]);

        $this->assertSame('18.00', CustomerPrices::for($this->customer)->get($this->cod->id)->price);
    }

    public function test_the_portal_shows_each_product_once_at_the_customers_price(): void
    {
        $this->ownList(17.5);
        $user = CustomerUser::factory()->for($this->customer)->create();

        $this->actingAs($user, 'customer')->get('/portal')
            ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.price', 17.5));
    }

    public function test_the_overridden_type_price_cannot_be_ordered(): void
    {
        Mail::fake();
        $this->ownList(23);
        $user = CustomerUser::factory()->for($this->customer)->create();
        $typeItem = $this->typeList->items()->first();

        $this->expectException(ValidationException::class);
        app(PlaceOrder::class)->handle($user, [$typeItem->id => 1]);
    }

    public function test_typing_prices_on_the_list_page(): void
    {
        $this->actingAs($this->superAdmin());
        $sole = Product::factory()->create(['name' => 'Zeetong']);
        $page = fn () => Livewire::test(ManagePrices::class, ['record' => $this->typeList->getRouteKey()]);

        $page()->assertCanSeeTableRecords([$this->cod, $sole])
            ->call('updateTableColumnState', 'price', (string) $sole->id, '35,5');
        $this->assertSame('35.50', ListPrices::item($sole, $this->typeList)->price);

        $page()->call('updateTableColumnState', 'day_price', (string) $sole->id, true);
        $this->assertNull(ListPrices::item($sole, $this->typeList)->price);

        $page()->call('updateTableColumnState', 'day_price', (string) $sole->id, false);
        $this->assertNull(ListPrices::item($sole, $this->typeList), 'Unticking day price takes it out of the list.');

        $page()->call('updateTableColumnState', 'min_quantity', (string) $this->cod->id, '2,5');
        $this->assertSame('2.500', ListPrices::item($this->cod, $this->typeList)->min_quantity);

        $page()->call('updateTableColumnState', 'price', (string) $this->cod->id, '');
        $this->assertNull(ListPrices::item($this->cod, $this->typeList), 'Emptying the price takes it out.');

        $page()->call('updateTableColumnState', 'price', (string) $sole->id, 'veel');
        $this->assertNull(ListPrices::item($sole, $this->typeList), 'Rubbish is refused.');
    }

    public function test_the_filter_shows_what_is_in_and_out_of_the_list(): void
    {
        $this->actingAs($this->superAdmin());
        $sole = Product::factory()->create();

        Livewire::test(ManagePrices::class, ['record' => $this->typeList->getRouteKey()])
            ->filterTable('in_list', 'in')->assertCanSeeTableRecords([$this->cod])->assertCanNotSeeTableRecords([$sole])
            ->filterTable('in_list', 'out')->assertCanSeeTableRecords([$sole])->assertCanNotSeeTableRecords([$this->cod]);
    }

    public function test_the_list_page_is_read_only_without_edit_permission(): void
    {
        $this->actingAs($this->staffWith(['price-lists.view']));

        $this->get(PriceListResource::getUrl('prices', ['record' => $this->typeList]))->assertOk();
        Livewire::test(ManagePrices::class, ['record' => $this->typeList->getRouteKey()])
            ->call('updateTableColumnState', 'price', (string) $this->cod->id, '1');

        $this->assertSame('20.00', ListPrices::item($this->cod, $this->typeList)->price);

        $this->actingAs($this->staffWith(['orders.view']));
        $this->get(PriceListResource::getUrl('prices', ['record' => $this->typeList]))->assertForbidden();
    }

    public function test_a_new_product_is_priced_in_every_general_list_at_once(): void
    {
        $this->actingAs($this->superAdmin());
        $private = PriceList::factory()->create(['name' => 'Particulier']);
        $own = $this->ownList(19);

        Livewire::test(ManageProducts::class)
            ->assertActionExists('create')
            ->callAction('create', data: [
                'name' => 'Zeebaars', 'slug' => 'zeebaars', 'unit' => 'kg', 'vat_rate' => '6.00',
                'prices' => [
                    $this->typeList->id => ['price' => '31,50', 'day' => false],
                    $private->id => ['price' => null, 'day' => true],
                ],
            ])
            ->assertHasNoActionErrors();

        $bass = Product::firstWhere('slug', 'zeebaars');
        $this->assertSame('31.50', ListPrices::item($bass, $this->typeList)->price);
        $this->assertNull(ListPrices::item($bass, $private)->price);
        $this->assertNull(ListPrices::item($bass, $own), 'Customer lists are not in the product form.');

        Livewire::test(ManageProducts::class)
            ->mountAction(TestAction::make('edit')->table($bass))
            ->assertActionDataSet([
                "prices.{$this->typeList->id}.price" => '31,50',
                "prices.{$private->id}.day" => true,
            ])
            ->setActionData(["prices.{$private->id}.day" => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertNull(ListPrices::item($bass, $private));
    }

    public function test_own_prices_button_creates_the_customers_list_once(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
            ->callAction('ownPrices')
            ->assertRedirect();

        $own = $this->customer->extraPriceLists()->first();
        $this->assertNotNull($own);
        $this->assertSame(0, $own->items()->count(), 'Starts empty: only exceptions go in.');

        Livewire::test(EditCustomer::class, ['record' => $this->customer->getRouteKey()])
            ->callAction('ownPrices')
            ->assertRedirect(PriceListResource::getUrl('prices', ['record' => $own]));
        $this->assertSame(1, PriceList::whereHas('customers')->count());

        // The page shows what they pay without it.
        Livewire::test(ManagePrices::class, ['record' => $own->getRouteKey()])
            ->assertTableColumnVisible('reference')
            ->assertTableColumnStateSet('reference', '€ 20,00', $this->cod);
    }
}
