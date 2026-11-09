<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceivingBatch;
use App\Models\StockReceivingDeliveryItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Panel suggestions:
// - a product has several suppliers, each with its own price, and a new
//   purchase order goes to the one with the lowest current price;
// - Stock Receiving says how many arrived damaged (sent back to the
//   supplier, never added to stock);
// - each delivery is a stock batch with its cost, selling price and margin.
class SupplierPricesAndBatchesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $acme;

    private Supplier $bravo;

    private Category $category;

    private Product $camera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->acme = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);
        $this->bravo = Supplier::create(['SupplierName' => 'Bravo Trading', 'ContactNumber' => '0912-000-0000', 'Address' => 'N/A']);

        $this->category = Category::create(['CategoryName' => 'CCTV']);
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        Inventory::create(['ProductID' => $this->camera->ProductID, 'Quantity' => 0, 'Status' => 'Out of Stock']);
    }

    /**
     * @param  array{good: int, damaged?: int}  $quantities
     */
    private function receive(Supplier $supplier, float $price, array $quantities, int $ordered = 10)
    {
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-'.str_pad((string) (PurchaseOrder::count() + 1), 6, '0', STR_PAD_LEFT), 'PurchaseDate' => now()->toDateString(),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $supplier->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        $line = PurchaseOrderItem::create(['PurchaseOrderID' => $order->PurchaseOrderID, 'ProductID' => $this->camera->ProductID, 'Quantity' => $ordered, 'CostPriceAtOrder' => $price]);

        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $order));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $order->PurchaseOrderID)->firstOrFail();

        return $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'DR-'.$order->PurchaseOrderID,
            'items' => [['purchase_order_item_id' => $line->PurchaseOrderItemID, 'quantity_received' => $quantities['good'], 'quantity_damaged' => $quantities['damaged'] ?? 0]],
        ]);
    }

    private function stock(): int
    {
        return (int) Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity');
    }

    public function test_a_new_product_is_saved_with_a_price_for_each_supplier(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.products.store'), [
            'ProductName' => 'Bullet Camera', 'Model' => 'BC-1', 'Barcode' => 'CCTVEX-000002',
            'CostPrice' => 700, 'Price' => 1200, 'CategoryID' => $this->category->CategoryID,
            'Suppliers' => [
                ['SupplierID' => $this->acme->SupplierID, 'CostPrice' => 650],
                ['SupplierID' => $this->bravo->SupplierID],
            ],
        ])->assertCreated();

        $product = Product::where('ProductName', 'Bullet Camera')->firstOrFail();
        $this->assertSame(650.0, (float) ProductSupplier::where('ProductID', $product->ProductID)->where('SupplierID', $this->acme->SupplierID)->value('CostPrice'));
        $this->assertNull(ProductSupplier::where('ProductID', $product->ProductID)->where('SupplierID', $this->bravo->SupplierID)->value('CostPrice'));
        // Acme at 650 is cheaper than Bravo at the product's 700.
        $this->assertSame($this->acme->SupplierID, $product->resolveReorderSupplier()->SupplierID);
    }

    public function test_a_suppliers_price_can_be_changed_and_too_many_digits_are_refused(): void
    {
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->acme->SupplierID, 'CostPrice' => 600]);

        $this->actingAs($this->admin)->postJson(route('admin.products.suppliers.store', $this->camera), ['SupplierID' => $this->acme->SupplierID, 'CostPrice' => 100000])
            ->assertStatus(422)->assertJsonValidationErrors(['CostPrice' => 'The Cost Price can only have up to 5 digits (₱99,999.99).']);

        $this->actingAs($this->admin)->postJson(route('admin.products.suppliers.store', $this->camera), ['SupplierID' => $this->acme->SupplierID, 'CostPrice' => 575])->assertOk();
        $this->assertSame(575.0, (float) ProductSupplier::where('SupplierID', $this->acme->SupplierID)->value('CostPrice'));
        $this->assertSame(1, ProductCostHistory::where('Source', ProductCostHistory::SOURCE_SUPPLIER_PIVOT_UPDATE)->where('NewCostPrice', 575)->count());
    }

    public function test_create_purchase_order_picks_the_cheapest_supplier_and_shows_each_price(): void
    {
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->acme->SupplierID, 'CostPrice' => 600, 'IsPreferred' => true]);
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->bravo->SupplierID, 'CostPrice' => 550]);

        $row = PurchaseOrderController::orderItemCandidates()->firstWhere('product.ProductID', $this->camera->ProductID);
        $this->assertSame($this->bravo->SupplierID, $row['supplier']->SupplierID);
        $this->assertSame(550.0, $row['currentPrice']);

        $html = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="'.$this->bravo->SupplierID.'" data-price="550.00" selected>Bravo Trading — ₱550.00</option>', $html);
        $this->assertStringContainsString('<option value="'.$this->acme->SupplierID.'" data-price="600.00" >Acme Supplies ★ — ₱600.00</option>', $html);
        $this->assertStringContainsString('window.poSupplierPicked = function (select) {', $html);
    }

    public function test_damaged_items_on_arrival_go_to_the_damage_module_not_to_stock(): void
    {
        $this->receive($this->acme, 580, ['good' => 8, 'damaged' => 2])->assertSessionMissing('error');

        // Only the good ones are stocked; the damaged ones still count as delivered.
        $this->assertSame(8, $this->stock());
        $line = PurchaseOrderItem::firstOrFail();
        $this->assertSame(10, (int) $line->ReceivedQuantity);
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, PurchaseOrder::firstOrFail()->Status);

        $damage = DamagedProduct::firstOrFail();
        $this->assertSame(DamagedProduct::SOURCE_STOCK_RECEIVING, $damage->SourceModule);
        $this->assertSame(DamagedProduct::STATUS_FOR_SUPPLIER_RETURN, $damage->Status);
        $this->assertSame(2, (int) $damage->Quantity);
        $this->assertSame($this->acme->SupplierID, $damage->SupplierID);
        $this->assertSame(PurchaseOrder::firstOrFail()->PurchaseOrderID, $damage->PurchaseOrderID);
        $this->assertStringContainsString('Damaged on arrival — PO PO-TEST-000001', $damage->Description);
        $this->assertSame(2, StockReceivingDeliveryItem::firstOrFail()->DamagedQuantity);
        $this->assertSame('Stock Receiving (Damaged on Arrival)', DamagedProduct::SOURCE_LABELS[$damage->SourceModule]);
    }

    public function test_good_and_damaged_together_cannot_pass_what_is_owed(): void
    {
        $this->receive($this->acme, 580, ['good' => 9, 'damaged' => 2])
            ->assertSessionHas('error', 'Good and damaged "Dome Camera" together can be at most 10 (still owed on this order).');

        $this->assertSame(0, $this->stock());
        $this->assertSame(0, DamagedProduct::count());
    }

    public function test_a_delivery_that_is_all_damaged_still_counts(): void
    {
        $this->receive($this->acme, 580, ['good' => 0, 'damaged' => 3], 3)->assertSessionMissing('error');

        $this->assertSame(0, $this->stock());
        $this->assertSame(3, (int) DamagedProduct::firstOrFail()->Quantity);
        $this->assertSame(PurchaseOrder::STATUS_FULLY_RECEIVED, PurchaseOrder::firstOrFail()->Status);
    }

    public function test_each_delivery_is_a_batch_with_its_cost_selling_price_and_margin(): void
    {
        $this->receive($this->acme, 580, ['good' => 10])->assertSessionMissing('error');
        $this->camera->update(['ProfitMargin' => 45]);
        $this->receive($this->bravo, 620, ['good' => 4, 'damaged' => 1], 5)->assertSessionMissing('error');

        [$first, $second] = StockReceivingDeliveryItem::orderBy('StockReceivingDeliveryItemID')->get()->all();
        $this->assertSame('580.00', (string) $first->UnitCost);
        // Selling Price = the batch's own cost / (1 - the product's margin).
        $this->assertSame('966.67', (string) $first->SellingPrice);
        $this->assertSame(40.0, $first->marginPercent());
        $this->assertSame('620.00', (string) $second->UnitCost);
        $this->assertSame('1127.27', (string) $second->SellingPrice);
        $this->assertSame(45.0, $second->marginPercent());

        // The delivering supplier's price follows what it was ordered at.
        $this->assertSame(580.0, (float) ProductSupplier::where('SupplierID', $this->acme->SupplierID)->value('CostPrice'));
        $this->assertSame(620.0, (float) ProductSupplier::where('SupplierID', $this->bravo->SupplierID)->value('CostPrice'));
        $this->assertSame(2, ProductCostHistory::where('Source', ProductCostHistory::SOURCE_PO_RECEIVING)->count());

        $html = $this->actingAs($this->admin)->getJson(route('admin.inventory.show', $this->camera))->assertOk()->json('html');
        $this->assertStringContainsString('Stock Batches', $html);
        // Received, Damaged, Sold, Remaining, Unit Cost, Selling Price, Margin.
        $this->assertMatchesRegularExpression('/Bravo Trading<\/td>\s*<td>4 pieces<\/td>\s*<td>1 piece<\/td>\s*<td>0 pieces<\/td>\s*<td><strong>4 pieces<\/strong><\/td>\s*<td[^>]*>₱620\.00<\/td>\s*<td[^>]*>₱1,127\.27<\/td>\s*<td>45\.0%<\/td>/u', $html);
        $this->assertStringContainsString('Margin = (Selling Price − Unit Cost) ÷ Selling Price.', $html);
    }

    public function test_an_order_line_priced_at_zero_does_not_change_the_suppliers_price(): void
    {
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->acme->SupplierID, 'CostPrice' => 600]);

        $this->receive($this->acme, 0, ['good' => 10])->assertSessionMissing('error');

        $this->assertSame(600.0, (float) ProductSupplier::where('SupplierID', $this->acme->SupplierID)->value('CostPrice'));
        $this->assertSame(0, ProductCostHistory::where('Source', ProductCostHistory::SOURCE_PO_RECEIVING)->count());
    }

    public function test_the_receiving_form_has_a_damaged_column(): void
    {
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001', 'PurchaseDate' => now()->toDateString(),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $this->acme->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        PurchaseOrderItem::create(['PurchaseOrderID' => $order->PurchaseOrderID, 'ProductID' => $this->camera->ProductID, 'Quantity' => 10, 'CostPriceAtOrder' => 580]);
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $order));
        $batch = StockReceivingBatch::firstOrFail();

        $html = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch))->json('html');
        $this->assertStringContainsString('Damaged <small style="font-weight:400;">(return to supplier)</small>', $html);
        $this->assertStringContainsString('name="items[0][quantity_damaged]" min="0" max="10" value="0"', $html);
    }
}
