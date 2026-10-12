<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ThermalReceiptPrinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Tests\TestCase;

class PrintBridgeTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://cctvexpresstacurong.com';

    private User $cashier;

    private Category $category;

    /** Captures what would have been sent to the printer. */
    private object $capture;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'cashier']);
        $this->cashier = User::factory()->create(['role_id' => $role->id, 'first_name' => 'Gina', 'last_name' => 'Cole']);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);

        $this->capture = new class implements PrintConnector
        {
            public string $bytes = '';

            public int $jobs = 0;

            public function __destruct() {}

            public function finalize()
            {
                $this->jobs++;
            }

            public function read($len)
            {
                return false;
            }

            public function write($data)
            {
                $this->bytes .= $data;
            }
        };

        config([
            'services.receipt_printer.enabled' => true,
            'services.receipt_printer.bridge_origins' => self::SITE,
        ]);
        $capture = $this->capture;
        $this->app->instance(ThermalReceiptPrinter::class, new ThermalReceiptPrinter(fn () => $capture));
    }

    private function sell(?User $as = null): string
    {
        $product = Product::create([
            'ProductName' => 'Dome Camera 2MP', 'Model' => 'M-'.uniqid(),
            'Price' => 1500, 'CostPrice' => 900, 'CategoryID' => $this->category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        return $this->actingAs($as ?? $this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => 2]],
            'payment_method' => 'cash',
            'payment_amount' => 5000,
        ])->assertOk()->json('receipt_number');
    }

    /**
     * @return list<array{text: string, center: bool, bold: bool, tall: bool}>
     */
    private function sampleLines(): array
    {
        return [
            ['text' => "CCTV Express\n", 'center' => true, 'bold' => true, 'tall' => true],
            ['text' => "TOTAL:                 P3,000.00\n", 'center' => false, 'bold' => true, 'tall' => true],
        ];
    }

    private function bridge(array $payload, string $origin = self::SITE, string $ip = '127.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['Origin' => $origin])
            ->postJson(route('print-bridge'), $payload);
    }

    public function test_the_deployed_sites_receipt_prints_exactly_like_a_local_sale(): void
    {
        $receipt = $this->sell();

        // What the local POS prints directly...
        $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt))->assertOk();
        $local = $this->capture->bytes;
        $this->capture->bytes = '';

        // ...and what the deployed POS sends through the bridge.
        $lines = $this->actingAs($this->cashier)->getJson(route('cashier.receipt.print-lines', $receipt))
            ->assertOk()
            ->assertJsonPath('payment_method', 'cash')
            ->json('lines');
        $this->bridge(['lines' => $lines])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertHeader('Access-Control-Allow-Origin', self::SITE)
            ->assertHeader('Access-Control-Allow-Private-Network', 'true');

        $this->assertNotSame('', $local);
        $this->assertSame($local, $this->capture->bytes);
    }

    public function test_preflight_from_the_deployed_site_is_answered(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->options(route('print-bridge'), [], [
            'Origin' => self::SITE,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Private-Network' => 'true',
        ]);

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', self::SITE);
        $response->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Private-Network', 'true');
        $this->assertSame('', $this->capture->bytes);
    }

    public function test_other_websites_cannot_print(): void
    {
        $this->bridge(['lines' => $this->sampleLines()], 'https://evil.example')
            ->assertForbidden()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->bridge(['lines' => $this->sampleLines()], '')->assertForbidden();

        $this->assertSame('', $this->capture->bytes);
    }

    public function test_other_devices_on_the_network_cannot_print(): void
    {
        $this->bridge(['lines' => $this->sampleLines()], self::SITE, '192.168.1.25')->assertForbidden();

        $this->assertSame('', $this->capture->bytes);
    }

    public function test_it_is_refused_when_this_pc_has_direct_printing_off(): void
    {
        config(['services.receipt_printer.enabled' => false]);

        $this->bridge(['lines' => $this->sampleLines()])->assertStatus(409);

        $this->assertSame('', $this->capture->bytes);
    }

    public function test_invalid_receipts_are_rejected(): void
    {
        $this->bridge([])->assertStatus(422);
        $this->bridge(['lines' => [['text' => 'x']]])->assertStatus(422);
        $this->bridge(['lines' => array_fill(0, 301, $this->sampleLines()[0])])->assertStatus(422);
        $this->bridge(['lines' => [['text' => str_repeat('x', 501), 'center' => false, 'bold' => false, 'tall' => false]]])->assertStatus(422);

        $this->assertSame('', $this->capture->bytes);
    }

    public function test_only_plain_text_reaches_the_printer(): void
    {
        $this->bridge(['lines' => [['text' => "Cámara ₱10\x1b@\x1dV\n", 'center' => false, 'bold' => false, 'tall' => false]]])->assertOk();

        $this->assertStringContainsString("Cmara 10@V\n", $this->capture->bytes);
        $this->assertStringNotContainsString("\x1b@\x1dV", $this->capture->bytes);
    }

    public function test_cash_drawer_opens_only_when_asked_and_enabled_on_this_pc(): void
    {
        $this->bridge(['lines' => $this->sampleLines(), 'open_drawer' => true])->assertOk();
        $this->assertStringNotContainsString("\x1bp", $this->capture->bytes);

        config(['services.receipt_printer.open_drawer' => true]);
        $this->bridge(['lines' => $this->sampleLines(), 'open_drawer' => false])->assertOk();
        $this->assertStringNotContainsString("\x1bp", $this->capture->bytes);

        $this->bridge(['lines' => $this->sampleLines(), 'open_drawer' => true])->assertOk();
        $this->assertStringContainsString("\x1bp", $this->capture->bytes);
    }

    public function test_an_offline_printer_returns_a_clear_error(): void
    {
        $this->app->instance(ThermalReceiptPrinter::class, new ThermalReceiptPrinter(fn () => throw new \RuntimeException('Failed to copy file to printer')));

        $this->bridge(['lines' => $this->sampleLines()])
            ->assertStatus(503)
            ->assertJson(['success' => false])
            ->assertHeader('Access-Control-Allow-Origin', self::SITE);
    }

    public function test_receipt_lines_are_only_given_for_the_cashiers_own_sale(): void
    {
        $other = User::factory()->create(['role_id' => $this->cashier->role_id]);
        $receipt = $this->sell($other);

        $this->actingAs($this->cashier)->getJson(route('cashier.receipt.print-lines', $receipt))->assertNotFound();
        $this->actingAs($other)->getJson(route('cashier.receipt.print-lines', $receipt))->assertOk();
    }

    public function test_receipt_lines_need_a_login(): void
    {
        $receipt = $this->sell();
        auth()->logout();

        $this->getJson(route('cashier.receipt.print-lines', $receipt))->assertUnauthorized();
    }

    public function test_pages_use_the_bridge_only_where_direct_printing_is_off(): void
    {
        config([
            'services.receipt_printer.enabled' => false,
            'services.receipt_printer.bridge_url' => 'http://127.0.0.1:8000/print-bridge',
        ]);

        $this->actingAs($this->cashier)->get(route('cashier.pos'))
            ->assertSee('window.PRINT_BRIDGE_URL = "http:\/\/127.0.0.1:8000\/print-bridge";', false)
            ->assertSee('window.printViaBridge(data.receipt_number, true)', false);
        $this->actingAs($this->cashier)->get(route('cashier.transactions'))
            ->assertSee('window.PRINT_BRIDGE_URL = "http:\/\/127.0.0.1:8000\/print-bridge";', false)
            ->assertSee('window.printViaBridge(receiptNumber, false)', false);

        // Locally the POS prints directly, so the bridge stays unused.
        config(['services.receipt_printer.enabled' => true]);
        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertSee('window.PRINT_BRIDGE_URL = "";', false);
    }
}
