<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceivingBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Stock Receiving: when the supplier has no stock, what's still owed on the
// ticked products is cancelled on that order and ordered from another
// supplier as a new Draft purchase order.
class ReorderFromAnotherSupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Supplier $otherSupplier;

    private Product $camera;

    private Product $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Address' => 'N/A']);
        $this->otherSupplier = Supplier::create(['SupplierName' => 'Beta Trading', 'ContactNumber' => '1111', 'Address' => 'N/A']);
        $category = Category::create(['CategoryName' => 'CCTV']);
        $this->camera = Product::create(['ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID]);
        $this->recorder = Product::create(['ProductName' => 'NVR 8CH', 'Model' => 'NVR-08', 'Price' => 2000, 'CostPrice' => 1200, 'CategoryID' => $category->CategoryID]);
        Inventory::create(['ProductID' => $this->camera->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);
        Inventory::create(['ProductID' => $this->recorder->ProductID, 'Quantity' => 2, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);
    }

    /**
     * A printed order (in Stock Receiving) for 50 cameras and 20 recorders.
     */
    private function sentOrder(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-2026-000001', 'PurchaseDate' => now()->toDateString(), 'Status' => PurchaseOrder::STATUS_DRAFT,
            'SupplierID' => $this->supplier->SupplierID, 'CreatedBy' => $this->admin->id,
        ]);
        PurchaseOrderItem::create(['PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->camera->ProductID, 'Quantity' => 50, 'CostPriceAtOrder' => 600]);
        PurchaseOrderItem::create(['PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->recorder->ProductID, 'Quantity' => 20, 'CostPriceAtOrder' => 1200]);
        $this->actingAs($this->admin)->get(route('admin.purchase-orders.print', $po));

        return $po->fresh();
    }

    private function line(PurchaseOrder $po, Product $product): PurchaseOrderItem
    {
        return PurchaseOrderItem::where('PurchaseOrderID', $po->PurchaseOrderID)->where('ProductID', $product->ProductID)->firstOrFail();
    }

    private function reorder(PurchaseOrder $po, array $products, ?Supplier $supplier = null, string $reason = 'Supplier has no stock')
    {
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();

        return $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.reorder', $batch), [
            'supplier_id' => ($supplier ?? $this->otherSupplier)->SupplierID,
            'items' => array_map(fn (Product $product) => $this->line($po, $product)->PurchaseOrderItemID, $products),
            'reason' => $reason,
        ]);
    }

    public function test_reordering_everything_cancels_the_order_and_drafts_one_for_the_new_supplier(): void
    {
        $po = $this->sentOrder();

        $this->reorder($po, [$this->camera, $this->recorder])
            ->assertRedirect(route('admin.stock-receivings.index'))
            ->assertSessionHas('success');

        $po->refresh();
        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $po->Status);
        $this->assertSame(50, (int) $this->line($po, $this->camera)->CancelledQuantity);
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->value('Status'));
        $this->assertStringStartsWith('Reordered from Beta Trading as PO-', $po->RemainderCancelReason);

        $draft = PurchaseOrder::with('items')->where('SupplierID', $this->otherSupplier->SupplierID)->firstOrFail();
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $draft->Status);
        $this->assertSame([50, 20], $draft->items->sortBy('ProductID')->pluck('Quantity')->map(fn ($q) => (int) $q)->values()->all());
        $this->assertEquals(600, (float) $draft->items->firstWhere('ProductID', $this->camera->ProductID)->CostPriceAtOrder);
        $this->assertSame('Reorder of PO-2026-000001 (Acme Supplies): Supplier has no stock', $draft->Notes);
        // A draft isn't in Stock Receiving until it's printed.
        $this->assertNull(StockReceivingBatch::where('PurchaseOrderID', $draft->PurchaseOrderID)->first());
        $this->assertSame(5, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
    }

    public function test_part_of_a_partly_delivered_order_can_be_reordered(): void
    {
        $po = $this->sentOrder();
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.stock-receivings.batches.add-to-inventory', $batch), [
            'receipt_number' => 'DR-1',
            'items' => [
                ['purchase_order_item_id' => $this->line($po, $this->camera)->PurchaseOrderItemID, 'quantity_received' => 20],
                ['purchase_order_item_id' => $this->line($po, $this->recorder)->PurchaseOrderItemID, 'quantity_received' => 0],
            ],
        ])->assertSessionHasNoErrors();

        // Only the recorders come from someone else; 30 cameras are still owed here.
        $this->reorder($po, [$this->recorder])->assertSessionHas('success');
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->Status);
        $this->assertSame(StockReceivingBatch::STATUS_PENDING, $batch->fresh()->Status);
        $this->assertSame(20, (int) PurchaseOrderItem::where('ProductID', $this->recorder->ProductID)->where('PurchaseOrderID', '!=', $po->PurchaseOrderID)->value('Quantity'));

        // Then the rest of the cameras too: nothing more is coming, and part arrived, so Closed.
        $this->reorder($po, [$this->camera])->assertSessionHas('success');
        $this->assertSame(PurchaseOrder::STATUS_CLOSED, $po->fresh()->Status);
        $this->assertSame(StockReceivingBatch::STATUS_COMPLETED, $batch->fresh()->Status);
        $this->assertSame(30, (int) PurchaseOrderItem::where('ProductID', $this->camera->ProductID)->where('PurchaseOrderID', '!=', $po->PurchaseOrderID)->value('Quantity'));
        $this->assertSame(25, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
    }

    public function test_the_new_suppliers_own_price_is_used_when_known(): void
    {
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->otherSupplier->SupplierID, 'CostPrice' => 550]);
        $po = $this->sentOrder();

        $this->reorder($po, [$this->camera])->assertSessionHas('success');

        $draftLine = PurchaseOrderItem::where('ProductID', $this->camera->ProductID)->where('PurchaseOrderID', '!=', $po->PurchaseOrderID)->firstOrFail();
        $this->assertEquals(550, (float) $draftLine->CostPriceAtOrder);
    }

    public function test_a_reorder_needs_another_active_supplier_a_product_and_a_reason(): void
    {
        $po = $this->sentOrder();

        $this->reorder($po, [$this->camera], $this->supplier)->assertSessionHas('error', 'Choose a different supplier from the one on this order.');
        $this->otherSupplier->update(['Status' => Supplier::STATUS_INACTIVE]);
        $this->reorder($po, [$this->camera])->assertSessionHas('error', '"Beta Trading" is inactive. Choose an active supplier.');
        $this->otherSupplier->update(['Status' => Supplier::STATUS_ACTIVE]);
        $this->reorder($po, [])->assertSessionHas('error', 'Tick at least one product to reorder.');
        $this->reorder($po, [$this->camera], null, '')->assertSessionHas('error');

        $this->assertSame(1, PurchaseOrder::count());
        $this->assertSame(0, (int) $this->line($po, $this->camera)->CancelledQuantity);
        $this->assertSame(PurchaseOrder::STATUS_PENDING, $po->fresh()->Status);
    }

    public function test_the_option_shows_only_on_deliveries_still_waiting(): void
    {
        $po = $this->sentOrder();
        $batch = StockReceivingBatch::where('PurchaseOrderID', $po->PurchaseOrderID)->firstOrFail();
        ProductSupplier::create(['ProductID' => $this->camera->ProductID, 'SupplierID' => $this->otherSupplier->SupplierID]);

        $details = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch))->assertOk();
        $this->assertTrue($details->json('canReorder'));
        $html = $details->json('html');
        $this->assertStringContainsString('id="reorderElsewhereForm"', $html);
        $this->assertMatchesRegularExpression('/<optgroup label="Supplies these products">\s*<option value="'.$this->otherSupplier->SupplierID.'">Beta Trading<\/option>/', $html);
        $this->assertStringNotContainsString('<option value="'.$this->supplier->SupplierID.'">', $html);
        $this->actingAs($this->admin)->get(route('admin.stock-receivings.index'))->assertOk()
            ->assertSee('id="reorderElsewhereBtn"', false);

        $this->reorder($po, [$this->camera, $this->recorder]);
        $completed = $this->actingAs($this->admin)->getJson(route('admin.stock-receivings.batches.show', $batch))->assertOk();
        $this->assertFalse($completed->json('canReorder'));
        $this->assertStringNotContainsString('id="reorderElsewhereForm"', $completed->json('html'));
    }
}
