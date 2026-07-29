<?php

namespace Tests\Feature\Admin;

use App\Models\Billing;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Supplier $supplier;
    private Staff $staff;

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
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 8, 'ReorderThreshold' => 5, 'Status' => 'Available']);

        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $cashierUser = User::factory()->create(['role_id' => $cashierRole->id, 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $this->staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $cashierUser->id,
        ]);
    }

    private function fetchDetails(string $type, int $id)
    {
        return $this->actingAs($this->admin)->getJson(route('admin.reports.details', ['type' => $type, 'id' => $id]));
    }

    public function test_sales_report_details_returns_full_breakdown(): void
    {
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => now(), 'StaffID' => $this->staff->StaffID,
        ]);
        SalesItem::create(['Quantity' => 2, 'UnitPrice' => 1000, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $transaction->SalesTransactionID]);
        $discount = Discount::firstOrCreate(['DiscountRate' => 0]);
        $billing = Billing::create([
            'CustomerName' => 'Walk-in Customer', 'VatApplied' => '12%', 'BillingAmount' => 2000,
            'BillingDate' => now(), 'DiscountID' => $discount->DiscountID, 'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
        Payment::create(['PaymentAmount' => 2000, 'PaymentMethod' => 'cash', 'ReceiptNumber' => 'RCT-000001', 'BillingID' => $billing->BillingID]);

        $response = $this->fetchDetails('sales', $billing->BillingID);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Cashier Name', 'value' => 'Jane Doe']);
        $response->assertJsonFragment(['label' => 'Receipt Number', 'value' => 'RCT-000001']);
        $response->assertJsonFragment(['label' => 'Payment Method', 'value' => 'cash']);
        $this->assertStringContainsString('DVR Camera', $response->getContent());
    }

    public function test_inventory_report_details_computes_stock_movement(): void
    {
        \App\Models\StockReceiving::create([
            'Quantity' => 20, 'DateReceived' => now()->format('Y-m-d'), 'ReceiptNumber' => 'RCPT-1',
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
        ]);

        $response = $this->fetchDetails('inventory', $this->product->ProductID);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Product Name', 'value' => 'DVR Camera']);
        $response->assertJsonFragment(['label' => 'SKU', 'value' => 'SKU-001']);
        $response->assertJsonFragment(['label' => 'Stock In (All Time)', 'value' => '20']);
    }

    public function test_purchase_order_report_details_includes_items_and_status(): void
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_FULLY_RECEIVED, 'SupplierID' => $this->supplier->SupplierID,
            'CreatedBy' => $this->admin->id,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 10, 'ReceivedQuantity' => 10, 'CostPriceAtOrder' => 600,
        ]);

        $response = $this->fetchDetails('orders', $po->PurchaseOrderID);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Supplier', 'value' => 'Acme Supplies']);
        $response->assertJsonFragment(['label' => 'Received Status', 'value' => 'Fully Received']);
        $this->assertStringContainsString('DVR Camera', $response->getContent());
    }

    public function test_return_report_details_includes_requested_by_and_items(): void
    {
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => now(), 'StaffID' => $this->staff->StaffID,
        ]);
        $return = SalesReturn::create([
            'SalesTransactionID' => $transaction->SalesTransactionID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'Reason' => 'Factory Defect', 'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'), 'Status' => 'pending', 'StaffID' => $this->staff->StaffID,
        ]);
        SalesReturnItem::create([
            'SalesReturnID' => $return->SalesReturnID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'UnitPrice' => 1000, 'Reason' => 'Factory Defect',
        ]);

        $response = $this->fetchDetails('returns', $return->SalesReturnID);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Requested By (Cashier)', 'value' => 'Jane Doe']);
        $response->assertJsonFragment(['label' => 'Return Status', 'value' => 'Pending']);
        $this->assertStringContainsString('DVR Camera', $response->getContent());
    }

    public function test_damage_report_details_returns_product_and_status(): void
    {
        $damage = DamagedProduct::create([
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 2, 'Description' => 'Cracked casing', 'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken', 'Status' => DamagedProduct::STATUS_PENDING, 'SourceModule' => DamagedProduct::SOURCE_MANUAL,
        ]);

        $response = $this->fetchDetails('damage', $damage->DamageID);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Product', 'value' => 'DVR Camera']);
        $response->assertJsonFragment(['label' => 'Quantity Damaged', 'value' => '2']);
    }

    public function test_supplier_report_details_returns_orders_table(): void
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000002', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_FULLY_RECEIVED, 'SupplierID' => $this->supplier->SupplierID,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 5, 'ReceivedQuantity' => 5, 'CostPriceAtOrder' => 600,
        ]);

        $response = $this->fetchDetails('supplier', $this->supplier->SupplierID);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Supplier Name', 'value' => 'Acme Supplies']);
        $this->assertStringContainsString('PO-TEST-000002', $response->getContent());
    }

    public function test_unknown_type_or_missing_id_returns_404(): void
    {
        $this->fetchDetails('sales', 999999)->assertNotFound();
        $this->fetchDetails('not-a-real-type', 1)->assertNotFound();
    }

    public function test_non_admin_cannot_view_report_details(): void
    {
        $cashierRole = Role::where('role_name', 'cashier')->first();
        $cashier = User::factory()->create(['role_id' => $cashierRole->id]);

        $response = $this->actingAs($cashier)->getJson(route('admin.reports.details', ['type' => 'supplier', 'id' => $this->supplier->SupplierID]));

        $response->assertForbidden();
    }

    public function test_view_details_button_present_for_every_report_type_with_data(): void
    {
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => now(), 'StaffID' => $this->staff->StaffID,
        ]);
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 1000, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $transaction->SalesTransactionID]);
        $discount = Discount::firstOrCreate(['DiscountRate' => 0]);
        Billing::create([
            'CustomerName' => 'Walk-in Customer', 'VatApplied' => '12%', 'BillingAmount' => 1000,
            'BillingDate' => now(), 'DiscountID' => $discount->DiscountID, 'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);

        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000003', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_PENDING, 'SupplierID' => $this->supplier->SupplierID,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 5, 'ReceivedQuantity' => 0, 'CostPriceAtOrder' => 600,
        ]);

        $return = SalesReturn::create([
            'SalesTransactionID' => $transaction->SalesTransactionID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'Reason' => 'Factory Defect', 'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'), 'Status' => 'pending', 'StaffID' => $this->staff->StaffID,
        ]);
        SalesReturnItem::create([
            'SalesReturnID' => $return->SalesReturnID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'UnitPrice' => 1000, 'Reason' => 'Factory Defect',
        ]);

        DamagedProduct::create([
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 1, 'Description' => 'Cracked', 'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken', 'Status' => DamagedProduct::STATUS_PENDING, 'SourceModule' => DamagedProduct::SOURCE_MANUAL,
        ]);

        $expectedOnclick = [
            'sales' => "viewReportDetails('sales'",
            'inventory' => "viewReportDetails('inventory'",
            'orders' => "viewReportDetails('orders'",
            'returns' => "viewReportDetails('returns'",
            'damage' => "viewReportDetails('damage'",
            'supplier' => "viewReportDetails('supplier'",
        ];

        foreach ($expectedOnclick as $type => $needle) {
            $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => $type]));
            $response->assertOk();
            $response->assertSee($needle, false);
        }
    }
}
