<?php

namespace Tests\Feature\Admin;

use App\Models\Billing;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\StockAdjustment;
use App\Models\StockReceiving;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsModuleTest extends TestCase
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

    private function makeBilling(float $amount, string $billingDate): Billing
    {
        $cashierRole = Role::firstOrCreate(['role_name' => 'cashier']);
        $cashierUser = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe', 'ContactNumber' => '0000',
            'Email' => 'jane' . uniqid() . '@example.com', 'Age' => 30, 'Gender' => 'F', 'UserID' => $cashierUser->id,
        ]);
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => $billingDate, 'StaffID' => $staff->StaffID,
        ]);

        return Billing::create([
            'CustomerName' => 'Walk-in Customer', 'VatApplied' => '12%', 'BillingAmount' => $amount,
            'BillingDate' => $billingDate, 'SalesTransactionID' => $transaction->SalesTransactionID,
        ]);
    }

    public function test_index_defaults_to_sales_report_and_offers_all_six_types(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Sales Report');
        $response->assertSee('Inventory Report');
        $response->assertSee('Purchase Report');
        $response->assertSee('Damage Report');
        $response->assertSee('Return Report');
        $response->assertSee('Supplier Report');
    }

    public function test_index_no_longer_shows_removed_sections_and_controls(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertDontSee('Best Selling Products');
        $response->assertDontSee('Recent Transactions');
        $response->assertDontSee('Preview Report');
        $response->assertDontSee('Export CSV');
        $response->assertDontSee('id="printReportBtn"', false);
        $response->assertDontSee('>Print<', false);
    }

    public function test_inventory_status_card_is_removed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => 'inventory']));

        $response->assertOk();
        $response->assertDontSee('Inventory Status');
        $response->assertDontSee('Available');
        $response->assertDontSee('Out of Stock');
    }

    public function test_inventory_date_inputs_are_not_disabled(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => 'inventory']));

        $response->assertOk();
        $response->assertDontSee('Inventory is a live snapshot');
        // Neither date input should carry a "disabled" attribute for the inventory type anymore.
        $response->assertDontSee('id="reportDateFrom" class="form-input" value="" disabled', false);
        $response->assertDontSee('id="reportDateTo" class="form-input" value="" disabled', false);
    }

    public function test_inventory_report_filters_by_date_range_using_stock_activity(): void
    {
        $inMovedProduct = Product::create([
            'ProductName' => 'Moved In Range', 'Model' => 'M1', 'SKU' => 'SKU-M1',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $inMovedProduct->ProductID, 'Quantity' => 10, 'ReorderThreshold' => 5, 'Status' => 'In Stock']);
        StockReceiving::create([
            'Quantity' => 10, 'DateReceived' => '2026-06-15', 'ReceiptNumber' => 'RCPT-1',
            'ProductID' => $inMovedProduct->ProductID, 'SupplierID' => $this->supplier->SupplierID,
        ]);

        $outsideProduct = Product::create([
            'ProductName' => 'Moved Outside Range', 'Model' => 'M2', 'SKU' => 'SKU-M2',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $outsideProduct->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 5, 'Status' => 'In Stock']);
        StockReceiving::create([
            'Quantity' => 5, 'DateReceived' => '2020-01-01', 'ReceiptNumber' => 'RCPT-2',
            'ProductID' => $outsideProduct->ProductID, 'SupplierID' => $this->supplier->SupplierID,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'inventory', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Moved In Range', $html);
        $this->assertStringNotContainsString('Moved Outside Range', $html);
    }

    public function test_end_date_before_start_date_is_clamped_with_error_message(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'sales', 'date_from' => '2026-06-10', 'date_to' => '2026-06-01',
        ]));

        $response->assertOk();
        $this->assertSame('End Date cannot be earlier than Start Date.', $response->json('dateRangeError'));
    }

    public function test_empty_report_shows_consistent_no_records_message(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'damage', 'date_from' => '2020-01-01', 'date_to' => '2020-01-02',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('No reports or records found for the selected date range.', $response->json('html'));
    }

    public function test_preview_endpoint_returns_damage_report_html(): void
    {
        DamagedProduct::create([
            'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
            'Quantity' => 2, 'Description' => 'Cracked', 'DateRecorded' => now()->format('Y-m-d'),
            'DamageType' => 'broken', 'Status' => DamagedProduct::STATUS_PENDING, 'SourceModule' => DamagedProduct::SOURCE_MANUAL,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'damage']));

        $response->assertOk();
        $this->assertStringContainsString('DVR Camera', $response->json('html'));
        $this->assertStringContainsString('Damage Records', $response->json('html'));
    }

    public function test_preview_endpoint_returns_supplier_report_html_with_spend(): void
    {
        $po = PurchaseOrder::create([
            'PONumber' => 'PO-TEST-000001', 'PurchaseDate' => now()->format('Y-m-d'),
            'Status' => PurchaseOrder::STATUS_FULLY_RECEIVED, 'SupplierID' => $this->supplier->SupplierID,
        ]);
        PurchaseOrderItem::create([
            'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 10, 'ReceivedQuantity' => 10, 'CostPriceAtOrder' => 600,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'supplier']));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Acme Supplies', $html);
        $this->assertStringContainsString('6,000.00', $html);
    }

    public function test_supplier_report_does_not_run_a_query_per_supplier(): void
    {
        // Multiple suppliers, each with their own PO/items — a per-supplier
        // query loop would scale with supplier count; the fixed version
        // stays at a fixed handful of queries regardless.
        for ($i = 0; $i < 5; $i++) {
            $supplier = Supplier::create([
                'SupplierName' => "Supplier {$i}", 'ContactNumber' => '0000',
                'Email' => "supplier{$i}@example.com", 'Address' => 'N/A',
            ]);
            $po = PurchaseOrder::create([
                'PONumber' => "PO-TEST-{$i}", 'PurchaseDate' => now()->format('Y-m-d'),
                'Status' => PurchaseOrder::STATUS_FULLY_RECEIVED, 'SupplierID' => $supplier->SupplierID,
            ]);
            PurchaseOrderItem::create([
                'PurchaseOrderID' => $po->PurchaseOrderID, 'ProductID' => $this->product->ProductID,
                'Quantity' => 5, 'ReceivedQuantity' => 5, 'CostPriceAtOrder' => 600,
            ]);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'supplier']));
        $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $response->assertOk();
        // A per-supplier N+1 would be 5+ queries just for purchase orders,
        // on top of everything else the request does. 15 is a generous
        // ceiling that still fails if the loop regresses.
        $this->assertLessThan(15, $queryCount, "Supplier report ran {$queryCount} queries — likely an N+1 regression.");
    }

    public function test_csv_export_works_for_damage_and_supplier_types(): void
    {
        $damageResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'damage', 'format' => 'csv']));
        $damageResponse->assertOk();
        $damageResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $supplierResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'supplier', 'format' => 'csv']));
        $supplierResponse->assertOk();
        $supplierResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_pdf_export_works_for_damage_and_supplier_types(): void
    {
        $damageResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'damage', 'format' => 'pdf']));
        $damageResponse->assertOk();

        $supplierResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'supplier', 'format' => 'pdf']));
        $supplierResponse->assertOk();
    }

    public function test_malformed_date_is_rejected_with_a_friendly_error_instead_of_silently_ignored(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'sales', 'date_from' => 'not-a-date',
        ]));

        $response->assertOk();
        $this->assertSame('Start Date is not a valid date.', $response->json('dateRangeError'));
    }

    public function test_valid_dates_still_pass_through_without_error(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'sales', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $this->assertNull($response->json('dateRangeError'));
    }

    public function test_preview_endpoint_returns_total_revenue_for_the_filtered_range(): void
    {
        $this->makeBilling(1500, '2026-06-15');

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'sales', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $this->assertEquals(1500.0, $response->json('totalRevenue'));
    }

    public function test_index_offers_date_presets_csv_export_and_print_preview(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('data-preset="today"', false);
        $response->assertSee('data-preset="yesterday"', false);
        $response->assertSee('data-preset="this_week"', false);
        $response->assertSee('data-preset="last_week"', false);
        $response->assertSee('data-preset="this_month"', false);
        $response->assertSee('data-preset="last_month"', false);
        $response->assertSee('data-preset="this_year"', false);
        $response->assertSee('id="exportCsvLink"', false);
        $response->assertSee('id="printPreviewLink"', false);
    }

    public function test_print_preview_renders_branded_report_with_date_range_and_generated_by(): void
    {
        $this->makeBilling(750, '2026-06-15');

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', [
            'type' => 'sales', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertSee('CCTV Express');
        $response->assertSee('sales Report');
        $response->assertSee('2026-06-01 to 2026-06-30');
        $response->assertSee('Generated By: ' . $this->admin->full_name, false);
        $response->assertSee('750.00');
    }

    public function test_pdf_export_still_returns_a_pdf_after_the_branding_rework(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // dompdf's own output is a binary PDF stream, not text-searchable via
    // assertSee — render the same admin.reports.pdf Blade view directly
    // (pre-dompdf) to verify the branding/Generated-By content actually
    // made it into the HTML dompdf converts.
    public function test_pdf_view_html_includes_company_branding_and_generated_by(): void
    {
        $this->actingAs($this->admin);

        $html = view('admin.reports.pdf', [
            'type' => 'sales', 'rows' => collect(), 'dateFrom' => '2026-06-01', 'dateTo' => '2026-06-30',
        ])->render();

        $this->assertStringContainsString('CCTV Express', $html);
        $this->assertStringContainsString('2026-06-01 to 2026-06-30', $html);
        $this->assertStringContainsString('Generated By: ' . $this->admin->full_name, $html);
        $this->assertStringContainsString('counter(page)', $html);
    }
}
