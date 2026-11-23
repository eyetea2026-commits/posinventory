<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChequeAndBankTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PaymentProof::DISK);

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id]);

        $category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
        $this->product = Product::create([
            'ProductName' => 'Test Camera', 'Model' => 'CAM-X'.uniqid(),
            'Price' => 1000, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 50, 'Status' => 'Available']);
    }

    private function baseCheckoutPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_amount' => 1120,
        ], $overrides);

        if (in_array($payload['payment_method'] ?? null, PaymentProof::METHODS, true) && ! array_key_exists('payment_proof', $payload)) {
            $payload['payment_proof'] = UploadedFile::fake()->image('proof.jpg');
        }

        return $payload;
    }

    public function test_valid_cheque_payment_persists_all_details(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'cheque',
            'reference_number' => 'CHK-000123',
            'bank_name' => 'BDO',
            'account_name' => 'Juan Dela Cruz',
            'payment_date' => '2026-08-15',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $payment = Payment::where('PaymentMethod', 'cheque')->firstOrFail();
        $this->assertSame('CHK-000123', $payment->ReferenceNumber);
        $this->assertSame('BDO', $payment->BankName);
        $this->assertSame('Juan Dela Cruz', $payment->AccountName);
        $this->assertSame('2026-08-15', $payment->PaymentDate->format('Y-m-d'));
    }

    public function test_valid_bank_transfer_persists_all_details(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'bank',
            'reference_number' => 'BT-REF-9988',
            'bank_name' => 'BPI',
            'account_name' => 'Maria Santos',
            'payment_date' => '2026-08-15',
            'payment_time' => '14:30',
            'remarks' => 'Paid via mobile app',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $payment = Payment::where('PaymentMethod', 'bank')->firstOrFail();
        $this->assertSame('BT-REF-9988', $payment->ReferenceNumber);
        $this->assertSame('BPI', $payment->BankName);
        $this->assertSame('Maria Santos', $payment->AccountName);
        $this->assertSame('Paid via mobile app', $payment->Remarks);
        $this->assertNotNull($payment->PaymentTime);
    }

    public function test_cheque_rejects_missing_required_fields(): void
    {
        $required = ['reference_number', 'bank_name', 'account_name', 'payment_date'];

        foreach ($required as $field) {
            $payload = $this->baseCheckoutPayload([
                'payment_method' => 'cheque',
                'reference_number' => 'CHK-1',
                'bank_name' => 'BDO',
                'account_name' => 'Juan Dela Cruz',
                'payment_date' => '2026-08-15',
            ]);
            unset($payload[$field]);

            $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $payload);

            $response->assertStatus(422);
        }
    }

    public function test_bank_transfer_rejects_missing_required_fields(): void
    {
        $required = ['reference_number', 'bank_name', 'account_name', 'payment_date', 'payment_time'];

        foreach ($required as $field) {
            $payload = $this->baseCheckoutPayload([
                'payment_method' => 'bank',
                'reference_number' => 'BT-1',
                'bank_name' => 'BPI',
                'account_name' => 'Maria Santos',
                'payment_date' => '2026-08-15',
                'payment_time' => '14:30',
            ]);
            unset($payload[$field]);

            $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $payload);

            $response->assertStatus(422);
        }
    }

    public function test_duplicate_cheque_number_is_rejected(): void
    {
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'cheque',
            'reference_number' => 'CHK-DUP-1',
            'bank_name' => 'BDO', 'account_name' => 'Juan Dela Cruz', 'payment_date' => '2026-08-15',
        ]))->assertOk();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'cheque',
            'reference_number' => 'CHK-DUP-1',
            'bank_name' => 'Metrobank', 'account_name' => 'Someone Else', 'payment_date' => '2026-08-16',
        ]));

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $this->assertSame(1, Payment::where('ReferenceNumber', 'CHK-DUP-1')->count());
    }

    public function test_same_reference_number_is_allowed_across_different_payment_methods(): void
    {
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'cheque',
            'reference_number' => 'SHARED-REF',
            'bank_name' => 'BDO', 'account_name' => 'Juan Dela Cruz', 'payment_date' => '2026-08-15',
        ]))->assertOk();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'bank',
            'reference_number' => 'SHARED-REF',
            'bank_name' => 'BPI', 'account_name' => 'Maria Santos', 'payment_date' => '2026-08-15', 'payment_time' => '10:00',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_cash_still_succeeds_without_any_payment_detail_fields(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'cash',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $payment = Payment::where('PaymentMethod', 'cash')->firstOrFail();
        $this->assertNull($payment->ReferenceNumber);
    }

    public function test_gcash_still_succeeds_without_a_reference_number(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'gcash',
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_gcash_reference_number_is_now_actually_persisted(): void
    {
        $response = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-REF-777',
        ]));

        $response->assertOk();
        $payment = Payment::where('PaymentMethod', 'gcash')->firstOrFail();
        $this->assertSame('GCASH-REF-777', $payment->ReferenceNumber);
    }

    public function test_receipt_shows_cheque_details(): void
    {
        $sale = $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->baseCheckoutPayload([
            'payment_method' => 'cheque',
            'reference_number' => 'CHK-999',
            'bank_name' => 'BDO', 'account_name' => 'Juan Dela Cruz', 'payment_date' => '2026-08-15',
        ]));
        $sale->assertOk();

        $receipt = $this->actingAs($this->cashier)->get(route('cashier.receipt', $sale->json('receipt_number')));

        $receipt->assertOk();
        $receipt->assertViewHas('referenceNumber', 'CHK-999');
        $receipt->assertViewHas('bankName', 'BDO');
        $receipt->assertViewHas('accountName', 'Juan Dela Cruz');
        $receipt->assertSee('CHK-999');
        $receipt->assertSee('BDO');
    }
}
