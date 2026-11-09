<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceiving;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Panel suggestion: a new purchase order goes to the supplier that is
// currently delivering the product (its latest delivery), and the product
// details show who that is.
class CurrentSupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $preferred;

    private Supplier $other;

    private Product $camera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->preferred = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0912-345-6789', 'Address' => 'N/A']);
        $this->other = Supplier::create(['SupplierName' => 'Bravo Trading', 'ContactNumber' => '0912-000-0000', 'Address' => 'N/A']);

        $category = Category::create(['CategoryName' => 'CCTV']);
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        Inventory::create(['ProductID' => $this->camera->ProductID, 'Quantity' => 0, 'Status' => 'Out of Stock']);
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->preferred->SupplierID, 'CostPrice' => 600, 'IsPreferred' => true]);
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->other->SupplierID, 'CostPrice' => 580]);
    }

    private function receiveFrom(Supplier $supplier): void
    {
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-'.str_pad((string) (PurchaseOrder::count() + 1), 6, '0', STR_PAD_LEFT), 'PurchaseDate' => now()->toDateString(),
            'Status' => PurchaseOrder::STATUS_DRAFT, 'SupplierID' => $supplier->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        $line = PurchaseOrderItem::create(['PurchaseOrderID' => $order->PurchaseOrderID, 'ProductID' => $this->camera->ProductID, 'Quantity' => 5, 'CostPriceAtOrder' => 580]);

        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $order));
        $batch = StockReceivingBatch::where('PurchaseOrderID', $order->PurchaseOrderID)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'DR-'.$order->PurchaseOrderID,
            'items' => [['purchase_order_item_id' => $line->PurchaseOrderItemID, 'quantity_received' => 5]],
        ])->assertSessionMissing('error');
    }

    private function productDetails(): string
    {
        return $this->actingAs($this->admin)->getJson(route('admin.products.show', $this->camera))->assertOk()->json('html');
    }

    private function createPurchaseOrderSupplier(): ?int
    {
        return PurchaseOrderController::orderItemCandidates()->firstWhere('product.ProductID', $this->camera->ProductID)['supplier']?->SupplierID;
    }

    public function test_before_any_delivery_the_preferred_supplier_is_used(): void
    {
        $this->assertSame($this->preferred->SupplierID, $this->camera->fresh()->resolveReorderSupplier()->SupplierID);
        $this->assertSame($this->preferred->SupplierID, $this->createPurchaseOrderSupplier());

        $html = $this->productDetails();
        $this->assertStringContainsString('<div><span>Current Supplier</span><span>No delivery yet</span></div>', $html);
        $this->assertMatchesRegularExpression('/<span>Suppliers<\/span><span>Acme Supplies <small>\(Preferred\)<\/small>\s*<br>\s*Bravo Trading/', $html);
    }

    public function test_the_supplier_that_delivered_last_is_picked_for_the_next_order(): void
    {
        $this->receiveFrom($this->other);

        $this->assertSame($this->other->SupplierID, $this->camera->fresh()->resolveReorderSupplier()->SupplierID);
        $this->assertSame($this->other->SupplierID, $this->createPurchaseOrderSupplier());
        $this->assertStringContainsString('<div><span>Current Supplier</span><span>Bravo Trading</span></div>', $this->productDetails());

        // The next delivery comes from the preferred one again.
        $this->travel(1)->days();
        $this->receiveFrom($this->preferred);
        $this->assertSame($this->preferred->SupplierID, $this->camera->fresh()->resolveReorderSupplier()->SupplierID);
        $this->assertStringContainsString('<div><span>Current Supplier</span><span>Acme Supplies</span></div>', $this->productDetails());
    }

    public function test_a_receipt_from_the_older_stock_receiving_form_counts(): void
    {
        StockReceiving::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->other->SupplierID, 'Quantity' => 5, 'DateReceived' => now()->toDateString(), 'ReceiptNumber' => 'OLD-001']);

        $this->assertSame($this->other->SupplierID, $this->camera->fresh()->lastDeliveredSupplierId());
        $this->assertSame($this->other->SupplierID, $this->camera->fresh()->resolveReorderSupplier()->SupplierID);
    }

    public function test_a_supplier_set_inactive_since_its_delivery_is_not_ordered_from(): void
    {
        $this->receiveFrom($this->other);
        $this->other->update(['Status' => Supplier::STATUS_INACTIVE]);

        $this->assertSame($this->preferred->SupplierID, $this->camera->fresh()->resolveReorderSupplier()->SupplierID);
    }
}
