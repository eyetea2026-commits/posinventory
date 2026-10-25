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

        // The Sales Report details pop-up prints "Printed by" too.
        $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk()
            ->assertSee('<p class="report-printed-by">Printed by Maria Santos Reyes on <span data-printed-at></span></p>', false);
    }
}
