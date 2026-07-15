<?php

namespace Tests\Feature\Cashier;

use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Replacement;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesReturn;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $cashierUser;
    private Staff $staff;
    private Product $product;
    private Product $replacementProduct;
    private SalesTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashierUser = User::factory()->create(['role_id' => $cashierRole->id]);
        $this->staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe',
            'ContactNumber' => '0000', 'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F',
            'UserID' => $this->cashierUser->id,
        ]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);

        $this->product = Product::create([
            'ProductName' => 'DVR Camera', 'Model' => 'CAM-01', 'SKU' => 'SKU-001',
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 5, 'Status' => 'Available']);

        $this->replacementProduct = Product::create([
            'ProductName' => 'DVR Camera (New Unit)', 'Model' => 'CAM-01B', 'SKU' => 'SKU-002',
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->replacementProduct->ProductID, 'Quantity' => 3, 'Status' => 'Available']);

        $this->transaction = SalesTransaction::create([
            'CustomerName' => 'Walk-in Customer',
            'SalesTransactionDate' => now(),
            'StaffID' => $this->staff->StaffID,
        ]);

        SalesItem::create([
            'Quantity' => 2, 'UnitPrice' => 1000,
            'ProductID' => $this->product->ProductID,
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
        ]);

        $discount = Discount::create(['DiscountRate' => 0]);
        $billing = Billing::create([
            'CustomerName' => 'Walk-in Customer', 'VatApplied' => '12%', 'BillingAmount' => 2000,
            'BillingDate' => now(), 'DiscountID' => $discount->DiscountID,
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
        ]);
        Payment::create([
            'PaymentAmount' => 2000, 'PaymentMethod' => 'cash',
            'ReceiptNumber' => 'RCT-' . str_pad($this->transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT),
            'BillingID' => $billing->BillingID,
        ]);
    }

    private function makeReturn(array $overrides = []): SalesReturn
    {
        return SalesReturn::create(array_merge([
            'SalesTransactionID' => $this->transaction->SalesTransactionID,
            'ProductID' => $this->product->ProductID,
            'Quantity' => 1,
            'Reason' => 'Factory Defect',
            'ReturnType' => 'refund',
            'ReturnDate' => now()->format('Y-m-d'),
            'Status' => 'pending',
            'StaffID' => $this->staff->StaffID,
        ], $overrides));
    }

    public function test_process_refund_rejects_non_approved_status(): void
    {
        $return = $this->makeReturn(['Status' => 'pending']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertStatus(400);
        $this->assertSame(5, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
    }

    public function test_process_refund_rejects_replacement_type_requests(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'ReturnType' => 'replacement']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertStatus(400);
    }

    public function test_process_refund_restores_inventory_and_marks_processed(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'Quantity' => 2]);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process', $return),
            ['refund_method' => 'cash']
        );

        $response->assertJson(['success' => true]);
        $return->refresh();
        $this->assertSame('processed', $return->Status);
        $this->assertEquals(2000, $return->RefundAmount);
        $this->assertSame(7, Inventory::where('ProductID', $this->product->ProductID)->first()->Quantity);
        $this->assertTrue(ActivityLog::where('Action', 'return.refund_processed')->exists());
    }

    public function test_process_replacement_rejects_non_approved_or_wrong_type(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'ReturnType' => 'refund']);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process-replacement', $return),
            ['replacement_product_id' => $this->replacementProduct->ProductID, 'quantity' => 1]
        );

        $response->assertStatus(400);
    }

    public function test_process_replacement_decrements_stock_and_creates_record(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'ReturnType' => 'replacement', 'Quantity' => 1]);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process-replacement', $return),
            ['replacement_product_id' => $this->replacementProduct->ProductID, 'quantity' => 1]
        );

        $response->assertJson(['success' => true]);
        $return->refresh();
        $this->assertSame('processed', $return->Status);
        $this->assertSame(2, Inventory::where('ProductID', $this->replacementProduct->ProductID)->first()->Quantity);
        $this->assertDatabaseCount('Replacement', 1);
        $this->assertTrue(ActivityLog::where('Action', 'return.replacement_processed')->exists());
    }

    public function test_process_replacement_fails_cleanly_on_insufficient_stock(): void
    {
        $return = $this->makeReturn(['Status' => 'approved', 'ReturnType' => 'replacement', 'Quantity' => 5]);

        $response = $this->actingAs($this->cashierUser)->postJson(
            route('cashier.refunds.process-replacement', $return),
            ['replacement_product_id' => $this->replacementProduct->ProductID, 'quantity' => 5]
        );

        $response->assertStatus(400);
        $this->assertSame(3, Inventory::where('ProductID', $this->replacementProduct->ProductID)->first()->Quantity);
        $this->assertDatabaseCount('Replacement', 0);
        $this->assertSame('approved', $return->fresh()->Status);
    }

    public function test_search_transaction_by_receipt_and_customer_and_barcode(): void
    {
        $byReceipt = $this->actingAs($this->cashierUser)->getJson(
            route('cashier.refunds.search', ['mode' => 'receipt', 'q' => 'RCT-' . str_pad($this->transaction->SalesTransactionID, 6, '0', STR_PAD_LEFT)])
        );
        $byReceipt->assertJson(['success' => true, 'multiple' => false]);

        $byCustomer = $this->actingAs($this->cashierUser)->getJson(
            route('cashier.refunds.search', ['mode' => 'customer', 'q' => 'Walk-in'])
        );
        $byCustomer->assertJson(['success' => true]);

        $byBarcode = $this->actingAs($this->cashierUser)->getJson(
            route('cashier.refunds.search', ['mode' => 'barcode', 'q' => 'NONEXISTENT'])
        );
        $byBarcode->assertStatus(404);
    }
}
