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
use RuntimeException;
use Tests\TestCase;

class ThermalReceiptPrintingTest extends TestCase
{
    use RefreshDatabase;

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
    }

    private function enablePrinterWith(?\Closure $factory = null): void
    {
        config(['services.receipt_printer.enabled' => true]);
        $capture = $this->capture;
        $this->app->instance(ThermalReceiptPrinter::class, new ThermalReceiptPrinter($factory ?? fn () => $capture));
    }

    private function sellAndGetReceipt(string $productName = 'Dome Camera 2MP', float $price = 1500, int $qty = 2, string $method = 'cash', float $paid = 5000, ?User $as = null): string
    {
        $product = Product::create([
            'ProductName' => $productName, 'Model' => 'M-'.uniqid(),
            'Price' => $price, 'CostPrice' => $price * 0.6, 'CategoryID' => $this->category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 20, 'Status' => 'Available']);

        $response = $this->actingAs($as ?? $this->cashier)->postJson(route('cashier.process-sale'), [
            'items' => [['id' => $product->ProductID, 'qty' => $qty]],
            'payment_method' => $method,
            'payment_amount' => $paid,
        ]);
        $response->assertOk();

        return $response->json('receipt_number');
    }

    private function printedText(): string
    {
        // Drop ESC/POS control sequences so assertions read like the paper.
        return (string) preg_replace(["/\x1b@/", "/\x1b[aE!]./s", "/\x1d!./s", "/\x1dV.{1,2}/s", "/\x1bd./s", "/\x1bp.../s"], '', $this->capture->bytes);
    }

    public function test_it_is_refused_when_direct_printing_is_turned_off(): void
    {
        $receipt = $this->sellAndGetReceipt();
        $this->app->instance(ThermalReceiptPrinter::class, new ThermalReceiptPrinter(fn () => $this->capture));

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt));

        $response->assertStatus(409);
        $this->assertSame('', $this->capture->bytes);
    }

    public function test_it_prints_the_same_figures_as_the_on_screen_receipt(): void
    {
        $this->enablePrinterWith();
        $receipt = $this->sellAndGetReceipt('Dome Camera 2MP', 1500, 2, 'cash', 5000);

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt), ['new_sale' => true]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, $this->capture->jobs);
        $text = $this->printedText();
        $this->assertStringContainsString($receipt, $text);
        $this->assertStringContainsString('Dome Camera 2MP', $text);
        $this->assertStringContainsString('2 x P1,500.00', $text);
        $this->assertStringContainsString('P3,000.00', $text);
        $this->assertStringContainsString('Gina Cole', $text);
        $this->assertMatchesRegularExpression('/Amount Received:\s+P5,000\.00/', $text);
        $this->assertMatchesRegularExpression('/Change:\s+P2,000\.00/', $text);

        // The on-screen receipt shows the same total.
        $this->actingAs($this->cashier)->get(route('cashier.receipt', $receipt))->assertOk()->assertSee('3,000.00');
    }

    public function test_every_printed_line_fits_58mm_paper_and_is_plain_ascii(): void
    {
        $this->enablePrinterWith();
        $receipt = $this->sellAndGetReceipt('Cámara Domo Ultra-Wide Night Vision 4K Outdoor Bullet Edition', 1234567.89, 1, 'cash', 1300000);

        $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt))->assertOk();

        $text = $this->printedText();
        foreach (explode("\n", $text) as $line) {
            $this->assertLessThanOrEqual(32, strlen($line), "Line too wide for 58mm: \"{$line}\"");
        }
        $this->assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', $this->capture->bytes);
        $this->assertStringContainsString('Camara Domo', $text);
        $this->assertStringContainsString('P1,234,567.89', $text);
    }

    public function test_cash_drawer_opens_only_for_a_new_cash_sale_when_enabled(): void
    {
        config(['services.receipt_printer.open_drawer' => true]);
        $this->enablePrinterWith();
        $receipt = $this->sellAndGetReceipt();

        // Reprint: no drawer pulse (ESC p).
        $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt), ['new_sale' => false])->assertOk();
        $this->assertStringNotContainsString("\x1bp", $this->capture->bytes);

        // Fresh sale: drawer pulse sent.
        $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt), ['new_sale' => true])->assertOk();
        $this->assertStringContainsString("\x1bp", $this->capture->bytes);
    }

    public function test_cash_drawer_stays_shut_by_default(): void
    {
        $this->enablePrinterWith();
        $receipt = $this->sellAndGetReceipt();

        $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt), ['new_sale' => true])->assertOk();

        $this->assertStringNotContainsString("\x1bp", $this->capture->bytes);
    }

    public function test_an_offline_printer_returns_a_clear_error_instead_of_crashing(): void
    {
        $this->enablePrinterWith(fn () => throw new RuntimeException('Failed to copy file to printer'));
        $receipt = $this->sellAndGetReceipt();

        $response = $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt));

        $response->assertStatus(503)->assertJson(['success' => false]);
        $this->assertStringContainsString('Could not reach the receipt printer', $response->json('message'));
    }

    public function test_another_cashiers_receipt_cannot_be_printed(): void
    {
        $this->enablePrinterWith();
        $other = User::factory()->create(['role_id' => $this->cashier->role_id]);
        $receipt = $this->sellAndGetReceipt(as: $other);

        $this->actingAs($this->cashier)->postJson(route('cashier.receipt.thermal', $receipt))->assertNotFound();
        $this->assertSame('', $this->capture->bytes);
    }

    public function test_pos_and_transactions_pages_follow_the_on_off_switch(): void
    {
        $receipt = $this->sellAndGetReceipt();

        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertSee('const THERMAL_PRINTING = false;', false);
        $this->actingAs($this->cashier)->get(route('cashier.transactions'))->assertSee('const RECEIPT_THERMAL_PRINTING = false;', false);

        config(['services.receipt_printer.enabled' => true]);

        $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertSee('const THERMAL_PRINTING = true;', false);
        $this->actingAs($this->cashier)->get(route('cashier.transactions'))
            ->assertSee('const RECEIPT_THERMAL_PRINTING = true;', false)
            ->assertSee("confirmPrintReceipt(event, '{$receipt}'", false);
    }
}
