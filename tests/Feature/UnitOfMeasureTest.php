<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturnItem;
use App\Models\StockAdjustment;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportSummaryBuilder;
use App\Services\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Units of measurement: stock is kept in a product's base unit (meters,
// pieces), it's bought by a purchase unit (1 roll = 5,000 meters, 1 box =
// 100 pieces) and sold by the base unit - or also by the box at its own
// price - with decimals (1.5 meters) only where the product allows them.
class UnitOfMeasureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Category $category;

    private Product $wire;

    private Product $rj45;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->category = Category::create(['CategoryName' => 'WIRE']);

        // Wire: bought by the 5,000-meter roll, stocked and sold by the meter (₱10/meter).
        $this->wire = Product::create([
            'ProductName' => 'UTP Cable CAT6', 'Model' => 'CAT6-305', 'Price' => 10, 'CostPrice' => 6, 'CategoryID' => $this->category->CategoryID,
            'UnitOfMeasure' => 'meter', 'PurchaseUnit' => 'roll', 'QuantityPerPurchaseUnit' => 5000, 'AllowsFractionalQuantity' => true,
        ]);
        Inventory::create(['ProductID' => $this->wire->ProductID, 'Quantity' => 5000, 'ReorderThreshold' => 500, 'Status' => 'Available']);

        // RJ45: bought by the 100-piece box, sold by the piece (₱5) or by the box (₱450).
        $this->rj45 = Product::create([
            'ProductName' => 'RJ45 Connector', 'Model' => 'RJ45-100', 'Price' => 5, 'CostPrice' => 3, 'CategoryID' => Category::create(['CategoryName' => 'CONSUMABLES'])->CategoryID,
            'UnitOfMeasure' => 'piece', 'PurchaseUnit' => 'box', 'QuantityPerPurchaseUnit' => 100, 'PurchaseUnitPrice' => 450,
        ]);
        Inventory::create(['ProductID' => $this->rj45->ProductID, 'Quantity' => 100, 'ReorderThreshold' => 20, 'Status' => 'Available']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function sell(array $items, float $payment)
    {
        return $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => $items, 'payment_method' => 'cash', 'payment_amount' => $payment,
        ]);
    }

    private function stock(Product $product): int|float
    {
        return Inventory::where('ProductID', $product->ProductID)->firstOrFail()->Quantity;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productForm(array $overrides = []): array
    {
        return array_merge([
            'ProductName' => 'Coaxial RG59', 'Model' => 'RG59-305', 'Barcode' => '4800000000017', 'CostPrice' => 20, 'Price' => 32,
            'CategoryID' => $this->category->CategoryID, 'ReorderThreshold' => 300,
            'UnitOfMeasure' => 'meter', 'PurchaseUnit' => 'roll', 'QuantityPerPurchaseUnit' => 305, 'SellsByPurchaseUnit' => 0, 'AllowsFractionalQuantity' => 1,
        ], $overrides);
    }

    public function test_stock_is_shown_in_rolls_until_one_is_opened_then_in_meters(): void
    {
        $this->assertSame('1 Roll (5,000 meters)', $this->wire->stockLabel(5000));
        $this->assertSame('2 Rolls (10,000 meters)', $this->wire->stockLabel(10000));
        $this->assertSame('4,999 meters remaining', $this->wire->stockLabel(4999));
        $this->assertSame('4,998.5 meters remaining', $this->wire->stockLabel(4998.5));
        $this->assertSame('1 Box (100 pieces)', $this->rj45->stockLabel(100));
        $this->assertSame('90 pieces remaining', $this->rj45->stockLabel(90));
        $this->assertSame('₱10.00/meter', $this->wire->priceLabel());
        $this->assertSame('₱450.00/box', $this->rj45->priceLabel('box'));
        $this->assertSame('1 meter', UnitOfMeasure::format(1, 'meter'));
        $this->assertSame('2 boxes', UnitOfMeasure::format(2, 'box'));

        // The POS card shows the stock and each selling unit's price.
        $page = $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk();
        $page->assertSee('Stock: 1 Roll (5,000 meters)');
        $page->assertSee('Stock: 1 Box (100 pieces)');
        $page->assertSee(e(json_encode(array_values($this->rj45->sellingUnits()))), false);
    }

    public function test_the_admin_sets_up_a_product_sold_by_the_meter(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.products.store'), $this->productForm())->assertCreated();

        $product = Product::where('Model', 'RG59-305')->firstOrFail();
        $this->assertSame('meter', $product->UnitOfMeasure);
        $this->assertSame('roll', $product->PurchaseUnit);
        $this->assertSame(305, $product->QuantityPerPurchaseUnit);
        $this->assertNull($product->PurchaseUnitPrice);
        $this->assertTrue($product->AllowsFractionalQuantity);
        $this->assertSame(['meter'], array_keys($product->sellingUnits()));

        // Also sold by the roll, at its own price.
        $this->actingAs($this->admin)->putJson(route('admin.products.update', $product), $this->productForm(['SellsByPurchaseUnit' => 1, 'PurchaseUnitPrice' => 9000]))->assertRedirect();
        $this->assertSame(['meter', 'roll'], array_keys($product->fresh()->sellingUnits()));
        $this->assertSame(9000.0, $product->fresh()->sellingUnits()['roll']['price']);

        // A form without the unit section keeps the units as they are.
        $this->actingAs($this->admin)->putJson(route('admin.products.update', $product), collect($this->productForm())->except(['UnitOfMeasure', 'PurchaseUnit', 'QuantityPerPurchaseUnit', 'SellsByPurchaseUnit', 'AllowsFractionalQuantity'])->all())->assertRedirect();
        $this->assertSame('meter', $product->fresh()->UnitOfMeasure);
        $this->assertSame(9000.0, (float) $product->fresh()->PurchaseUnitPrice);
    }

    public function test_the_form_reads_like_1000_meters_equals_1_roll_at_a_price_per_meter(): void
    {
        $html = $this->actingAs($this->admin)->getJson(route('admin.products.edit', $this->wire))->assertOk()->json('html');

        // [Meter] 5000 | [Roll] 1 | ₱ 10 per meter, in that order, in one row.
        $this->assertMatchesRegularExpression('/id="UnitOfMeasure".*?id="QuantityPerPurchaseUnit"[^>]*value="5000".*?id="PurchaseUnit".*?class="form-input uom-one" value="1".*?id="SellingPrice"[^>]*value="10(.00)?".*?<span class="uom-per-base">per meter<\/span>/s', $html);
        // The Selling Price lives only there now.
        $this->assertSame(1, substr_count($html, 'name="Price"'));
    }

    public function test_unit_settings_are_validated(): void
    {
        $store = fn (array $overrides) => $this->actingAs($this->admin)->postJson(route('admin.products.store'), $this->productForm($overrides));

        $store(['QuantityPerPurchaseUnit' => null])->assertStatus(422)->assertJsonValidationErrors('QuantityPerPurchaseUnit');
        $store(['QuantityPerPurchaseUnit' => 0])->assertStatus(422)->assertJsonValidationErrors('QuantityPerPurchaseUnit');
        $store(['QuantityPerPurchaseUnit' => -305])->assertStatus(422)->assertJsonValidationErrors('QuantityPerPurchaseUnit');
        $store(['QuantityPerPurchaseUnit' => 30.5, 'AllowsFractionalQuantity' => 0])->assertStatus(422)->assertJsonValidationErrors('QuantityPerPurchaseUnit');
        $store(['PurchaseUnit' => 'meter'])->assertStatus(422)->assertJsonValidationErrors('PurchaseUnit');
        $store(['UnitOfMeasure' => 'metre'])->assertStatus(422)->assertJsonValidationErrors('UnitOfMeasure');
        // Only Piece, Meter, Roll and Box are offered.
        $this->assertSame(['piece', 'meter', 'roll', 'box'], UnitOfMeasure::UNITS);
        $store(['UnitOfMeasure' => 'pack'])->assertStatus(422)->assertJsonValidationErrors('UnitOfMeasure');
        $store(['PurchaseUnit' => 'reel'])->assertStatus(422)->assertJsonValidationErrors('PurchaseUnit');
        $store(['SellsByPurchaseUnit' => 1, 'PurchaseUnitPrice' => null])->assertStatus(422)->assertJsonValidationErrors('PurchaseUnitPrice');

        // Serial-numbered products are counted and sold by the whole piece.
        $cameras = Category::create(['CategoryName' => 'IP CAMERA', 'TracksSerialNumbers' => true]);
        $store(['CategoryID' => $cameras->CategoryID])->assertStatus(422)->assertJsonValidationErrors('UnitOfMeasure');
        $this->assertSame(0, Product::where('Model', 'RG59-305')->count());

        // A product's base unit can't change while it has stock in the old one.
        $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->rj45), $this->productForm([
            'ProductName' => 'RJ45 Connector', 'Model' => 'RJ45-100', 'Barcode' => '4800000000024', 'CategoryID' => $this->rj45->CategoryID,
            'UnitOfMeasure' => 'box', 'PurchaseUnit' => null,
        ]))->assertStatus(422)->assertJsonPath('errors.UnitOfMeasure.0', 'This product still has 100 pieces in stock. Its inventory unit can only change while its stock is 0: record that stock again in the new unit with a Stock Adjustment.');
        $this->assertSame('piece', $this->rj45->fresh()->UnitOfMeasure);
    }

    public function test_wire_products_use_meter_and_roll_and_others_piece_and_box(): void
    {
        $this->assertSame(['meter', 'roll'], UnitOfMeasure::forCategory('WIRE'));
        $this->assertSame(['meter', 'roll'], UnitOfMeasure::forCategory(' wire '));
        $this->assertSame(['piece', 'box'], UnitOfMeasure::forCategory('CONSUMABLES'));
        $this->assertSame(['piece', 'box'], UnitOfMeasure::forCategory('FIXED WIRE'));
        $this->assertSame(['piece', 'box'], UnitOfMeasure::forCategory(null));

        $consumables = $this->rj45->CategoryID;
        $store = fn (array $overrides) => $this->actingAs($this->admin)->postJson(route('admin.products.store'), $this->productForm($overrides));

        // A wire product can't be counted by the piece or bought by the box...
        $store(['UnitOfMeasure' => 'piece', 'PurchaseUnit' => null])->assertStatus(422)->assertJsonPath('errors.UnitOfMeasure.0', 'Wire products use Meter or Roll.');
        $store(['PurchaseUnit' => 'box'])->assertStatus(422)->assertJsonPath('errors.PurchaseUnit.0', 'Wire products use Meter or Roll.');
        // ...and any other product can't use meters or rolls.
        $store(['CategoryID' => $consumables])->assertStatus(422)->assertJsonPath('errors.UnitOfMeasure.0', 'Products outside WIRE use Piece or Box.');
        $store(['CategoryID' => $consumables, 'UnitOfMeasure' => 'piece', 'PurchaseUnit' => 'roll', 'AllowsFractionalQuantity' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('PurchaseUnit');

        // The allowed pairs save.
        $store([])->assertCreated();
        $store(['ProductName' => 'RJ45 Pass Through', 'Model' => 'RJ45-PT', 'Barcode' => '4800000000048', 'CategoryID' => $consumables,
            'UnitOfMeasure' => 'piece', 'PurchaseUnit' => 'box', 'QuantityPerPurchaseUnit' => 100, 'AllowsFractionalQuantity' => 0])->assertCreated();

        // The form knows each category's units.
        $html = $this->actingAs($this->admin)->getJson(route('admin.products.edit', $this->wire))->assertOk()->json('html');
        $this->assertStringContainsString('value="'.$this->category->CategoryID.'" data-units="meter_roll,meter,roll"', $html);
        $this->assertStringContainsString('value="'.$consumables.'" data-units="piece,box"', $html);
    }

    public function test_meter_and_roll_is_one_choice_for_wire_bought_by_the_roll(): void
    {
        $store = fn (array $overrides) => $this->actingAs($this->admin)->postJson(route('admin.products.store'), $this->productForm($overrides));

        // "Meter & Roll" + 1,000: counted and sold by the meter, bought by the roll.
        $store(['UnitOfMeasure' => UnitOfMeasure::METER_AND_ROLL, 'PurchaseUnit' => null, 'QuantityPerPurchaseUnit' => 1000, 'Price' => 15])->assertCreated();
        $product = Product::where('Model', 'RG59-305')->firstOrFail();
        $this->assertSame(['meter', 'roll', 1000], [$product->UnitOfMeasure, $product->PurchaseUnit, $product->QuantityPerPurchaseUnit]);
        $this->assertSame('₱15.00/meter', $product->priceLabel());
        $this->assertSame('1 Roll (1,000 meters)', $product->stockLabel(1000));

        // It needs to know how many meters one roll holds.
        $store(['ProductName' => 'Fiber Drop', 'Model' => 'FD-1', 'Barcode' => '4800000000055', 'UnitOfMeasure' => UnitOfMeasure::METER_AND_ROLL, 'PurchaseUnit' => null, 'QuantityPerPurchaseUnit' => null])
            ->assertStatus(422)->assertJsonPath('errors.QuantityPerPurchaseUnit.0', 'Enter how many meters one roll holds.');
        // Only wire products have it.
        $store(['ProductName' => 'Camera', 'Model' => 'CAM-1', 'Barcode' => '4800000000062', 'CategoryID' => $this->rj45->CategoryID, 'UnitOfMeasure' => UnitOfMeasure::METER_AND_ROLL, 'PurchaseUnit' => null])
            ->assertStatus(422)->assertJsonValidationErrors('UnitOfMeasure');

        // Editing a meter + roll product shows "Meter & Roll" picked.
        $html = $this->actingAs($this->admin)->getJson(route('admin.products.edit', $this->wire))->assertOk()->json('html');
        $this->assertStringContainsString('<option value="meter_roll" data-plural="meters" selected>Meter &amp; Roll</option>', $html);

        // The Purchase Unit can always be changed; it's kept in step with
        // Meter & Roll instead of being locked.
        $page = $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk();
        $page->assertDontSee('purchaseUnitSelect.disabled', false);
        $page->assertSee("if (baseUnitSelect.value === 'meter' && purchaseUnitSelect.value === 'roll') {", false);
        $page->assertSee('baseUnitSelect.value = METER_AND_ROLL;', false);
    }

    public function test_editing_a_product_never_touches_its_stock(): void
    {
        Inventory::where('ProductID', $this->wire->ProductID)->update(['Quantity' => 4998.5]);

        $this->actingAs($this->admin)->putJson(route('admin.products.update', $this->wire), $this->productForm([
            'ProductName' => 'UTP Cable CAT6', 'Model' => 'CAT6-305', 'Barcode' => '4800000000031', 'Price' => 12, 'QuantityPerPurchaseUnit' => 5000,
        ]))->assertRedirect();

        $this->assertSame(4998.5, $this->stock($this->wire));
        $this->assertEquals(12, $this->wire->fresh()->Price);
    }

    public function test_wire_is_sold_by_the_meter_including_part_of_a_meter(): void
    {
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 1, 'unit' => 'meter']], 10)->assertOk()->assertJsonPath('total', 10);
        $this->assertSame(4999, $this->stock($this->wire));
        $this->assertSame('4,999 meters remaining', $this->wire->stockLabel($this->stock($this->wire)));

        $this->sell([['id' => $this->wire->ProductID, 'qty' => 1.5]], 15)->assertOk()->assertJsonPath('total', 15);
        $this->assertSame(4997.5, $this->stock($this->wire));

        $line = SalesItem::latest('SalesItemID')->firstOrFail();
        $this->assertSame(1.5, $line->Quantity);
        $this->assertSame('meter', $line->Unit);
        $this->assertSame(1.5, $line->BaseQuantity);
        $this->assertEquals(10, $line->UnitPrice);

        // The receipt (on screen and on the thermal printer) prints the meters.
        $receiptNumber = 'RCT-'.str_pad((string) $line->SalesTransactionID, 6, '0', STR_PAD_LEFT);
        $this->actingAs($this->cashier)->get(route('cashier.receipt', $receiptNumber))->assertOk()->assertSee('1.5 meters');
        $lines = json_encode($this->actingAs($this->cashier)->getJson(route('cashier.receipt.print-lines', $receiptNumber))->assertOk()->json('lines'));
        $this->assertMatchesRegularExpression('/1\.5 meters x P10\.00 +P15\.00/', $lines);
    }

    public function test_rj45_is_sold_by_the_piece_or_by_the_box(): void
    {
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 10, 'unit' => 'piece']], 50)->assertOk()->assertJsonPath('total', 50);
        $this->assertSame(90, $this->stock($this->rj45));
        $this->assertSame('90 pieces remaining', $this->rj45->stockLabel(90));

        Inventory::where('ProductID', $this->rj45->ProductID)->update(['Quantity' => 200]);
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 1, 'unit' => 'box']], 450)->assertOk()->assertJsonPath('total', 450);
        $this->assertSame(100, $this->stock($this->rj45));

        $line = SalesItem::latest('SalesItemID')->firstOrFail();
        $this->assertSame([1, 'box', 100], [$line->Quantity, $line->Unit, $line->BaseQuantity]);
        $this->assertEquals(450, $line->UnitPrice);
    }

    public function test_quantities_are_checked_before_anything_is_sold(): void
    {
        // More than the stock (in the base unit).
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 5000.5]], 60000)->assertStatus(400)->assertJsonPath('message', 'Insufficient stock for UTP Cable CAT6. Available: 5,000 meters');
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 2, 'unit' => 'box']], 900)->assertStatus(400);
        // Part of a piece, of a box, or a unit it isn't sold by.
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 1.5]], 10)->assertStatus(400)->assertJsonPath('message', '"RJ45 Connector" is sold in whole pieces.');
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 0.5, 'unit' => 'box']], 450)->assertStatus(400);
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 1, 'unit' => 'roll']], 50000)->assertStatus(400)->assertJsonPath('message', '"UTP Cable CAT6" is not sold by the roll.');
        // One unit per product per sale.
        Inventory::where('ProductID', $this->rj45->ProductID)->update(['Quantity' => 200]);
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 1, 'unit' => 'box'], ['id' => $this->rj45->ProductID, 'qty' => 5, 'unit' => 'piece']], 500)->assertStatus(400);
        // Zero, negative, too many decimals.
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 0]], 10)->assertStatus(422)->assertJsonValidationErrors('items.0.qty');
        $this->sell([['id' => $this->wire->ProductID, 'qty' => -1]], 10)->assertStatus(422)->assertJsonValidationErrors('items.0.qty');
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 1.255]], 20)->assertStatus(422)->assertJsonValidationErrors('items.0.qty');

        $this->assertSame(0, SalesItem::count());
        $this->assertSame(5000, $this->stock($this->wire));
        $this->assertSame(200, $this->stock($this->rj45));
    }

    public function test_the_same_product_on_two_cart_lines_is_checked_as_one(): void
    {
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 2500], ['id' => $this->wire->ProductID, 'qty' => 2500.5]], 60000)->assertStatus(400);
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 2], ['id' => $this->wire->ProductID, 'qty' => 0.5]], 25)->assertOk()->assertJsonPath('total', 25);

        $this->assertSame(1, SalesItem::count());
        $this->assertSame(2.5, SalesItem::firstOrFail()->Quantity);
        $this->assertSame(4997.5, $this->stock($this->wire));
    }

    public function test_a_promo_applies_to_the_price_of_the_unit_sold(): void
    {
        Inventory::where('ProductID', $this->rj45->ProductID)->update(['Quantity' => 200]);
        $promo = Discount::create(['Name' => 'Box Sale', 'PromoCode' => 'BOX10', 'DiscountType' => 'percentage', 'DiscountRate' => 10, 'StartDate' => now()->subDay(), 'EndDate' => now()->addDay(), 'Status' => 'active']);
        $promo->products()->attach($this->rj45->ProductID);

        // 10% off ₱450 per box.
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 2, 'unit' => 'box']], 810)->assertOk()->assertJsonPath('total', 810);
        $this->assertEquals(90, SalesItem::firstOrFail()->DiscountAmount);
    }

    public function test_purchase_orders_are_ordered_and_received_by_the_roll(): void
    {
        $supplier = Supplier::create(['SupplierName' => 'Cable Depot', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);

        // The order form offers rolls, priced per roll (₱6/meter x 5,000).
        $this->assertSame(30000.0, collect(PurchaseOrderController::orderItemCandidates())->firstWhere('product.ProductID', $this->wire->ProductID)['previousPrice']);

        $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'PurchaseDate' => now()->toDateString(),
            'items' => [['product_id' => $this->wire->ProductID, 'quantity' => 2, 'cost_price' => 30000, 'supplier_id' => $supplier->SupplierID]],
        ])->assertRedirect();

        $line = PurchaseOrderItem::firstOrFail();
        $this->assertSame(['roll', 5000, 2], [$line->Unit, $line->QuantityPerUnit, $line->Quantity]);
        $this->assertSame('2 rolls (10,000 meters)', $line->quantityLabel());

        // Printing sends it to Stock Receiving; 1 roll arrives, then the other.
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $line->purchaseOrder));
        $batch = StockReceivingBatch::firstOrFail();
        $receive = fn (int $rolls, string $receipt) => $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => $receipt, 'items' => [['purchase_order_item_id' => $line->PurchaseOrderItemID, 'quantity_received' => $rolls]],
        ]);

        $receive(1, 'DR-1')->assertSessionHas('success');
        $this->assertSame(10000, $this->stock($this->wire));
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $line->purchaseOrder->fresh()->Status);

        $receive(1, 'DR-2')->assertSessionHas('success');
        $this->assertSame(15000, $this->stock($this->wire));
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, $line->purchaseOrder->fresh()->Status);
    }

    public function test_reordering_suggests_whole_rolls(): void
    {
        // 500-meter threshold with 200 meters left: 800 meters short -> 1 roll.
        $this->assertSame(1, PurchaseOrderController::suggestedOrderQuantity($this->wire, 200, 500));
        // 12,000 meters short -> 3 rolls.
        $this->assertSame(3, PurchaseOrderController::suggestedOrderQuantity($this->wire, 0, 6000));
        $this->assertSame(30, PurchaseOrderController::suggestedOrderQuantity(Product::create(['ProductName' => 'Camera', 'Model' => 'C-1', 'Price' => 100, 'CategoryID' => $this->category->CategoryID]), 0, 15));
    }

    public function test_stock_adjustments_are_in_the_base_unit_with_decimals_only_where_allowed(): void
    {
        $adjust = fn (Product $product, $quantity) => $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), [
            'ProductID' => $product->ProductID, 'QuantityAdjust' => $quantity, 'Reason' => StockAdjustment::REASON_COUNT_ERROR, 'Date' => now()->toDateString(),
        ]);

        $adjust($this->wire, -0.5)->assertSessionHasNoErrors();
        $this->assertSame(4999.5, $this->stock($this->wire));
        $this->assertSame(-0.5, StockAdjustment::firstOrFail()->QuantityAdjust);

        $adjust($this->rj45, 0.5)->assertSessionHasErrors('QuantityAdjust');
        $adjust($this->rj45, 0)->assertSessionHasErrors('QuantityAdjust');
        $this->assertSame(100, $this->stock($this->rj45));
    }

    public function test_a_box_returned_as_damaged_goes_to_damage_as_its_100_pieces(): void
    {
        Inventory::where('ProductID', $this->rj45->ProductID)->update(['Quantity' => 200]);
        $this->sell([['id' => $this->rj45->ProductID, 'qty' => 1, 'unit' => 'box']], 450)->assertOk();
        $transactionId = SalesItem::firstOrFail()->SalesTransactionID;

        $this->actingAs($this->cashier)->getJson(route('cashier.refunds.transaction', $transactionId))->assertOk()
            ->assertJsonPath('transaction.items.0.Unit', 'box')
            ->assertJsonPath('transaction.items.0.RemainingReturnableQty', 1)
            ->assertJsonPath('transaction.items.0.AllowsDecimals', false);

        // Part of a box can't come back; the whole box can.
        $request = fn ($quantity) => $this->actingAs($this->cashier)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $transactionId, 'return_type' => 'refund',
            'items' => [['product_id' => $this->rj45->ProductID, 'quantity' => $quantity, 'reason_code' => 'damaged_product']],
        ]);
        $request(0.5)->assertStatus(400);
        $request(1)->assertOk()->assertJsonPath('refund_amount', 450);

        $returnItem = SalesReturnItem::firstOrFail();
        $this->assertSame([1, 'box', 100], [$returnItem->Quantity, $returnItem->Unit, $returnItem->BaseQuantity]);

        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $returnItem->salesReturn));
        $this->assertSame(100, DamagedProduct::firstOrFail()->Quantity);
    }

    public function test_meters_can_be_returned_by_part_of_a_meter(): void
    {
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 3]], 30)->assertOk();
        $transactionId = SalesItem::firstOrFail()->SalesTransactionID;

        $this->actingAs($this->cashier)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $transactionId, 'return_type' => 'refund',
            'items' => [['product_id' => $this->wire->ProductID, 'quantity' => 1.5, 'reason_code' => 'factory_defect']],
        ])->assertOk()->assertJsonPath('refund_amount', 15);

        // What's left to return on that sale follows.
        $this->actingAs($this->cashier)->getJson(route('cashier.refunds.transaction', $transactionId))->assertOk()
            ->assertJsonPath('transaction.items.0.RemainingReturnableQty', 1.5)
            ->assertJsonPath('transaction.items.0.AllowsDecimals', true);
    }

    public function test_reports_add_up_quantities_per_unit(): void
    {
        $this->sell([['id' => $this->wire->ProductID, 'qty' => 1.5], ['id' => $this->rj45->ProductID, 'qty' => 10]], 65)->assertOk();

        $summary = ReportSummaryBuilder::forType('sales', collect([
            (object) ['is_first' => true, 'Quantity' => 1.5, 'Unit' => 'meter', 'ItemTotal' => 15, 'Discount' => 0, 'VatAmount' => 0, 'BillingAmount' => 65],
            (object) ['is_first' => false, 'Quantity' => 10, 'Unit' => 'piece', 'ItemTotal' => 50, 'Discount' => null, 'VatAmount' => null, 'BillingAmount' => null],
        ]));
        $this->assertSame('1.5 meters, 10 pieces', ReportSummaryBuilder::formatValue($summary[1]));

        // Units sold count the base unit (Product Ranking, Inventory velocity).
        $this->actingAs($this->admin)->get(route('admin.inventory.index'))->assertOk()->assertSee('meters / 30d');
        $this->assertSame('1.5 meters', UnitOfMeasure::cell(1.5, 'meter'));
        $this->assertSame('10', UnitOfMeasure::cell(10, 'piece'));
    }
}
