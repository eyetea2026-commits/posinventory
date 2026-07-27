<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockReceiving;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Supplier $supplier;

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
        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
    }

    private function makeOrder(string $ponumber, array $overrides = []): PurchaseOrder
    {
        $po = PurchaseOrder::create(array_merge([
            'PONumber' => $ponumber,
            'PurchaseDate' => now()->format('Y-m-d'),
            'ExpectedDeliveryDate' => now()->addDays(5)->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_FULLY_RECEIVED,
            'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ], $overrides));

        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 10, 'ReceivedQuantity' => 10, 'CostPriceAtOrder' => 600,
        ]);

        return $po;
    }

    public function test_supplier_list_opens_history_via_modal_instead_of_navigation(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.index'));

        $response->assertOk();
        $response->assertSee('openSupplierHistoryModal(' . $this->supplier->SupplierID . ',', false);
        $response->assertSee('id="supplierHistoryModal"', false);
        // No direct navigation link to the standalone history page anymore.
        $response->assertDontSee('href="' . route('admin.suppliers.show', $this->supplier) . '"', false);
    }

    public function test_history_page_lists_transactions_with_required_columns(): void
    {
        $this->makeOrder('PO-2026-000001');

        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.show', $this->supplier));

        $response->assertOk();
        $response->assertSee('PO-2026-000001');
        $response->assertSee('Supplier History');
        $response->assertDontSee('Supplier Profile');
    }

    public function test_history_search_filters_by_po_number_via_ajax(): void
    {
        $this->makeOrder('PO-2026-000001');
        $this->makeOrder('PO-2026-000002');

        $response = $this->actingAs($this->admin)->getJson(route('admin.suppliers.show', $this->supplier) . '?search=000002');

        $response->assertOk();
        $this->assertStringContainsString('PO-2026-000002', $response->json('rows'));
        $this->assertStringNotContainsString('PO-2026-000001', $response->json('rows'));
    }

    public function test_delivery_status_reflects_on_time_vs_late(): void
    {
        $onTimePo = $this->makeOrder('PO-2026-000003');
        StockReceiving::create([
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 10, 'ReceiptNumber' => 'RCV-1', 'DateReceived' => now()->format('Y-m-d'),
            'PurchaseOrderID' => $onTimePo->PurchaseOrderID,
        ]);

        $latePo = $this->makeOrder('PO-2026-000004', ['ExpectedDeliveryDate' => now()->subDays(2)->format('Y-m-d')]);
        StockReceiving::create([
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 10, 'ReceiptNumber' => 'RCV-2', 'DateReceived' => now()->format('Y-m-d'),
            'PurchaseOrderID' => $latePo->PurchaseOrderID,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.show', $this->supplier));

        $response->assertOk();
        $response->assertSeeInOrder(['PO-2026-000004', 'Late']);
        $response->assertSeeInOrder(['PO-2026-000003', 'On Time']);
    }

    public function test_purchase_order_details_endpoint_returns_full_breakdown(): void
    {
        $po = $this->makeOrder('PO-2026-000005');

        $response = $this->actingAs($this->admin)->getJson(route('admin.suppliers.purchase-order-details', [$this->supplier, $po]));

        $response->assertOk();
        $response->assertJsonPath('purchaseOrder.PONumber', 'PO-2026-000005');
        $response->assertJsonPath('purchaseOrder.CreatedBy', $this->admin->full_name);
        $response->assertJsonPath('items.0.ProductName', 'DVR Camera');
        $response->assertJsonPath('items.0.Category', 'CCTV');
        $response->assertJsonPath('items.0.SKU', 'SKU-001');
        $response->assertJsonPath('summary.TotalQuantityOrdered', 10);
        $response->assertJsonPath('summary.TotalPurchaseAmount', 6000);
    }

    public function test_purchase_order_details_rejects_a_po_belonging_to_another_supplier(): void
    {
        $otherSupplier = Supplier::create(['SupplierName' => 'Other', 'ContactNumber' => '1', 'Email' => 'o@example.com', 'Address' => 'N/A']);
        $po = $this->makeOrder('PO-2026-000006');

        $response = $this->actingAs($this->admin)->getJson(route('admin.suppliers.purchase-order-details', [$otherSupplier, $po]));

        $response->assertNotFound();
    }

    public function test_created_by_is_recorded_when_a_purchase_order_is_created(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => 'draft',
            'products' => [
                ['product_id' => $this->product->ProductID, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $this->assertDatabaseHas('PurchaseOrder', ['CreatedBy' => $this->admin->id]);
    }
}
