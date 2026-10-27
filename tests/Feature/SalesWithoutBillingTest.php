<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// There's no Billing table: SalesTransaction -> SalesItem, with Payment
// linked to the transaction. The sale holds what it came to (subtotal,
// discounts, VAT, total) since the customer pays at once.
class SalesWithoutBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $category = Category::create(['CategoryName' => 'CCTV']);
        $this->product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $category->CategoryID]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
    }

    private function sell(): SalesTransaction
    {
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->product->ProductID, 'qty' => 2]],
            'customer_name' => 'Juan',
            'payment_method' => 'cash',
            'payment_amount' => 3500,
        ])->assertOk();

        return SalesTransaction::with('payment')->latest('SalesTransactionID')->firstOrFail();
    }

    public function test_a_sale_keeps_its_amounts_and_its_payment_points_at_it(): void
    {
        $this->assertFalse(Schema::hasTable('Billing'));
        $this->assertFalse(Schema::hasColumn('Payment', 'BillingID'));

        $sale = $this->sell();

        $this->assertEquals(3000, (float) $sale->Subtotal);
        $this->assertEquals(3000, (float) $sale->TotalAmount);
        $this->assertEquals(360, (float) $sale->VatAmount);
        $this->assertSame('12%', $sale->VatApplied);
        $this->assertSame($sale->SalesTransactionID, $sale->payment->SalesTransactionID);
        $this->assertEquals(3500, (float) $sale->payment->PaymentAmount);
        $this->assertSame($sale->SalesTransactionID, Payment::firstOrFail()->transaction->SalesTransactionID);

        // The receipt still reads the stored breakdown and the change.
        $this->actingAs($this->cashier)->get(route('cashier.receipt', 'RCT-'.str_pad($sale->SalesTransactionID, 6, '0', STR_PAD_LEFT)))
            ->assertOk()->assertSee('3,000.00')->assertSee('500.00');
    }
}
