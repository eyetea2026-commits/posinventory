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
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'ProductID' => $this->product->ProductID,
            'QuantityAdjust' => -3,
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

    public function test_increase_and_decrease_both_update_inventory(): void
    {
        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), $this->basePayload(['QuantityAdjust' => 5, 'Reason' => StockAdjustment::REASON_COUNT_ERROR]));
        $this->assertSame(15, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);

        $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), $this->basePayload(['QuantityAdjust' => -4, 'Reason' => StockAdjustment::REASON_STOLEN]));
        $this->assertSame(11, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);

        $this->assertTrue(ActivityLog::where('Action', 'stock.adjusted')->count() >= 2);
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
            $this->basePayload(['QuantityAdjust' => -50, 'Reason' => StockAdjustment::REASON_LOST])
        );

        $response->assertSessionHas('error');
        $this->assertSame(10, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertDatabaseMissing('StockAdjustment', ['ProductID' => $this->product->ProductID]);
    }

    public function test_damaged_reason_decrease_creates_damage_record(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => -2, 'Reason' => StockAdjustment::REASON_DAMAGED])
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

    public function test_damaged_reason_increase_does_not_create_damage_record(): void
    {
        // A positive "Damaged" adjustment doesn't represent a new loss (e.g. correcting
        // a previous over-deduction) — only decreases should divert into the Damage module.
        $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => 2, 'Reason' => StockAdjustment::REASON_DAMAGED])
        );

        $this->assertDatabaseCount('DamagedProduct', 0);
    }

    public function test_non_damaged_reason_decrease_does_not_create_damage_record(): void
    {
        $this->actingAs($this->admin)->post(
            route('admin.stock-adjustments.store'),
            $this->basePayload(['QuantityAdjust' => -2, 'Reason' => StockAdjustment::REASON_LOST])
        );

        $this->assertDatabaseCount('DamagedProduct', 0);
    }
}
