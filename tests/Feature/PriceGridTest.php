<?php

namespace Tests\Feature;

use App\Filament\Pages\Prices;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\PriceGrid;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\TestCase;

class PriceGridTest extends TestCase
{
    public function test_cell_values_are_parsed_and_displayed(): void
    {
        $this->assertSame(['listed' => false, 'price' => null], PriceGrid::parse(''));
        $this->assertSame(['listed' => false, 'price' => null], PriceGrid::parse('  '));
        $this->assertSame(['listed' => true, 'price' => null], PriceGrid::parse('D'));
        $this->assertSame(['listed' => true, 'price' => null], PriceGrid::parse('dagprijs'));
        $this->assertSame(['listed' => true, 'price' => 24.5], PriceGrid::parse('24,50'));
        $this->assertSame(['listed' => true, 'price' => 24.5], PriceGrid::parse('24.5'));

        $this->assertSame('', PriceGrid::display(null));
        $this->assertSame('dagprijs', PriceGrid::display(new PriceListItem(['price' => null])));
        $this->assertSame('24,50', PriceGrid::display(new PriceListItem(['price' => 24.5])));
    }

    public function test_rounding_goes_up_to_the_step(): void
    {
        $this->assertSame(24.6, PriceGrid::round(24.53, 0.10));
        $this->assertSame(24.5, PriceGrid::round(24.50, 0.10), 'Already on the step: unchanged.');
        $this->assertSame(25.0, PriceGrid::round(24.01, 1));
        $this->assertSame(24.55, PriceGrid::round(24.51, 0.05));
        $this->assertSame(24.53, PriceGrid::round(24.531, 0));
    }

    public function test_setting_a_cell_adds_updates_and_removes(): void
    {
        $product = Product::factory()->create();
        $list = PriceList::factory()->create();
        $cell = fn () => $list->items()->where('product_id', $product->id)->first();

        PriceGrid::set($product, $list, '12,40');
        $this->assertSame('12.40', $cell()->price);

        PriceGrid::set($product, $list, 'd');
        $this->assertNull($cell()->price);
        $this->assertSame(1, $list->items()->count());

        PriceGrid::set($product, $list, '');
        $this->assertNull($cell());
    }

    public function test_copying_a_list_with_a_markup(): void
    {
        [$a, $b, $c] = Product::factory()->count(3)->create();
        $business = PriceList::factory()->create();
        $private = PriceList::factory()->create();
        $business->items()->createMany([
            ['product_id' => $a->id, 'price' => 20.00],
            ['product_id' => $b->id, 'price' => null],
            ['product_id' => $c->id, 'price' => 10.00],
        ]);
        $private->items()->create(['product_id' => $c->id, 'price' => 99]);

        $this->assertSame(2, PriceGrid::copy($business, $private, 15, 0.10, overwrite: false));
        $prices = $private->items()->pluck('price', 'product_id');
        $this->assertSame('23.00', $prices[$a->id]);
        $this->assertNull($prices[$b->id], 'A day price stays a day price.');
        $this->assertSame('99.00', $prices[$c->id], 'Existing prices are left alone.');

        PriceGrid::copy($business, $private, 15, 0.10, overwrite: true);
        $this->assertSame('11.50', $private->items()->where('product_id', $c->id)->value('price'));
    }

    public function test_typing_in_the_grid_saves_the_cell(): void
    {
        $this->actingAs($this->superAdmin());
        $product = Product::factory()->create();
        $list = PriceList::factory()->create();

        Livewire::test(Prices::class)
            ->assertCanSeeTableRecords([$product])
            ->call('updateTableColumnState', "list_{$list->id}", (string) $product->id, '18,90');

        $this->assertSame('18.90', $list->items()->where('product_id', $product->id)->value('price'));

        Livewire::test(Prices::class)
            ->call('updateTableColumnState', "list_{$list->id}", (string) $product->id, 'veel');
        $this->assertSame('18.90', $list->items()->where('product_id', $product->id)->value('price'), 'Rubbish is refused.');

        Livewire::test(Prices::class)->call('updateTableColumnState', "list_{$list->id}", (string) $product->id, '');
        $this->assertSame(0, $list->items()->count());
    }

    public function test_the_grid_is_read_only_without_edit_permission(): void
    {
        $product = Product::factory()->create();
        $list = PriceList::factory()->create();

        $this->actingAs($this->staffWith(['products.view']));
        $this->get('/admin/prices')->assertForbidden();

        $this->actingAs($this->staffWith(['price-lists.view']));
        $this->get('/admin/prices')->assertOk();

        Livewire::test(Prices::class)->call('updateTableColumnState', "list_{$list->id}", (string) $product->id, '5');
        $this->assertSame(0, $list->items()->count());
    }

    public function test_copy_action_on_the_grid(): void
    {
        $this->actingAs($this->superAdmin());
        $product = Product::factory()->create();
        $from = PriceList::factory()->create();
        $to = PriceList::factory()->create();
        $from->items()->create(['product_id' => $product->id, 'price' => 10]);

        Livewire::test(Prices::class)
            ->callAction('copy', data: ['from' => $from->id, 'to' => $to->id, 'percentage' => 12, 'step' => '0.50', 'overwrite' => '0'])
            ->assertHasNoActionErrors();

        $this->assertSame('11.50', $to->items()->value('price'));
    }

    public function test_a_new_product_gets_its_prices_in_one_go(): void
    {
        $this->actingAs($this->superAdmin());
        $horeca = PriceList::factory()->create();
        $private = PriceList::factory()->create();
        $unused = PriceList::factory()->create();

        Livewire::test(ManageProducts::class)
            ->callAction('create', data: [
                'name' => 'Zeebaars',
                'slug' => 'zeebaars',
                'unit' => 'kg',
                'vat_rate' => '6.00',
                'prices' => [$horeca->id => '31,50', $private->id => 'd', $unused->id => ''],
            ])
            ->assertHasNoActionErrors();

        $product = Product::firstWhere('slug', 'zeebaars');
        $this->assertSame('31.50', $horeca->items()->where('product_id', $product->id)->value('price'));
        $this->assertTrue($private->items()->where('product_id', $product->id)->exists());
        $this->assertNull($private->items()->where('product_id', $product->id)->value('price'));
        $this->assertFalse($unused->items()->exists());

        // Editing shows the prices and can take the product out of a list.
        Livewire::test(ManageProducts::class)
            ->mountAction(TestAction::make('edit')->table($product))
            ->assertActionDataSet(["prices.{$horeca->id}" => '31,50', "prices.{$private->id}" => 'dagprijs'])
            ->setActionData(["prices.{$private->id}" => ''])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertFalse($private->items()->where('product_id', $product->id)->exists());
    }
}
