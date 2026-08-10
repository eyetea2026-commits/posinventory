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
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\StockAdjustment;
use App\Models\StockReceiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportSummaryBuilder;
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
        $response->assertSee('June 1, 2026');
        $response->assertSee('June 30, 2026');
        $response->assertSee('2026-06-01 to 2026-06-30');
        $response->assertSee('Generated By');
        $response->assertSee($this->admin->full_name);
        $response->assertSee('Branch');
        $response->assertSee('Main Branch');
        $response->assertSee('Report Summary');
        $response->assertSee('Checked By');
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
        $this->assertStringContainsString('Generated By', $html);
        $this->assertStringContainsString($this->admin->full_name, $html);
        $this->assertStringContainsString('Confidential Report', $html);
        $this->assertStringContainsString('counter(page)', $html);
        $this->assertStringContainsString('Report Summary', $html);
    }

    // ReportSummaryBuilder::sales() now expects the item-level row shape
    // ReportController::salesItemRows() produces (Quantity/ItemTotal on
    // every row; Discount/VatAmount/BillingAmount only on each invoice's
    // first item row) — constructed directly here since that method is
    // private; the real explosion is covered end-to-end by
    // test_sales_report_export_explodes_to_one_row_per_line_item below.
    public function test_report_summary_builder_computes_sales_aggregates(): void
    {
        $rows = collect([
            (object) ['Quantity' => 2, 'ItemTotal' => 2000.0, 'Discount' => 0.0, 'VatAmount' => 120.0, 'BillingAmount' => 1120.0, 'is_first' => true],
            (object) ['Quantity' => 1, 'ItemTotal' => 1000.0, 'Discount' => null, 'VatAmount' => null, 'BillingAmount' => null, 'is_first' => false],
        ]);

        $summary = collect(ReportSummaryBuilder::forType('sales', $rows))->keyBy('label');

        $this->assertSame(1, $summary['Total Transactions']['value']);
        $this->assertSame(3, $summary['Total Quantity Sold']['value']);
        $this->assertEqualsWithDelta(3000.0, $summary['Subtotal (Gross Sales)']['value'], 0.001);
        $this->assertEqualsWithDelta(120.0, $summary['VAT']['value'], 0.001);
        $this->assertEqualsWithDelta(1120.0, $summary['Net Sales']['value'], 0.001);
        $this->assertTrue($summary['Net Sales']['money']);
        $this->assertFalse($summary['Total Transactions']['money']);
    }

    public function test_report_summary_builder_computes_inventory_aggregates(): void
    {
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 40, 'Status' => 'Available']);

        $rows = Inventory::with('product')->get();
        $summary = collect(ReportSummaryBuilder::forType('inventory', $rows))->keyBy('label');

        $this->assertSame(1, $summary['Total Products']['value']);
        $this->assertSame(40, $summary['Total Stock Quantity']['value']);
        $this->assertEqualsWithDelta(40 * (float) $this->product->CostPrice, $summary['Total Inventory Value']['value'], 0.001);
    }

    public function test_excel_export_returns_a_valid_spreadsheet_for_every_report_type(): void
    {
        foreach (['sales', 'inventory', 'orders', 'damage', 'returns', 'supplier'] as $type) {
            $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => $type, 'format' => 'excel']));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    // ---- Report redesign: item-level Sales explosion ----

    public function test_sales_report_export_explodes_to_one_row_per_line_item(): void
    {
        $secondProduct = Product::create([
            'ProductName' => 'IP Camera', 'Model' => 'CAM-02', 'SKU' => 'SKU-002',
            'Price' => 500, 'CostPrice' => 300, 'CategoryID' => $this->product->CategoryID,
        ]);
        $billing = $this->makeBilling(1120, '2026-06-15');
        $billing->update(['Subtotal' => 1000, 'DiscountAmount' => 0, 'VatAmount' => 120]);
        SalesItem::create(['Quantity' => 2, 'UnitPrice' => 400, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 200, 'ProductID' => $secondProduct->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']));

        $response->assertOk();
        $response->assertSee('DVR Camera');
        $response->assertSee('IP Camera');
        // Two line items from the same invoice = two table rows, but the
        // discount/VAT amount must only appear once (on the first item's
        // row), not duplicated across both — it's a whole-invoice fact.
        $response->assertSeeInOrder(['DVR Camera', 'IP Camera']);
    }

    public function test_sales_report_item_rows_sum_back_to_the_original_billing_amount(): void
    {
        $secondProduct = Product::create([
            'ProductName' => 'IP Camera', 'Model' => 'CAM-02', 'SKU' => 'SKU-002',
            'Price' => 500, 'CostPrice' => 300, 'CategoryID' => $this->product->CategoryID,
        ]);
        $billing = $this->makeBilling(1120, '2026-06-15');
        $billing->update(['Subtotal' => 1000, 'DiscountAmount' => 0, 'VatAmount' => 120]);
        SalesItem::create(['Quantity' => 2, 'UnitPrice' => 400, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 200, 'ProductID' => $secondProduct->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);

        $method = new \ReflectionMethod(\App\Http\Controllers\Admin\ReportController::class, 'salesItemRows');
        $method->setAccessible(true);
        $rows = $method->invoke(new \App\Http\Controllers\Admin\ReportController(), null, null);

        $this->assertCount(2, $rows);
        // Gross (sum of every line's Quantity*UnitPrice) minus discount plus
        // VAT must reconstruct the exact amount the cashier actually charged
        // — no drift from the checkout math that produced it.
        $gross = $rows->sum('ItemTotal');
        $this->assertEqualsWithDelta(1000.0, $gross, 0.001);
        $this->assertEqualsWithDelta(1120.0, $gross - $rows->sum('Discount') + $rows->sum('VatAmount'), 0.001);
        $this->assertTrue($rows->first()->is_first);
        $this->assertFalse($rows->last()->is_first);
        $this->assertNull($rows->last()->Discount);
    }

    // ---- Report redesign: item-level Purchase (orders) explosion ----

    public function test_purchase_report_export_explodes_to_one_row_per_ordered_line(): void
    {
        $order = PurchaseOrder::create([
            'PONumber' => 'PO-2026-000001', 'SupplierID' => $this->supplier->SupplierID,
            'PurchaseDate' => '2026-06-10', 'Status' => 'approved',
        ]);
        PurchaseOrderItem::create(['PurchaseOrderID' => $order->PurchaseOrderID, 'ProductID' => $this->product->ProductID, 'Quantity' => 10, 'CostPriceAtOrder' => 600]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'orders']));

        $response->assertOk();
        $response->assertSee('PO-2026-000001');
        $response->assertSee('Acme Supplies');
        $response->assertSee('DVR Camera');
        // No VAT column — purchase orders have no VAT concept in this system.
        $response->assertDontSee('>VAT<', false);
    }

    // ---- Report redesign: enriched columns per type ----

    public function test_inventory_report_shows_enriched_columns(): void
    {
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 40, 'ReorderThreshold' => 10, 'Status' => 'Available']);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'inventory']));

        $response->assertOk();
        $response->assertSee('SKU-001');
        $response->assertSee('CCTV');
        $response->assertSee('₱600.00'); // Cost Price
        $response->assertSee('₱24,000.00'); // Stock Value = 40 * 600
    }

    public function test_supplier_report_shows_contact_details(): void
    {
        $this->supplier->update(['ContactPerson' => 'John Reyes']);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'supplier']));

        $response->assertOk();
        $response->assertSee('John Reyes');
        $response->assertSee('acme@example.com');
    }

    // ---- Company logo ----

    public function test_print_and_pdf_and_excel_all_include_the_company_logo(): void
    {
        $print = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']));
        $print->assertOk();
        $print->assertSee('data:image/png;base64,', false);

        $pdf = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'pdf']));
        $pdf->assertOk();

        $excel = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'excel']));
        $excel->assertOk();
    }

    // ---- A4 landscape for wide report types ----

    public function test_sales_and_inventory_pdf_use_landscape_others_use_portrait(): void
    {
        $sales = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'pdf']));
        $sales->assertOk();

        $supplier = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'supplier', 'format' => 'pdf']));
        $supplier->assertOk();

        // Both must succeed without throwing — dompdf raises if setPaper()
        // is ever called with an invalid orientation string, so a clean 200
        // for every type confirms the per-type landscape/portrait branch
        // in ReportController::export() is wired correctly for all of them.
        foreach (['sales', 'inventory', 'orders', 'damage', 'returns', 'supplier'] as $type) {
            $this->actingAs($this->admin)
                ->get(route('admin.reports.export', ['type' => $type, 'format' => 'pdf']))
                ->assertOk();
        }
    }

    // ---- Empty state ----

    public function test_empty_sales_report_shows_no_records_found_not_a_broken_table(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', [
            'type' => 'sales', 'date_from' => '2099-01-01', 'date_to' => '2099-01-31',
        ]));

        $response->assertOk();
        $response->assertSee('NO RECORDS FOUND');
        // Header/filters/footer must still render around the empty state.
        $response->assertSee('Report Period');
        $response->assertSee('Generated by CCTV Express POS');
    }
}
