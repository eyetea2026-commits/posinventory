<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Cost and Selling Price take up to 5 whole-peso digits (99,999.99), the
// Reorder Threshold up to 3 digits (999); number fields take no letters;
// and the Add/Edit Product form reads Photo | Name, Category, Cost Price /
// Model, Brand, Selling Price, then the units, pricing and suppliers.
class ProductFormLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productFields(array $overrides = []): array
    {
        return array_merge([
            'ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Barcode' => 'CCTVEX-000001',
            'CostPrice' => 1000, 'Price' => 1800, 'ReorderThreshold' => 10, 'CategoryID' => $this->category->CategoryID,
        ], $overrides);
    }

    private function store(array $overrides)
    {
        return $this->actingAs($this->admin)->postJson(route('admin.products.store'), $this->productFields($overrides));
    }

    public function test_prices_take_up_to_five_digits(): void
    {
        $this->store(['CostPrice' => 100000])->assertStatus(422)
            ->assertJsonValidationErrors(['CostPrice' => 'The Cost Price can only have up to 5 digits (₱99,999.99).']);
        $this->store(['Price' => 100000])->assertStatus(422)
            ->assertJsonValidationErrors(['Price' => 'The Selling Price can only have up to 5 digits (₱99,999.99).']);
        $this->assertSame(0, Product::count());

        $this->store(['CostPrice' => 99999.99, 'Price' => 99999.99])->assertCreated();
        $this->assertSame(99999.99, (float) Product::firstOrFail()->Price);
    }

    // With no Selling Price sent, the one worked out from the Cost Price
    // (45% margin) must stay within the limit too.
    public function test_a_selling_price_worked_out_from_the_cost_stays_within_five_digits(): void
    {
        $this->store(['CostPrice' => 60000, 'Price' => null])->assertStatus(422)->assertJsonValidationErrors('Price');
        $this->assertSame(0, Product::count());
    }

    public function test_the_reorder_threshold_takes_up_to_three_digits(): void
    {
        $this->store(['ReorderThreshold' => 1000])->assertStatus(422)
            ->assertJsonValidationErrors(['ReorderThreshold' => 'The Reorder Threshold can only have up to 3 digits (999).']);

        $this->store(['ReorderThreshold' => 999])->assertCreated();
        $this->assertSame(999, Inventory::firstOrFail()->ReorderThreshold);
    }

    public function test_editing_applies_the_same_limits(): void
    {
        $this->store([])->assertCreated();
        $product = Product::firstOrFail();

        $this->actingAs($this->admin)->putJson(route('admin.products.update', $product), $this->productFields(['CostPrice' => 123456, 'ReorderThreshold' => 5000]))
            ->assertStatus(422)->assertJsonValidationErrors(['CostPrice', 'ReorderThreshold']);
        $this->assertSame(1000.0, (float) $product->fresh()->CostPrice);
    }

    public function test_the_form_reads_photo_beside_name_category_cost_then_model_brand_selling(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.products.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/product-photo-field.*?id="ProductName".*?id="CategoryID".*?id="CostPrice".*?id="Model".*?id="BrandID".*?id="SellingPrice".*?name="Description".*?id="Barcode".*?id="UnitOfMeasure".*?id="PurchaseUnit".*?id="ReorderThreshold".*?Pricing Calculations.*?Suppliers for this Product/s', $html);
        $this->assertMatchesRegularExpression('/id="ReorderThreshold"[^>]*\s+value="10" min="0" max="999" step="1" data-max-digits="3"/', $html);
        $this->assertStringContainsString('window.attachMoneyInput(costPriceInput, { maxIntegerDigits: 5 });', $html);
        $this->assertStringContainsString('window.attachMoneyInput(sellingPriceInput, { maxIntegerDigits: 5 });', $html);
        // The supplier is picked in the table's last row: the dropdown, then Add Supplier.
        $this->assertMatchesRegularExpression('/<tfoot>.*?<select id="newSupplierId" class="form-select" aria-label="Supplier">.*?id="addProductSupplierBtn".*?<\/tfoot>/s', $html);
    }

    public function test_number_fields_refuse_letters_on_admin_and_cashier_pages(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()
            ->assertSee('window.__numericInputs = true;', false);

        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->actingAs($cashier)->get(route('cashier.pos'))->assertOk()
            ->assertSee('window.__numericInputs = true;', false);
    }
}
