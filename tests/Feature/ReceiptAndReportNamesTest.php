<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The receipt shows the cashier's middle name as an initial ("Gina J.
// Cole"); a printed or exported report fills "Prepared By" with the name of
// the Administrator who printed it.
class ReceiptAndReportNamesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::create(['role_name' => 'admin'])->id,
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Reyes',
        ]);
        $this->cashier = User::factory()->create([
            'role_id' => Role::create(['role_name' => 'cashier'])->id,
            'first_name' => 'Gina', 'middle_name' => 'Jack', 'last_name' => 'Cole',
        ]);
    }

    public function test_the_middle_name_becomes_an_initial(): void
    {
        $this->assertSame('Gina J. Cole', $this->cashier->name_with_middle_initial);
        $this->assertSame('Gina Jack Cole', $this->cashier->full_name);

        $this->assertSame('Ana D. Cruz', (new User(['first_name' => 'Ana', 'middle_name' => 'dela Paz', 'last_name' => 'Cruz']))->name_with_middle_initial);
        $this->assertSame('Ana G. Cruz', (new User(['first_name' => 'Ana', 'middle_name' => 'G', 'last_name' => 'Cruz']))->name_with_middle_initial);
        $this->assertSame('Ana Cruz', (new User(['first_name' => 'Ana', 'middle_name' => '  ', 'last_name' => 'Cruz']))->name_with_middle_initial);
    }

    public function test_the_receipt_shows_the_cashiers_middle_initial(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV']);
        $product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $category->CategoryID]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 5, 'Status' => 'Available']);

        $receipt = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 1]],
            'payment_method' => 'cash', 'payment_amount' => 1500,
        ])->assertOk()->json('receipt_number');

        $this->actingAs($this->cashier)->get(route('cashier.receipt', $receipt))->assertOk()
            ->assertSee('Gina J. Cole')
            ->assertDontSee('Gina Jack Cole');

        // The thermal printout uses the same name.
        $lines = $this->actingAs($this->cashier)->getJson(route('cashier.receipt.print-lines', $receipt))->assertOk()->getContent();
        $this->assertStringContainsString('Gina J. Cole', $lines);
        $this->assertStringNotContainsString('Gina Jack Cole', $lines);
    }

    public function test_a_printed_report_is_prepared_by_whoever_printed_it(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))->assertOk()
            ->assertSee('<strong>Maria Santos Reyes</strong>&nbsp;<br><small>Administrator</small><br><small class="sig-caption">Prepared By</small>', false);

        // So is a View Details Print Preview.
        $category = Category::create(['CategoryName' => 'IP CAMERA']);
        $product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $category->CategoryID]);
        $this->actingAs($this->admin)->get(route('admin.reports.details.print', ['type' => 'inventory', 'id' => $product->ProductID]))->assertOk()
            ->assertSee('<strong>Maria Santos Reyes</strong>&nbsp;<br><small>Administrator</small><br><small class="sig-caption">Prepared By</small>', false);
    }

    public function test_reports_have_no_checked_by_or_approved_by_signature(): void
    {
        foreach (['sales', 'inventory', 'damage', 'returns', 'stock_adjustment', 'receiving', 'orders'] as $type) {
            $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => $type]))->assertOk()
                ->assertSee('<small class="sig-caption">Prepared By</small>', false)
                ->assertDontSee('Checked By')
                ->assertDontSee('Approved By')
                ->assertDontSee('<small>Manager</small>', false)
                ->assertDontSee('<small>Owner</small>', false);
        }

        // The one signature, Prepared By, is centered on the page (print and PDF).
        $this->actingAs($this->admin)->get(route('admin.reports.print', ['type' => 'sales']))->assertOk()
            ->assertSee('.signature-area { display: flex; justify-content: center;', false)
            ->assertSee('.signature-block { flex: 0 0 32%; width: 32%; margin: 0 auto;', false);
        $this->assertStringContainsString('.signature-block { flex: 0 0 32%; width: 32%; margin: 0 auto;', file_get_contents(resource_path('views/admin/reports/pdf.blade.php')));

        // The exported PDF uses the same footer.
        $this->actingAs($this->admin)->view('admin.reports.partials.report-footer', ['pdf' => true])
            ->assertSee('Prepared By')
            ->assertSee('Confidential Report')
            ->assertDontSee('Checked By')
            ->assertDontSee('Approved By');
    }
}
