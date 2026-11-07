<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\Role;
use App\Models\SalesReturn;
use App\Models\SalesTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// A piece refunded as Damaged / Factory Defect is the exact serial the
// customer brought back, and that sticker can never be sold at the POS again.
class ReturnedSerialsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Product $camera;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        $cameras = Category::create(['CategoryName' => 'IP CAMERA', 'TracksSerialNumbers' => true]);
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $cameras->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        Inventory::create(['ProductID' => $this->camera->ProductID, 'Quantity' => 3, 'ReorderThreshold' => 1, 'Status' => 'Available']);
    }

    /**
     * @param  list<string>  $serials
     */
    private function sell(array $serials): SalesTransaction
    {
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => count($serials), 'serials' => $serials]],
            'payment_method' => 'cash', 'payment_amount' => 1500 * count($serials),
        ])->assertOk();

        return SalesTransaction::latest('SalesTransactionID')->firstOrFail();
    }

    /**
     * @param  list<string>|null  $serials
     */
    private function requestReturn(SalesTransaction $sale, int $quantity, ?array $serials = null)
    {
        return $this->actingAs($this->cashier)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $sale->SalesTransactionID,
            'items' => [array_filter([
                'product_id' => $this->camera->ProductID, 'quantity' => $quantity, 'reason_code' => 'damaged_product', 'serials' => $serials,
            ], fn ($value) => $value !== null)],
            'return_type' => 'refund',
        ]);
    }

    private function serialStatus(string $serialNumber): string
    {
        return ProductSerial::where('SerialNumber', $serialNumber)->value('Status');
    }

    public function test_the_returned_piece_is_marked_damaged_and_can_never_be_sold_again(): void
    {
        $sale = $this->sell(['SN00001', 'SN00002']);

        // The receipt lists the stickers that can come back.
        $this->actingAs($this->cashier)->getJson(route('cashier.refunds.transaction', $sale->SalesTransactionID))->assertOk()
            ->assertJsonPath('transaction.items.0.TracksSerials', true)
            ->assertJsonPath('transaction.items.0.SerialNumbers', ['SN00001', 'SN00002']);

        // Two were sold, one comes back: which one has to be said.
        $this->requestReturn($sale, 1)->assertStatus(400)
            ->assertJsonPath('message', 'Pick the serial number of each "Dome Camera" being returned (1 needed).');
        $this->requestReturn($sale, 1, ['SN00003'])->assertStatus(400)
            ->assertJsonPath('message', 'Serial number SN00003 wasn\'t sold as "Dome Camera" on this receipt, or is already being returned.');
        $this->requestReturn($sale, 1, ['sn00002'])->assertOk()->assertJson(['success' => true]);
        $return = SalesReturn::latest('SalesReturnID')->firstOrFail();
        $this->assertSame($return->SalesReturnID, ProductSerial::where('SerialNumber', 'SN00002')->value('SalesReturnID'));
        $this->assertNull(ProductSerial::where('SerialNumber', 'SN00001')->value('SalesReturnID'));

        // Approved as Damaged: that exact piece is Damaged, the other stays sold.
        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return))->assertRedirect();
        $this->assertSame(ProductSerial::STATUS_DAMAGED, $this->serialStatus('SN00002'));
        $this->assertSame(ProductSerial::STATUS_SOLD, $this->serialStatus('SN00001'));
        $this->assertStringContainsString('Serial SN00002', DamagedProduct::where('SalesReturnID', $return->SalesReturnID)->value('Description'));

        // Refunded: nothing goes back to stock.
        $this->actingAs($this->cashier)->postJson(route('cashier.refunds.process', $return), ['refund_method' => 'cash'])->assertOk();
        $this->assertSame(ProductSerial::STATUS_DAMAGED, $this->serialStatus('SN00002'));
        $this->assertSame(1, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));

        // The POS refuses its sticker, scanned or sent with a sale.
        $this->actingAs($this->cashier)->getJson('/api/products/barcode/SN00002')->assertNotFound()
            ->assertJsonPath('message', 'Serial number SN00002 is a damaged / defective piece (refunded on Return #'.str_pad((string) $return->SalesReturnID, 6, '0', STR_PAD_LEFT).') and cannot be sold.');
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => 1, 'serials' => ['SN00002']]],
            'payment_method' => 'cash', 'payment_amount' => 1500,
        ])->assertStatus(400);
        $this->assertSame(ProductSerial::STATUS_DAMAGED, $this->serialStatus('SN00002'));
    }

    public function test_one_piece_bought_and_returned_is_picked_by_itself_and_a_declined_return_releases_it(): void
    {
        $sale = $this->sell(['SN00003']);

        $this->requestReturn($sale, 1)->assertOk();
        $return = SalesReturn::latest('SalesReturnID')->firstOrFail();
        $this->assertSame($return->SalesReturnID, ProductSerial::where('SerialNumber', 'SN00003')->value('SalesReturnID'));

        // Already on a return: it can't be on a second one.
        $this->actingAs($this->cashier)->getJson(route('cashier.refunds.transaction', $sale->SalesTransactionID))
            ->assertJsonPath('transaction.items.0.SerialNumbers', []);

        // Declined: the customer keeps it, so it's no longer on a return and stays sold.
        $this->actingAs($this->admin)->post(route('admin.sales-returns.decline', $return), ['DeclineReason' => 'No defect found'])->assertRedirect();
        $this->assertNull(ProductSerial::where('SerialNumber', 'SN00003')->value('SalesReturnID'));
        $this->assertSame(ProductSerial::STATUS_SOLD, $this->serialStatus('SN00003'));
    }

    public function test_a_sale_without_recorded_serials_can_still_be_returned(): void
    {
        $sale = $this->sell(['SN00001']);
        // Sold before serials were tracked: no piece is linked to the sale.
        ProductSerial::where('SerialNumber', 'SN00001')->update(['SalesTransactionID' => null]);

        $this->actingAs($this->cashier)->getJson(route('cashier.refunds.transaction', $sale->SalesTransactionID))
            ->assertJsonPath('transaction.items.0.SerialNumbers', []);
        $this->requestReturn($sale, 1)->assertOk()->assertJson(['success' => true]);
    }

    public function test_refunds_made_before_the_serial_was_recorded_are_marked_damaged(): void
    {
        $sale = $this->sell(['SN00001', 'SN00002']);
        $this->requestReturn($sale, 1, ['SN00001'])->assertOk();
        $return = SalesReturn::latest('SalesReturnID')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.sales-returns.approve', $return))->assertRedirect();

        // As it was before: no link, both pieces still "Sold".
        DB::table('ProductSerial')->update(['SalesReturnID' => null, 'Status' => ProductSerial::STATUS_SOLD]);
        $migration = require database_path('migrations/2026_11_07_115108_add_sales_return_id_to_product_serial_table.php');
        $migration->down();
        $migration->up();

        $damaged = ProductSerial::where('SalesReturnID', $return->SalesReturnID)->get();
        $this->assertCount(1, $damaged);
        $this->assertSame(ProductSerial::STATUS_DAMAGED, $damaged->first()->Status);
    }
}
