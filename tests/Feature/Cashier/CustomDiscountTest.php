<?php

namespace Tests\Feature\Cashier;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// A custom % discount at the POS needs an Administrator's password.
class CustomDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $this->admin = User::factory()->create([
            'role_id' => Role::create(['role_name' => 'admin'])->id,
            'first_name' => 'Owner', 'last_name' => 'Admin', 'password' => 'Owner-Pass-123',
        ]);
    }

    private function makeProduct(float $price, int $stock = 50): Product
    {
        $category = Category::firstOrCreate(['CategoryName' => 'CCTV'], ['Description' => 'Cameras']);
        $product = Product::create([
            'ProductName' => 'Camera '.uniqid(), 'Model' => 'CAM-'.uniqid(),
            'Price' => $price, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $stock, 'Status' => 'Available']);

        return $product;
    }

    private function approve(float $percent, string $password = 'Owner-Pass-123')
    {
        return $this->actingAs($this->cashier)->postJson(route('cashier.custom-discount.approve'), [
            'percent' => $percent, 'admin_password' => $password,
        ]);
    }

    private function sell(array $items, float $paid, ?float $percent)
    {
        return $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => $items, 'payment_method' => 'cash', 'payment_amount' => $paid,
            'custom_discount_percent' => $percent,
        ]);
    }

    public function test_an_approved_discount_lowers_the_total_and_is_recorded(): void
    {
        $camera = $this->makeProduct(1000);
        $cable = $this->makeProduct(500);

        $this->approve(10)->assertOk()->assertJson(['success' => true, 'percent' => 10, 'approved_by' => 'Owner Admin']);

        $sale = $this->sell([['id' => $camera->ProductID, 'qty' => 1], ['id' => $cable->ProductID, 'qty' => 2]], 1800, 10)->assertOk();

        $billing = Billing::latest('BillingID')->firstOrFail();
        $this->assertEquals(2000, (float) $billing->Subtotal);
        $this->assertEquals(10, (float) $billing->CustomDiscountPercent);
        $this->assertEquals(200, (float) $billing->CustomDiscountAmount);
        $this->assertEquals(200, (float) $billing->DiscountAmount);
        $this->assertEquals(1800, (float) $billing->BillingAmount);
        $this->assertSame($this->admin->id, $billing->CustomDiscountApprovedBy);

        // Split across the lines, so a refund pays back what was paid.
        $this->assertEquals([100.0, 100.0], SalesItem::orderBy('SalesItemID')->pluck('DiscountAmount')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals([900.0, 450.0], SalesItem::orderBy('SalesItemID')->get()->map(fn ($item) => round($item->refundable_unit_price, 2))->all());

        // Shown on the receipt.
        $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')))
            ->assertOk()->assertSee('Discount (10%):')->assertSee('-₱200.00');

        $this->assertTrue(ActivityLog::where('action', 'pos.custom_discount_used')->exists());
    }

    public function test_a_discount_without_approval_is_refused(): void
    {
        $camera = $this->makeProduct(1000);

        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 900, 10)
            ->assertStatus(403)
            ->assertJson(['success' => false]);
        $this->assertSame(0, Billing::count());
    }

    public function test_the_wrong_password_or_a_cashier_password_is_refused(): void
    {
        $this->approve(10, 'wrong')->assertStatus(422)->assertJson(['message' => 'Incorrect Administrator password.']);
        // The cashier's own password is not an Administrator's.
        $this->cashier->update(['password' => 'Cashier-Pass-123']);
        $this->approve(10, 'Cashier-Pass-123')->assertStatus(422);
        $this->assertTrue(ActivityLog::where('action', 'pos.custom_discount_denied')->exists());

        $camera = $this->makeProduct(1000);
        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 900, 10)->assertStatus(403);
    }

    public function test_an_approval_covers_only_its_own_percent_and_only_one_sale(): void
    {
        $camera = $this->makeProduct(1000);
        $this->approve(10)->assertOk();

        // A bigger percent than approved is refused.
        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 500, 50)->assertStatus(403);

        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 900, 10)->assertOk();
        // Used up: the next sale needs a new approval.
        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 900, 10)->assertStatus(403);
    }

    public function test_an_approval_expires(): void
    {
        $camera = $this->makeProduct(1000);
        $this->approve(10)->assertOk();

        $this->travel(16)->minutes();

        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 900, 10)->assertStatus(403);
    }

    public function test_the_percent_is_a_two_digit_whole_number(): void
    {
        // (Approvals are limited to 5 tries a minute.)
        foreach ([0, 100, 12.5] as $percent) {
            $this->approve($percent)->assertStatus(422)->assertJsonValidationErrors('percent');
        }
        $this->approve(99)->assertOk();

        $camera = $this->makeProduct(1000);
        $this->sell([['id' => $camera->ProductID, 'qty' => 1]], 0, 100)->assertStatus(422);
    }

    public function test_the_pos_has_the_discount_field_above_salable_sales_and_the_new_customer_label(): void
    {
        $html = $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()->getContent();

        $this->assertStringContainsString('<label>Customer / Company Name (Optional)</label>', $html);
        $this->assertStringContainsString('placeholder="Enter customer or company name..."', $html);
        $field = strpos($html, '<label for="custom-discount-input">Discount (%)</label>');
        $this->assertNotFalse($field);
        $this->assertLessThan(strpos($html, '<span>Salable Sales</span>'), $field);
        $this->assertStringContainsString('custom_discount_percent: customDiscount ? customDiscount.percent : null,', $html);
        // Two digits only.
        $this->assertStringContainsString('<input type="text" id="custom-discount-input" inputmode="numeric" maxlength="2"', $html);
        // Approval popup in the same design as User Management's "Enter Your Credentials".
        $this->assertStringContainsString('<h2 id="approval-modal-title"><i class="fas fa-shield-halved"></i> Administrator Approval</h2>', $html);
        $this->assertStringContainsString('<input type="password" id="approval-password" class="approval-input"', $html);
        $this->assertStringContainsString('<i class="fas fa-check"></i> Confirm Password', $html);
        $this->assertStringNotContainsString("title: 'Administrator Approval'", $html);
    }
}
