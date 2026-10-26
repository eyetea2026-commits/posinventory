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

// GCash, Bank Transfer and Cheque sales can carry an optional proof of
// payment photo. It's kept on the private disk and only shown to the
// cashier who made the sale and to Administrators.
class PaymentProofTest extends TestCase
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
        $category = Category::create(['CategoryName' => 'CCTV']);
        $this->product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $category->CategoryID]);
        Inventory::create(['ProductID' => $this->product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sale(array $overrides = []): array
    {
        return array_merge([
            'items' => [['id' => $this->product->ProductID, 'qty' => 1]],
            'payment_method' => 'gcash',
            'payment_amount' => 1500,
            'reference_number' => 'GC-123',
        ], $overrides);
    }

    public function test_a_gcash_sale_keeps_its_proof_photo_privately(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.process-sale'), $this->sale([
            'payment_proof' => UploadedFile::fake()->image('gcash.jpg', 1200, 2400),
        ]), ['Accept' => 'application/json'])->assertOk()->assertJsonPath('success', true);

        $payment = Payment::firstOrFail();
        $this->assertNotNull($payment->ProofImagePath);
        Storage::disk(PaymentProof::DISK)->assertExists($payment->ProofImagePath);
        $this->assertStringStartsWith('payment-proofs/', $payment->ProofImagePath);

        // The cashier who made the sale and Administrators can open it.
        $this->actingAs($this->cashier)->get(route('payments.proof', $payment))->assertOk();
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->actingAs($admin)->get(route('payments.proof', $payment))->assertOk();

        // Another cashier can't, and nobody signed out can.
        $otherCashier = User::factory()->create(['role_id' => $this->cashier->role_id]);
        $this->actingAs($otherCashier)->get(route('payments.proof', $payment))->assertForbidden();
        auth()->logout();
        $this->get(route('payments.proof', $payment))->assertRedirect();

        // Transaction History shows a Proof button on the sale.
        $this->actingAs($this->cashier)->get(route('cashier.transactions'))->assertOk()
            ->assertSee(route('payments.proof', $payment), false);
    }

    public function test_the_proof_is_optional_and_only_for_gcash_bank_and_cheque(): void
    {
        // No photo: the sale goes through as before.
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), $this->sale())->assertOk();
        $this->assertNull(Payment::firstOrFail()->ProofImagePath);

        // A photo sent with a cash sale is ignored.
        $this->actingAs($this->cashier)->post(route('cashier.process-sale'), $this->sale([
            'payment_method' => 'cash', 'reference_number' => null,
            'payment_proof' => UploadedFile::fake()->image('cash.jpg'),
        ]), ['Accept' => 'application/json'])->assertOk();
        $this->assertNull(Payment::latest('PaymentID')->firstOrFail()->ProofImagePath);
        $this->assertSame([], Storage::disk(PaymentProof::DISK)->allFiles());
    }

    public function test_a_proof_must_be_a_photo_of_5_mb_or_less(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.process-sale'), $this->sale([
            'payment_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('payment_proof');

        $this->actingAs($this->cashier)->post(route('cashier.process-sale'), $this->sale([
            'payment_proof' => UploadedFile::fake()->image('huge.jpg')->size(6000),
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('payment_proof');

        $this->assertSame(0, Payment::count());
    }

    public function test_the_proof_photo_is_removed_when_the_sale_fails(): void
    {
        $this->actingAs($this->cashier)->post(route('cashier.process-sale'), $this->sale([
            'items' => [['id' => $this->product->ProductID, 'qty' => 99]],
            'payment_proof' => UploadedFile::fake()->image('gcash.jpg'),
        ]), ['Accept' => 'application/json'])->assertStatus(400);

        $this->assertSame(0, Payment::count());
        $this->assertSame([], Storage::disk(PaymentProof::DISK)->allFiles());
    }

    public function test_the_pos_offers_the_proof_field(): void
    {
        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()
            ->assertSee('<input type="file" id="payment-proof" accept="image/jpeg,image/png,image/webp"', false)
            ->assertSee("const PAYMENT_PROOF_METHODS = ['gcash', 'bank', 'cheque'];", false)
            ->assertSee("formData.append('payment_proof', proofFile);", false);
    }
}
