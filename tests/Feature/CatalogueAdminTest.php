<?php

namespace Tests\Feature;

use App\Enums\Unit;
use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogueAdminTest extends TestCase
{
    public function test_a_product_can_be_created_from_the_admin(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageProducts::class)
            ->callAction('create', data: [
                'name' => 'Kabeljauwfilet',
                'slug' => 'kabeljauwfilet',
                'unit' => Unit::Kilogram->value,
                'vat_rate' => '6.00',
                'translations' => ['fr' => ['name' => 'Filet de cabillaud']],
            ])
            ->assertHasNoActionErrors();

        $product = Product::firstWhere('slug', 'kabeljauwfilet');
        $this->assertNotNull($product);
        $this->assertSame('Filet de cabillaud', $product->t('name', 'fr'));
    }

    public function test_a_product_can_be_edited_and_keeps_its_vat_rate(): void
    {
        $this->actingAs($this->superAdmin());
        $product = Product::factory()->create(['vat_rate' => 21]);

        Livewire::test(ManageProducts::class)
            ->mountAction(TestAction::make('edit')->table($product))
            ->assertActionDataSet(['vat_rate' => '21.00'])
            ->setActionData(['name' => 'Nieuwe naam'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('Nieuwe naam', $product->fresh()->name);
        $this->assertSame('21.00', $product->fresh()->vat_rate);
    }

    public function test_a_category_can_be_created(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ManageProductCategories::class)
            ->callAction('create', data: ['name' => 'Vis', 'slug' => 'vis'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('product_categories', ['slug' => 'vis']);
    }
}
