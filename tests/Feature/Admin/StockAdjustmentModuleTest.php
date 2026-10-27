<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'ProductID' => $this->product->ProductID,
            'QuantityAdjust' => 3,
            'Reason' => StockAdjustment::REASON_LOST,
            'Date' => now()->format('Y-m-d'),
        ], $overrides);
    }

    public function test_reason_must_be_one_of_the_fixed_list(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['Reason' => 'Something Else'])
        );

        $response->assertSessionHasErrors('Reason');
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }

    // The quantity is typed without a sign and always comes out of stock;
    // it's stored negative so history and reports read it as a decrease.
    public function test_quantity_is_entered_without_a_sign_and_always_removes_stock(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), $this->basePayload(['QuantityAdjust' => 4]))
            ->assertSessionHasNoErrors();

        $this->assertSame(6, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(-4, StockAdjustment::firstOrFail()->QuantityAdjust);
        $this->assertTrue(ActivityLog::where('Action', 'stock.adjusted')->exists());
    }

    public function test_stolen_is_no_longer_a_reason(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), $this->basePayload(['Reason' => 'Stolen']));

        $response->assertSessionHasErrors('Reason');
        $this->assertNotContains('Stolen', StockAdjustment::REASONS);
        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))
            ->assertOk()->assertDontSee('<option value="Stolen"', false);
    }

    // Stock only comes in through Stock Receiving: a negative (or signed)
    // quantity is rejected for every reason.
    public function test_a_negative_quantity_is_rejected(): void
    {
        foreach ([StockAdjustment::REASON_DAMAGED, StockAdjustment::REASON_LOST] as $reason) {
            $response = $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), $this->basePayload(['QuantityAdjust' => -5, 'Reason' => $reason]));

            $response->assertSessionHasErrors('QuantityAdjust');
        }

        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertSame(0, StockAdjustment::count());
    }

    public function test_inventory_count_error_is_no_longer_a_reason(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), $this->basePayload(['Reason' => StockAdjustment::REASON_COUNT_ERROR]))
            ->assertSessionHasErrors('Reason');

        $this->assertSame([StockAdjustment::REASON_DAMAGED, StockAdjustment::REASON_LOST], StockAdjustment::REASONS);
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))
            ->assertOk()
            ->assertDontSee('<option value="Inventory Count Error"', false)
            ->assertDontSee('Use -5 to subtract');
    }

    // Older adjustments keep showing the reason they were saved with.
    public function test_older_inventory_count_error_adjustments_still_show(): void
    {
        StockAdjustment::create([
            'ProductID' => $this->product->ProductID, 'QuantityAdjust' => 2,
            'Reason' => StockAdjustment::REASON_COUNT_ERROR, 'Date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))
            ->assertOk()
            ->assertSee('<span class="badge badge-info">Inventory Count Error</span>', false);
    }

    public function test_zero_quantity_adjustment_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 0])
        );

        $response->assertSessionHasErrors('QuantityAdjust');
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }

    public function test_negative_inventory_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 50, 'Reason' => StockAdjustment::REASON_LOST])
        );

        $response->assertSessionHas('error');
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertDatabaseMissing('StockAdjustment', ['ProductID' => $this->product->ProductID]);
    }

    public function test_damaged_reason_decrease_creates_damage_record(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 2, 'Reason' => StockAdjustment::REASON_DAMAGED])
        );

        $response->assertRedirect(route('admin.stock-adjustments.index'));
        $this->assertSame(8, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);

        $adjustment = StockAdjustment::first();
        $damage = DamagedProduct::where('StockAdjustmentID', $adjustment->AdjustmentID)->first();

        $this->assertNotNull($damage);
        $this->assertSame(2, $damage->Quantity);
        $this->assertSame(DamagedProduct::SOURCE_STOCK_ADJUSTMENT, $damage->SourceModule);
        $this->assertSame(DamagedProduct::STATUS_FOR_SUPPLIER_RETURN, $damage->Status);
        $this->assertTrue(ActivityLog::where('Action', 'damage.created_from_adjustment')->exists());
    }

    // A Damaged adjustment that's rejected (more than the stock on hand)
    // never reaches the Damage module.
    public function test_a_rejected_damaged_adjustment_does_not_create_damage_record(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 50, 'Reason' => StockAdjustment::REASON_DAMAGED])
        )->assertSessionHas('error');

        $this->assertDatabaseCount('DamagedProduct', 0);
    }

    public function test_non_damaged_reason_decrease_does_not_create_damage_record(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 2, 'Reason' => StockAdjustment::REASON_LOST])
        );

        $this->assertDatabaseCount('DamagedProduct', 0);
    }

    // Product selection is a plain <select>, which the shared searchable
    // dropdown gives a search box inside; only products with stock are
    // listed, each with what's on hand.
    public function test_product_dropdown_is_a_searchable_select_of_products_with_stock(): void
    {
        $outOfStock = Product::create([
            'ProductName' => 'Sold Out Camera', 'Model' => 'CAM-02',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $outOfStock->ProductID, 'Quantity' => 0, 'Status' => 'Out of Stock']);
        Product::create([
            'ProductName' => 'Never Stocked Camera', 'Model' => 'CAM-03',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->product->CategoryID,
        ]);

        foreach ([route('admin.stock-adjustments.index'), route('admin.stock-adjustments.create')] as $url) {
            $response = $this->actingAs($this->admin)->get($url);

            $response->assertOk();
            $response->assertSee('<select id="ProductID" name="ProductID" class="form-select" required>', false);
            $response->assertDontSee('list="productOptions"', false);
            $response->assertSee('DVR Camera (CAM-01) — 10 pieces in stock');
            $response->assertDontSee('Sold Out Camera');
            $response->assertDontSee('Never Stocked Camera');
        }
    }

    public function test_stock_adjustment_saves_the_selected_product(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 3, 'Reason' => StockAdjustment::REASON_LOST])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('StockAdjustment', ['ProductID' => $this->product->ProductID, 'QuantityAdjust' => -3]);
    }
}
