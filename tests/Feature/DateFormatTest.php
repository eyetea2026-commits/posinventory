<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

// Dates are shown as MM/DD/YYYY everywhere in the system (admin, cashier,
// printouts and exports).
class DateFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
    }

    private function adjustmentOn(string $date): void
    {
        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Barcode' => 'BC-1', 'Price' => 1000, 'CostPrice' => 600, 'CategoryID' => $category->CategoryID]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
        StockAdjustment::create(['ProductID' => $product->ProductID, 'QuantityAdjust' => -1, 'Reason' => StockAdjustment::REASON_LOST, 'Date' => $date]);
    }

    public function test_admin_list_shows_dates_as_mm_dd_yyyy(): void
    {
        $this->adjustmentOn('2026-10-05');

        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))
            ->assertOk()->assertSee('10/05/2026')->assertDontSee('Oct 05, 2026');
    }

    public function test_csv_export_writes_dates_as_mm_dd_yyyy(): void
    {
        $this->adjustmentOn('2026-10-05');

        $csv = $this->actingAs($this->admin)->get(route('admin.reports.export', ['type' => 'stock_adjustment', 'format' => 'csv']))->streamedContent();

        $this->assertStringContainsString('10/05/2026', $csv);
        $this->assertStringNotContainsString('2026-10-05', $csv);
    }

    public function test_no_view_or_class_formats_dates_as_month_names(): void
    {
        // Long month-name date formats ("Oct 05, 2026", "October 5, 2026")
        // must not come back; chart axis labels without a year are fine.
        $files = Finder::create()->files()->name('*.php')
            ->in([app_path(), resource_path('views')])->exclude('vendor');

        $offenders = [];
        foreach ($files as $file) {
            if (preg_match_all("/format\\(['\"][^'\"]*\\b[FM] [dj], Y/", $file->getContents(), $m)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Use m/d/Y for displayed dates.');
    }
}
