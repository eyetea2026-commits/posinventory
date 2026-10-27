<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ReportController;
use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\StockAdjustment;
use App\Models\StockReceiving;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportSummaryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID,
        ]);
        $this->supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);
    }

    private function makeSale(float $amount, string $billingDate): SalesTransaction
    {
        $cashierRole = Role::firstOrCreate(['role_name' => 'cashier']);
        $cashierUser = User::factory()->create(['role_id' => $cashierRole->id]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe', 'ContactNumber' => '0000',
            'Email' => 'jane'.uniqid().'@example.com', 'Age' => 30, 'Gender' => 'F', 'UserID' => $cashierUser->id,
        ]);
        $transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer', 'SalesTransactionDate' => $billingDate, 'StaffID' => $staff->StaffID,
        ]);

        return tap(SalesTransaction::findOrFail($transaction->SalesTransactionID))->update([
            'VatApplied' => '12%',
            'TotalAmount' => $amount,
        ]);
    }

    public function test_index_defaults_to_sales_report_and_offers_exactly_the_seven_curated_types(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Sales and Revenue Report');
        $response->assertSee('Inventory Report');
        $response->assertSee('Reorder Report');
        $response->assertSee('Damage Report');
        $response->assertSee('Refund Report');
        $response->assertSee('Stock Adjustment Report');
        $response->assertSee('Purchase Order Report');
        // Stock Receiving and Supplier were dropped from the curated list.
        $response->assertDontSee('Stock Receiving Report');
        $response->assertDontSee('Supplier Report');
        $response->assertDontSee('value="stock_receiving"', false);
        $response->assertDontSee('value="supplier"', false);
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

    // ---- Stock Adjustment: split out of Inventory into its own type ----

    public function test_report_type_dropdown_offers_stock_adjustment(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Stock Adjustment Report');
        $response->assertSee('value="stock_adjustment"', false);
    }

    public function test_inventory_report_no_longer_bundles_stock_adjustments(): void
    {
        StockAdjustment::create([
            'ProductID' => $this->product->ProductID, 'QuantityAdjust' => -3,
            'Reason' => StockAdjustment::REASON_LOST, 'Date' => now()->format('Y-m-d'),
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'inventory']));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Inventory Items', $html);
        $this->assertStringNotContainsString('Stock Adjustments', $html);
        $this->assertStringNotContainsString(StockAdjustment::REASON_LOST, $html);
    }

    public function test_stock_adjustment_report_shows_its_own_rows(): void
    {
        StockAdjustment::create([
            'ProductID' => $this->product->ProductID, 'QuantityAdjust' => -3,
            'Reason' => StockAdjustment::REASON_LOST, 'Date' => '2026-06-15',
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', [
            'type' => 'stock_adjustment', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Stock Adjustments', $html);
        $this->assertStringContainsString('DVR Camera', $html);
        $this->assertStringContainsString('-3', $html);
        $this->assertStringContainsString(StockAdjustment::REASON_LOST, $html);
    }

    public function test_stock_adjustment_csv_and_pdf_export_work(): void
    {
        StockAdjustment::create([
            'ProductID' => $this->product->ProductID, 'QuantityAdjust' => 2,
            'Reason' => StockAdjustment::REASON_COUNT_ERROR, 'Date' => now()->format('Y-m-d'),
        ]);

        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'stock_adjustment', 'format' => 'csv']));
        $csv->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $pdf = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'stock_adjustment', 'format' => 'pdf']));
        $pdf->assertOk();
    }

    public function test_csv_export_starts_with_a_utf8_byte_order_mark(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'inventory', 'format' => 'csv']));

        $csv->assertOk();
        // Excel needs the BOM to read the file as UTF-8 instead of ANSI.
        $this->assertStringStartsWith("\xEF\xBB\xBFID,Product,", $csv->streamedContent());
    }

    public function test_pdf_export_embeds_a_font_that_has_the_peso_sign(): void
    {
        $pdf = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'inventory', 'format' => 'pdf']));

        $pdf->assertOk();
        $content = (string) $pdf->getContent();
        // Helvetica (the old sans-serif fallback) has no ₱ glyph and printed "?".
        $this->assertStringContainsString('DejaVuSans', $content);
        $this->assertStringNotContainsString('/BaseFont /Helvetica', $content);
        // Subsetting keeps the embedded font small (the full font is ~700KB).
        $this->assertLessThan(400 * 1024, strlen($content));
    }

    public function test_report_summary_builder_computes_stock_adjustment_aggregates(): void
    {
        $adjustments = collect([
            (object) ['QuantityAdjust' => 5],
            (object) ['QuantityAdjust' => -3],
        ]);
        $adjustmentSummary = collect(ReportSummaryBuilder::forType('stock_adjustment', $adjustments))->keyBy('label');
        $this->assertSame(2, $adjustmentSummary['Total Adjustments']['value']);
        $this->assertSame(5, $adjustmentSummary['Total Quantity Increased']['value']);
        $this->assertSame(3, $adjustmentSummary['Total Quantity Decreased']['value']);
    }

    // ---- Reorder: a live low-stock snapshot, reusing the same
    // Quantity <= ReorderThreshold detection the dashboard's low-stock
    // widget already uses ----

    public function test_reorder_report_shows_only_products_at_or_below_threshold(): void
    {
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 3, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);

        $wellStocked = Product::create([
            'ProductName' => 'Well Stocked Camera', 'Model' => 'CAM-99',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $wellStocked->ProductID, 'Quantity' => 100, 'ReorderThreshold' => 10, 'Status' => 'Available']);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'reorder']));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Products Needing Reorder', $html);
        $this->assertStringContainsString('DVR Camera', $html);
        $this->assertStringNotContainsString('Well Stocked Camera', $html);
    }

    public function test_reorder_report_shows_preferred_supplier_and_create_po_action(): void
    {
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 2, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);
        $this->product->suppliers()->create(['SupplierID' => $this->supplier->SupplierID, 'CostPrice' => 600, 'IsPreferred' => true]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'reorder']));

        $response->assertOk();
        $html = $response->json('html');
        $this->assertStringContainsString('Acme Supplies', $html);
        $this->assertStringContainsString(route('admin.purchase-orders.create-from-reorder', $this->product->ProductID), $html);
    }

    public function test_reorder_report_csv_and_pdf_export_work(): void
    {
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 1, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);

        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'reorder', 'format' => 'csv']));
        $csv->assertOk();
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $pdf = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'reorder', 'format' => 'pdf']));
        $pdf->assertOk();
    }

    public function test_report_summary_builder_computes_reorder_aggregates(): void
    {
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 4, 'ReorderThreshold' => 10, 'Status' => 'Low Stock']);
        $rows = Inventory::with('product')->get();

        $summary = collect(ReportSummaryBuilder::forType('reorder', $rows))->keyBy('label');

        $this->assertSame(1, $summary['Products Needing Reorder']['value']);
        // suggestedReorderQuantity(4, 10) brings stock up to 2x threshold: (10*2) - 4 = 16.
        $this->assertSame(16, $summary['Total Suggested Reorder Quantity']['value']);
    }

    public function test_inventory_report_filters_by_date_range_using_stock_activity(): void
    {
        $inMovedProduct = Product::create([
            'ProductName' => 'Moved In Range', 'Model' => 'M1',
            'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $this->product->CategoryID,
        ]);
        Inventory::create(['ProductID' => $inMovedProduct->ProductID, 'Quantity' => 10, 'ReorderThreshold' => 5, 'Status' => 'In Stock']);
        StockReceiving::create([
            'Quantity' => 10, 'DateReceived' => '2026-06-15', 'ReceiptNumber' => 'RCPT-1',
            'ProductID' => $inMovedProduct->ProductID, 'SupplierID' => $this->supplier->SupplierID,
        ]);

        $outsideProduct = Product::create([
            'ProductName' => 'Moved Outside Range', 'Model' => 'M2',
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

    public function test_csv_export_works_for_damage_type(): void
    {
        $damageResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'damage', 'format' => 'csv']));
        $damageResponse->assertOk();
        $damageResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_pdf_export_works_for_damage_type(): void
    {
        $damageResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'damage', 'format' => 'pdf']));
        $damageResponse->assertOk();
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
        $this->makeSale(1500, '2026-06-15');

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
        $this->makeSale(750, '2026-06-15');

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', [
            'type' => 'sales', 'date_from' => '2026-06-01', 'date_to' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertSee('CCTV Express');
        $response->assertSee('Sales and Revenue Report');
        $response->assertSee('06/01/2026');
        $response->assertSee('06/30/2026');
        $response->assertSee('06/01/2026 to 06/30/2026');
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
        $this->assertStringContainsString('06/01/2026 to 06/30/2026', $html);
        $this->assertStringContainsString('Generated By', $html);
        $this->assertStringContainsString($this->admin->full_name, $html);
        $this->assertStringContainsString('Confidential Report', $html);
        $this->assertStringContainsString('counter(page)', $html);
        $this->assertStringContainsString('Report Summary', $html);
    }

    // ReportSummaryBuilder::sales() now expects the item-level row shape
    // ReportController::salesItemRows() produces (Quantity/ItemTotal on
    // every row; Discount/VatAmount/TotalAmount only on each invoice's
    // first item row) — constructed directly here since that method is
    // private; the real explosion is covered end-to-end by
    // test_sales_report_export_explodes_to_one_row_per_line_item below.
    public function test_report_summary_builder_computes_sales_aggregates(): void
    {
        $rows = collect([
            (object) ['Quantity' => 2, 'ItemTotal' => 2000.0, 'Discount' => 0.0, 'VatAmount' => 120.0, 'TotalAmount' => 1120.0, 'is_first' => true],
            (object) ['Quantity' => 1, 'ItemTotal' => 1000.0, 'Discount' => null, 'VatAmount' => null, 'TotalAmount' => null, 'is_first' => false],
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
        foreach (['sales', 'inventory', 'reorder', 'stock_adjustment', 'orders', 'damage', 'returns'] as $type) {
            $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => $type, 'format' => 'excel']));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    // ---- Report redesign: item-level Sales explosion ----

    public function test_sales_report_export_explodes_to_one_row_per_line_item(): void
    {
        $secondProduct = Product::create([
            'ProductName' => 'IP Camera', 'Model' => 'CAM-02',
            'Price' => 500, 'CostPrice' => 300, 'CategoryID' => $this->product->CategoryID,
        ]);
        $billing = $this->makeSale(1120, '2026-06-15');
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
            'ProductName' => 'IP Camera', 'Model' => 'CAM-02',
            'Price' => 500, 'CostPrice' => 300, 'CategoryID' => $this->product->CategoryID,
        ]);
        $billing = $this->makeSale(1120, '2026-06-15');
        $billing->update(['Subtotal' => 1000, 'DiscountAmount' => 0, 'VatAmount' => 120]);
        SalesItem::create(['Quantity' => 2, 'UnitPrice' => 400, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 200, 'ProductID' => $secondProduct->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);

        $method = new \ReflectionMethod(ReportController::class, 'salesItemRows');
        $method->setAccessible(true);
        $rows = $method->invoke(new ReportController, null, null);

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

    // The printed/exported Sales report's "Total" column must show the
    // invoice's actual net total (post-discount), not the pre-discount
    // gross line amount — otherwise it never agrees with the Discount/VAT
    // columns shown right next to it (a ₱3,000 sale with ₱200 discount
    // must show Total ₱2,800, not ₱3,000).
    public function test_sales_report_total_column_reflects_the_discount_not_the_gross_line_amount(): void
    {
        $billing = $this->makeSale(2800, '2026-06-20');
        $billing->update(['Subtotal' => 3000, 'DiscountAmount' => 200, 'VatAmount' => 300]);
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 3000, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']));

        $response->assertOk();
        $response->assertSee('₱2,800.00');
        // The old (wrong) behavior showed the gross line amount here.
        $response->assertDontSee('<td class="col-money col-total">₱3,000.00</td>', false);
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
        $response->assertSee('CCTV');
        $response->assertSee('₱600.00'); // Cost Price
        $response->assertSee('₱24,000.00'); // Stock Value = 40 * 600
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

        // Every type must succeed without throwing — dompdf raises if setPaper()
        // is ever called with an invalid orientation string, so a clean 200
        // for every type confirms the per-type landscape/portrait branch
        // in ReportController::export() is wired correctly for all of them.
        foreach (['sales', 'inventory', 'reorder', 'stock_adjustment', 'orders', 'damage', 'returns'] as $type) {
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

    private function makeRefund(SalesTransaction $billing, float $amount, string $date, string $type = 'refund', string $status = SalesReturn::STATUS_PROCESSED): SalesReturn
    {
        return SalesReturn::create([
            'SalesTransactionID' => $billing->SalesTransactionID, 'ProductID' => $this->product->ProductID,
            'Quantity' => 1, 'Reason' => 'Factory Defect', 'ReturnType' => $type, 'Status' => $status,
            'ReturnDate' => $date, 'RefundAmount' => $amount, 'RefundDate' => $status === SalesReturn::STATUS_PROCESSED ? $date : null,
        ]);
    }

    // Sales and Revenue only: three cards above the filters -- total sold,
    // total refunded, and what's left after refunds, for the selected range.
    public function test_sales_report_shows_sale_refund_and_after_refund_totals_above_the_filters(): void
    {
        $inRange = $this->makeSale(5000, '2026-05-10');
        $this->makeSale(2500, '2026-05-12');
        $outside = $this->makeSale(9999, '2026-04-01');
        $this->makeRefund($inRange, 1200, '2026-05-11');
        // Not counted: outside the range, a replacement (goods, not money), still pending.
        $this->makeRefund($outside, 700, '2026-04-02');
        $this->makeRefund($inRange, 300, '2026-05-11', 'replacement');
        $this->makeRefund($inRange, 400, '2026-05-11', 'refund', SalesReturn::STATUS_APPROVED);

        $range = ['type' => 'sales', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31'];
        $html = $this->actingAs($this->admin)->get(route('admin.reports.index', $range))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div id="salesSummaryCards" class="stats-grid mt-4" style="">#', $html);
        $this->assertLessThan(strpos($html, 'report-filter-card'), strpos($html, 'id="salesSummaryCards"'));
        $this->assertStringContainsString('Total Cost of Sale', $html);
        $this->assertStringContainsString('<div class="stat-value" id="summarySalesTotal">₱7,500.00</div>', $html);
        $this->assertStringContainsString('<div class="stat-value" id="summaryRefundTotal">₱1,200.00</div>', $html);
        $this->assertStringContainsString('<div class="stat-value" id="summaryNetTotal">₱6,300.00</div>', $html);

        $this->actingAs($this->admin)->getJson(route('admin.reports.preview', $range))->assertOk()
            ->assertJsonPath('salesSummary.sales_total', 7500)
            ->assertJsonPath('salesSummary.refund_total', 1200)
            ->assertJsonPath('salesSummary.net_total', 6300);
    }

    public function test_summary_cards_are_hidden_for_every_other_report_type(): void
    {
        foreach (['inventory', 'reorder', 'damage', 'returns', 'stock_adjustment', 'orders'] as $type) {
            $this->actingAs($this->admin)->get(route('admin.reports.index', ['type' => $type]))->assertOk()
                ->assertSee('<div id="salesSummaryCards" class="stats-grid mt-4" style="display:none;">', false);
            $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => $type]))->assertOk()
                ->assertJsonPath('salesSummary', null);
        }
    }

    // No date range picked: the printed report says "As of <today>", not
    // "All Time" (both the Report Period line and the Filters box).
    public function test_print_preview_without_dates_says_as_of_today(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13));

        $html = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))->assertOk()->getContent();

        $this->assertStringNotContainsString('All Time', $html);
        $this->assertSame(2, substr_count($html, 'As of 10/13/2026'));

        // With dates picked, it still shows that range.
        $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
            ->assertOk()->assertSee('05/01/2026')->assertSee('05/31/2026')->assertDontSee('As of');
    }

    // The Excel download's header says the same: "As of <today>" when no
    // dates are picked, never "All Time".
    public function test_downloaded_reports_show_the_peso_sign(): void
    {
        $billing = $this->makeSale(1500, '2026-06-15');
        SalesItem::create(['Quantity' => 1, 'UnitPrice' => 1500, 'ProductID' => $this->product->ProductID, 'SalesTransactionID' => $billing->SalesTransactionID]);

        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('"₱1,500.00"', $csv);

        $excel = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'excel']))->assertOk();
        $sheet = IOFactory::load($excel->baseResponse->getFile()->getPathname())->getActiveSheet();
        $totalCell = null;
        foreach ($sheet->getRowIterator() as $row) {
            if ($sheet->getCell('A'.$row->getRowIndex())->getValue() === 'Invoice No') {
                $totalCell = $sheet->getCell('J'.($row->getRowIndex() + 1));
                break;
            }
        }
        $this->assertNotNull($totalCell);
        $this->assertEquals(1500, $totalCell->getValue());
        $this->assertSame('₱1,500.00', $totalCell->getFormattedValue());

        // The PDF is rendered from the same table as the print preview.
        $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))
            ->assertOk()
            ->assertSee('₱1,500.00');
        $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_excel_download_without_dates_says_as_of_today(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13));
        $this->makeSale(1500, '2026-10-10');

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'excel']))->assertOk();

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $cells = collect($sheet->toArray())->flatten()->filter()->map(fn ($v) => (string) $v);

        $this->assertTrue($cells->contains('As of 10/13/2026'), 'Report Period should read "As of 10/13/2026"');
        $this->assertFalse($cells->contains('All Time'));
    }

    // A customer who gave no name (stored as "Walk-in Customer") shows as
    // "N/A" on the Sales and Revenue and Refund reports; real names stay.
    public function test_walk_in_customers_show_as_na_on_sales_and_refund_reports(): void
    {
        $walkIn = $this->makeSale(1000, '2026-05-10');
        $named = $this->makeSale(2000, '2026-05-11');
        $named->update(['CustomerName' => 'Juan Dela Cruz']);
        $refund = $this->makeRefund($walkIn, 500, '2026-05-12');
        $refund->update(['CustomerName' => 'Walk-in Customer']);
        SalesReturnItem::create(['SalesReturnID' => $refund->SalesReturnID, 'ProductID' => $this->product->ProductID, 'Quantity' => 1, 'UnitPrice' => 500, 'Reason' => 'Factory Defect']);

        $sales = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'sales']))->json('html');
        $this->assertStringContainsString('<td>N/A</td>', $sales);
        $this->assertStringContainsString('<td>Juan Dela Cruz</td>', $sales);
        $this->assertStringNotContainsString('Walk-in', $sales);

        $salesPrint = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))->assertOk()->getContent();
        $this->assertStringNotContainsString('Walk-in', $salesPrint);
        $this->assertStringContainsString('Juan Dela Cruz', $salesPrint);

        $returnsPrint = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'returns']))->assertOk()->getContent();
        $this->assertStringContainsString('<td>N/A</td>', $returnsPrint);
        $this->assertStringNotContainsString('Walk-in', $returnsPrint);

        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'sales', 'format' => 'csv']))->streamedContent();
        $this->assertStringNotContainsString('Walk-in', $csv);
        $this->assertStringContainsString('N/A', $csv);
    }

    // Refund and Damage report statuses are coloured: yellow still waiting,
    // green approved/settled, red declined/written off.
    public function test_refund_and_damage_report_statuses_are_coloured(): void
    {
        $billing = $this->makeSale(1000, now()->format('Y-m-d'));
        foreach ([SalesReturn::STATUS_PENDING, SalesReturn::STATUS_APPROVED, SalesReturn::STATUS_DECLINED] as $status) {
            $return = $this->makeRefund($billing, 100, now()->format('Y-m-d'), 'refund', $status);
            SalesReturnItem::create([
                'SalesReturnID' => $return->SalesReturnID, 'ProductID' => $this->product->ProductID,
                'Quantity' => 1, 'UnitPrice' => 100, 'Reason' => 'Factory Defect',
            ]);
        }
        foreach ([DamagedProduct::STATUS_FOR_SUPPLIER_RETURN, DamagedProduct::STATUS_REPLACEMENT_RECEIVED, DamagedProduct::STATUS_DISPOSED] as $status) {
            DamagedProduct::create([
                'ProductID' => $this->product->ProductID, 'SupplierID' => $this->supplier->SupplierID,
                'Quantity' => 1, 'Description' => 'Cracked', 'DateRecorded' => now()->format('Y-m-d'),
                'DamageType' => 'broken', 'Status' => $status, 'SourceModule' => DamagedProduct::SOURCE_MANUAL,
            ]);
        }

        $refunds = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'returns']))->assertOk()->json('html');
        $this->assertStringContainsString('<span class="badge badge-warning">Pending</span>', $refunds);
        $this->assertStringContainsString('<span class="badge badge-success">Approved</span>', $refunds);
        $this->assertStringContainsString('<span class="badge badge-danger">Declined</span>', $refunds);

        $damage = $this->actingAs($this->admin)->getJson(route('admin.reports.preview', ['type' => 'damage']))->assertOk()->json('html');
        $this->assertStringContainsString('<span class="badge badge-warning">Pending Supplier Return</span>', $damage);
        $this->assertStringContainsString('<span class="badge badge-success">Replacement Received</span>', $damage);
        $this->assertStringContainsString('<span class="badge badge-danger">Disposed</span>', $damage);

        $printed = $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'returns']))->assertOk()->getContent();
        $this->assertStringContainsString('<td class="status-warning">Pending</td>', $printed);
        $this->assertStringContainsString('<td class="status-success">Approved</td>', $printed);
        $this->assertStringContainsString('<td class="status-danger">Declined</td>', $printed);
        $this->assertStringContainsString('td.status-danger { color: #dc2626;', $printed);
    }
}
