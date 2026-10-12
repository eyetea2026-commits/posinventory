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

    // Printing the receipt page from the browser (the deployed site, where
    // direct printing is off) through the thermal printer's driver.
    public function test_browser_printed_receipt_matches_the_thermal_slip_without_page_header(): void
    {
        $receipt = $this->sellAndGetReceipt('Dome Camera 2MP', 1500, 2, 'cash', 5000);

        $html = $this->actingAs($this->cashier)->get(route('cashier.receipt', $receipt))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="print-text">(.*?)<pre class="pl-tail">(.*?)</pre>#s', $html);
        preg_match('#<div class="print-text">(.*?)<pre class="pl-tail">(.*?)</pre>#s', $html, $m);
        preg_match_all('#<div class="(pl[^"]*)">(.*?)</div>#s', $m[1], $rows, PREG_SET_ORDER);
        $text = '';
        $classOf = [];
        foreach ($rows as [, $class, $line]) {
            $line = html_entity_decode($line);
            $text .= $line."\n";
            $classOf[trim(preg_replace('/\s+/', ' ', $line))] = $class;
        }
        $text .= html_entity_decode($m[2]);

        // Two blank lines above the header.
        $this->assertSame([' ', ' '], [html_entity_decode($rows[0][2]), html_entity_decode($rows[1][2])]);
        $this->assertSame('CCTV Express', trim(html_entity_decode($rows[2][2])));

        // Same bold / double-height lines as the direct thermal printout.
        $this->assertSame('pl pl-bold pl-tall', $classOf['CCTV Express']);
        $this->assertSame('pl pl-bold', $classOf['SALES RECEIPT']);
        $this->assertSame('pl pl-bold pl-tall', $classOf['TOTAL: P3,000.00']);
        $this->assertSame('pl', $classOf['Your Trusted Security Partner']);
        $this->assertSame('pl', $classOf['Payment: Cash']);
        $this->assertStringContainsString('.print-text .pl-bold { font-weight: bold; }', $html);
        $this->assertStringContainsString('.print-text .pl-tall { transform: scaleY(2);', $html);

        // Same lines as the direct thermal printout: padded columns, "P" amounts.
        $this->assertStringContainsString('2 x P1,500.00', $text);
        $this->assertMatchesRegularExpression('/TOTAL:\s+P3,000\.00/', $text);
        $this->assertMatchesRegularExpression('/Receipt #:\s+'.preg_quote($receipt, '/').'/', $text);
        $this->assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', $text, 'no peso sign or other characters a text-only printer turns into "?"');
        foreach (explode("\n", rtrim($text, "\n")) as $line) {
            $this->assertLessThanOrEqual(32, strlen($line), "too wide for 58mm: [{$line}]");
        }

        // Blank lines after the footer (ending in "." so a text driver keeps
        // them) feed the paper past the tear bar.
        $this->assertMatchesRegularExpression("/Please come again\n{7}\s*\.\n?$/", $text);

        // Zero page margins leave no room for the browser's URL/date header and footer.
        $this->assertStringContainsString('@page { margin: 0; }', $html);
        $this->assertMatchesRegularExpression('/@media print \{.*?\.receipt \{ display: none; \}.*?\.print-text \{\s*display: block;\s*width: 48mm;/s', $html);
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

        // After the footer the paper feeds 6 lines (ESC d 6) so the receipt
        // clears the tear bar before it's torn off.
        $this->assertMatchesRegularExpression("/Please come again\n.*\x1bd\x06/s", $this->capture->bytes);

        // Two blank lines above the "CCTV Express" header.
        $this->assertMatchesRegularExpression("/^ \n \n[^\n]*CCTV Express/", $text);

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

    // After "Yes, Complete Sale" the receipt prints from a hidden frame: no
    // receipt pop-up on screen and no separate browser window.
    public function test_completed_sale_prints_the_receipt_without_a_popup_or_new_window(): void
    {
        $html = $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()->getContent();

        $this->assertStringNotContainsString('window.open(', $html);
        $this->assertStringNotContainsString('Sale Complete — Receipt', $html);
        $this->assertStringNotContainsString('receiptFallbackOverlay', $html);
        $this->assertMatchesRegularExpression('/<iframe id="receiptPrintFrame"[^>]*visibility:hidden;/', $html);
        $this->assertStringContainsString('printReceiptInBackground(receiptUrl);', $html);

        // It prints by itself once loaded (silently in Chrome --kiosk-printing),
        // and only starts the next sale after printing.
        $this->assertMatchesRegularExpression('/frame\.onload = function \(\) \{.*?frame\.contentWindow\.print\(\);/s', $html);
        $this->assertMatchesRegularExpression("/addEventListener\('afterprint', finish\)/", $html);
        $this->assertMatchesRegularExpression("/const finish = function \(\) \{.*?toastSuccess\('Sale complete\. Receipt printed\.'\)\.then\(function \(\) \{\s*window\.location\.reload\(\);/s", $html);
    }
}
