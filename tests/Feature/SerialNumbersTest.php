<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DamagedProduct;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductSerial;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Code128Barcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

// One serial number (SN00001, one count across all products) per piece in
// stock, except categories that don't track them (consumables, wires). The
// serial sticker is that piece's barcode at the POS, and the stickers print
// from the product's View Details as an A4 4 x 10 PDF.
class SerialNumbersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Product $camera;

    private Product $cable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        $cameras = Category::create(['CategoryName' => 'IR CAMERA', 'TracksSerialNumbers' => true]);
        $wires = Category::create(['CategoryName' => 'WIRE', 'TracksSerialNumbers' => false]);
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $cameras->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        $this->cable = Product::create(['ProductName' => 'UTP Cable', 'Model' => 'UTP-1', 'Price' => 20, 'CategoryID' => $wires->CategoryID, 'Barcode' => 'CCTVEX-000002']);
    }

    private function stock(Product $product, int $quantity): Inventory
    {
        return Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $quantity, 'ReorderThreshold' => 5, 'Status' => 'Available']);
    }

    /**
     * @return list<string>
     */
    private function inStockSerials(Product $product): array
    {
        return ProductSerial::where('ProductID', $product->ProductID)->where('Status', ProductSerial::STATUS_IN_STOCK)
            ->orderBy('ProductSerialID')->pluck('SerialNumber')->all();
    }

    public function test_each_piece_received_gets_its_own_serial_except_consumables_and_wires(): void
    {
        $this->stock($this->camera, 10);
        $this->stock($this->cable, 100);

        $this->assertSame(['SN00001', 'SN00002', 'SN00003', 'SN00004', 'SN00005', 'SN00006', 'SN00007', 'SN00008', 'SN00009', 'SN00010'], $this->inStockSerials($this->camera));
        $this->assertSame([], $this->inStockSerials($this->cable));

        // More stock continues the one shared count.
        $inventory = Inventory::where('ProductID', $this->camera->ProductID)->first();
        $inventory->Quantity += 2;
        $inventory->save();
        $this->assertSame(['SN00011', 'SN00012'], array_slice($this->inStockSerials($this->camera), -2));
    }

    /**
     * @param  list<string>  $serialNumbers
     */
    private function adjust(Product $product, int $quantity, string $reason, array $serialNumbers = []): TestResponse
    {
        return $this->actingAs($this->admin)->post(route('admin.stock-adjustments.store'), [
            'ProductID' => $product->ProductID, 'QuantityAdjust' => $quantity, 'Reason' => $reason,
            'SerialNumbers' => $serialNumbers, 'Date' => now()->toDateString(),
        ]);
    }

    public function test_an_adjustment_takes_out_the_ticked_serials(): void
    {
        $this->stock($this->camera, 3);

        $this->adjust($this->camera, 1, StockAdjustment::REASON_LOST, ['SN00002'])->assertSessionHasNoErrors();

        $this->assertSame(['SN00001', 'SN00003'], $this->inStockSerials($this->camera));
        $lost = ProductSerial::where('SerialNumber', 'SN00002')->firstOrFail();
        $this->assertSame(ProductSerial::STATUS_LOST, $lost->Status);
        $this->assertSame(StockAdjustment::firstOrFail()->AdjustmentID, $lost->StockAdjustmentID);
        $this->assertSame(2, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
    }

    public function test_damaged_serials_are_named_on_the_damage_record_and_the_adjustment_list(): void
    {
        $this->stock($this->camera, 3);

        $this->adjust($this->camera, 2, StockAdjustment::REASON_DAMAGED, ['sn00003', 'SN00001'])->assertSessionHasNoErrors();

        $this->assertSame(['SN00002'], $this->inStockSerials($this->camera));
        $this->assertSame([ProductSerial::STATUS_DAMAGED, ProductSerial::STATUS_DAMAGED], ProductSerial::whereIn('SerialNumber', ['SN00001', 'SN00003'])->pluck('Status')->all());
        $this->assertStringContainsString('Serial numbers: SN00001, SN00003', DamagedProduct::firstOrFail()->Description);

        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))->assertOk()->assertSee('SN00001, SN00003');
        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index', ['search' => 'SN00003']))->assertOk()->assertSee('Dome Camera');
        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index', ['search' => 'SN00002']))->assertOk()->assertSee('No Stock Adjustments');
    }

    public function test_a_serial_numbered_adjustment_needs_one_in_stock_serial_per_piece(): void
    {
        $this->stock($this->camera, 3);
        ProductSerial::where('SerialNumber', 'SN00003')->update(['Status' => ProductSerial::STATUS_SOLD]);

        $this->adjust($this->camera, 2, StockAdjustment::REASON_LOST, ['SN00001'])->assertSessionHasErrors('SerialNumbers');
        $this->adjust($this->camera, 1, StockAdjustment::REASON_LOST)->assertSessionHasErrors('SerialNumbers');
        $this->adjust($this->camera, 1, StockAdjustment::REASON_LOST, ['SN00003'])->assertSessionHasErrors('SerialNumbers');
        $this->adjust($this->camera, 1, StockAdjustment::REASON_LOST, ['SN99999'])->assertSessionHasErrors('SerialNumbers');

        $this->assertSame(0, StockAdjustment::count());
        $this->assertSame(['SN00001', 'SN00002'], $this->inStockSerials($this->camera));
        $this->assertSame(3, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
    }

    public function test_products_without_serials_adjust_by_quantity_alone(): void
    {
        $this->stock($this->cable, 100);

        $this->adjust($this->cable, 5, StockAdjustment::REASON_LOST)->assertSessionHasNoErrors();

        $this->assertSame(95, Inventory::where('ProductID', $this->cable->ProductID)->value('Quantity'));
    }

    public function test_the_adjustment_form_offers_the_in_stock_serials_of_each_product(): void
    {
        $this->stock($this->camera, 3);
        $this->stock($this->cable, 100);
        ProductSerial::where('SerialNumber', 'SN00002')->update(['Status' => ProductSerial::STATUS_SOLD]);

        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))->assertOk()
            ->assertSee('data-serials="'.e(json_encode(['SN00001', 'SN00003'])).'"', false)
            ->assertSee('data-serials=""', false)
            // Recorded one row each: the searchable dropdown, then Add Serial.
            ->assertSee('<tbody id="adjustment-serial-rows"></tbody>', false)
            ->assertSee('<select id="SerialNumberSelect" class="form-select" aria-label="Serial Number">', false)
            ->assertSee('id="addSerialNumberBtn"', false)
            ->assertDontSee('id="SerialSearch"', false);
    }

    // Saving from the modal re-renders the page in the background; the
    // modal takes its Product list (stock and serials) from that page, so a
    // serial just taken out isn't offered again.
    public function test_after_an_adjustment_the_form_no_longer_offers_that_serial(): void
    {
        $this->stock($this->camera, 3);

        $this->adjust($this->camera, 1, StockAdjustment::REASON_DAMAGED, ['SN00001'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('admin.stock-adjustments.index'))->assertOk()
            ->assertSee('data-serials="'.e(json_encode(['SN00002', 'SN00003'])).'"', false)
            ->assertSee('Dome Camera (DC-1) — 2 pieces in stock')
            ->assertSee("const newProducts = parsed.querySelector('#ProductID');", false)
            ->assertSee('currentProducts.innerHTML = newProducts.innerHTML;', false);
    }

    public function test_scanning_a_serial_sticker_finds_that_piece(): void
    {
        $this->stock($this->camera, 2);
        $this->stock($this->cable, 5);

        $this->actingAs($this->cashier)->getJson('/api/products/barcode/SN00002')->assertOk()
            ->assertJsonPath('product.ProductID', $this->camera->ProductID)
            ->assertJsonPath('serial', 'SN00002');

        // Serial-numbered products sell by their stickers, not the product barcode.
        $this->actingAs($this->cashier)->getJson('/api/products/barcode/CCTVEX-000001')->assertNotFound()
            ->assertJsonPath('message', 'Scan the serial number sticker on the "Dome Camera" instead.');
        // Consumables and wires keep their product barcode.
        $this->actingAs($this->cashier)->getJson('/api/products/barcode/CCTVEX-000002')->assertOk()
            ->assertJsonPath('product.ProductID', $this->cable->ProductID);

        ProductSerial::where('SerialNumber', 'SN00001')->update(['Status' => ProductSerial::STATUS_SOLD]);
        $this->actingAs($this->cashier)->getJson('/api/products/barcode/SN00001')->assertNotFound()
            ->assertJsonPath('message', 'Serial number SN00001 is not in stock (Sold).');
    }

    public function test_a_sale_records_the_scanned_serials_as_sold(): void
    {
        $this->stock($this->camera, 3);
        $this->stock($this->cable, 50);

        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => 2, 'serials' => ['SN00003', 'sn00001']], ['id' => $this->cable->ProductID, 'qty' => 10]],
            'payment_method' => 'cash', 'payment_amount' => 3200,
        ])->assertOk();

        $this->assertSame(['SN00002'], $this->inStockSerials($this->camera));
        $sold = ProductSerial::whereIn('SerialNumber', ['SN00001', 'SN00003'])->get();
        $this->assertTrue($sold->every(fn ($serial) => $serial->Status === ProductSerial::STATUS_SOLD && $serial->SalesTransactionID !== null));
        $this->assertSame(1, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
        $this->assertSame(40, Inventory::where('ProductID', $this->cable->ProductID)->value('Quantity'));
    }

    public function test_a_sale_without_the_serials_scanned_is_refused(): void
    {
        $this->stock($this->camera, 3);

        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => 2]],
            'payment_method' => 'cash', 'payment_amount' => 3000,
        ])->assertStatus(400)->assertJsonPath('message', 'Scan the serial number sticker of each "Dome Camera" (2 needed, 0 scanned).');

        ProductSerial::where('SerialNumber', 'SN00002')->update(['Status' => ProductSerial::STATUS_SOLD]);
        $this->actingAs($this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $this->camera->ProductID, 'qty' => 2, 'serials' => ['SN00001', 'SN00002']]],
            'payment_method' => 'cash', 'payment_amount' => 3000,
        ])->assertStatus(400)->assertJsonPath('message', 'Serial number SN00002 is not an in-stock "Dome Camera". Remove it from the cart.');

        $this->assertSame(3, Inventory::where('ProductID', $this->camera->ProductID)->value('Quantity'));
    }

    public function test_view_details_lists_the_serials_and_prints_them_as_a_sticker_pdf(): void
    {
        $this->stock($this->camera, 2);

        $html = $this->actingAs($this->admin)->getJson(route('admin.products.show', $this->camera), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json('html');
        $this->assertStringContainsString('<code>SN00001</code>', $html);
        $this->assertStringContainsString('Print Stickers (PDF)', $html);
        $this->assertStringContainsString(route('admin.products.serial-stickers', $this->camera), $html);

        $response = $this->actingAs($this->admin)->get(route('admin.products.serial-stickers', $this->camera));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // Products without serial numbers have no stickers to print.
        $this->stock($this->cable, 5);
        $html = $this->actingAs($this->admin)->getJson(route('admin.products.show', $this->cable), ['X-Requested-With' => 'XMLHttpRequest'])->json('html');
        $this->assertStringNotContainsString('Print Stickers (PDF)', $html);
    }

    public function test_a_category_chooses_whether_its_pieces_get_serial_numbers(): void
    {
        $this->actingAs($this->admin)->get(route('admin.categories.create'))->assertOk()
            ->assertSee('Give each piece a serial number (its barcode sticker)');

        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['CategoryName' => 'PTZ', 'TracksSerialNumbers' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['CategoryName' => 'CONSUMABLES', 'TracksSerialNumbers' => '0'])->assertSessionHasNoErrors();

        $this->assertTrue(Category::where('CategoryName', 'PTZ')->first()->TracksSerialNumbers);
        $this->assertFalse(Category::where('CategoryName', 'CONSUMABLES')->first()->TracksSerialNumbers);
    }

    public function test_the_sticker_barcode_is_code_128_set_b(): void
    {
        $widths = Code128Barcode::widths('SN00001');

        // Start B + 7 characters + check + stop = 112 modules.
        $this->assertSame(112, array_sum($widths));
        $this->assertSame([2, 1, 1, 2, 1, 4], array_slice($widths, 0, 6));
        // Check value 36 (104 + S*1 + N*2 + 0*3 + 0*4 + 0*5 + 0*6 + 1*7 = 654 mod 103).
        $this->assertSame([1, 1, 2, 3, 1, 3], array_slice($widths, 6 + 7 * 6, 6));
    }
}
