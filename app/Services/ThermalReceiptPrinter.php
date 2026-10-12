<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

/**
 * Prints the sales receipt straight to a 58mm ESC/POS thermal printer
 * (Officom OC-58IIH) shared in Windows, with no browser print dialog.
 *
 * Only usable when this Laravel server runs on the same PC as the printer
 * (the local XAMPP setup), so it's gated behind
 * config('services.receipt_printer.enabled'), which is off by default.
 * Receipt figures come from the exact same array the on-screen receipt view
 * uses (CashierAuthController::buildReceiptData()), so paper and screen can
 * never disagree.
 */
class ThermalReceiptPrinter
{
    private int $width;

    /**
     * @param  (Closure(): PrintConnector)|null  $connectorFactory  Overridable so tests can capture the bytes instead of printing.
     */
    public function __construct(private ?Closure $connectorFactory = null)
    {
        $this->width = max(24, (int) config('services.receipt_printer.chars_per_line', 32));
    }

    public static function isEnabled(): bool
    {
        return (bool) config('services.receipt_printer.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $receipt  Output of CashierAuthController::buildReceiptData().
     *
     * @throws \Throwable when the printer can't be reached (off, unplugged, not shared).
     */
    public function printReceipt(array $receipt, bool $openDrawer = false): void
    {
        $connector = $this->connectorFactory
            ? ($this->connectorFactory)()
            : new WindowsPrintConnector((string) config('services.receipt_printer.name', 'POS58'));

        $printer = new Printer($connector);

        try {
            $this->writeReceipt($printer, $receipt);

            if ($openDrawer) {
                $printer->pulse();
            }
        } finally {
            // Sends the buffered job to the printer; always called so a
            // half-built job never lingers.
            $printer->close();
        }
    }

    /**
     * A short slip used by `php artisan pos:printer-test`.
     */
    public function printTestPage(): void
    {
        $connector = $this->connectorFactory
            ? ($this->connectorFactory)()
            : new WindowsPrintConnector((string) config('services.receipt_printer.name', 'POS58'));

        $printer = new Printer($connector);

        try {
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("CCTV EXPRESS\n");
            $printer->setEmphasis(false);
            $printer->text("Printer test OK\n");
            $printer->text(now()->format('m/d/Y h:i A')."\n");
            $printer->text(str_repeat('-', $this->width)."\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text($this->row('Left', 'Right')."\n");
            $printer->text($this->row('Amount', $this->money(1234567.89))."\n");
            $printer->feed(3);
            $printer->cut();
        } finally {
            $printer->close();
        }
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function writeReceipt(Printer $printer, array $r): void
    {
        $rule = str_repeat('-', $this->width)."\n";

        $printer->initialize();

        // Header
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2);
        $printer->text("CCTV Express\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text("Your Trusted Security Partner\n");
        if (config('app.business_address')) {
            $printer->text($this->wrap($this->ascii((string) config('app.business_address'))));
        }
        $printer->text($rule);
        $printer->setEmphasis(true);
        $printer->text("SALES RECEIPT\n");
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        // Meta
        $customer = ($r['customerName'] ?? null) === 'Walk-in Customer' ? 'N/A' : ($r['customerName'] ?? null);
        $printer->text($this->row('Receipt #:', (string) $r['receiptNumber'])."\n");
        $printer->text($this->row('Date:', (string) $r['date'])."\n");
        $printer->text($this->row('Cashier:', $this->ascii((string) $r['cashierName']))."\n");
        if ($customer) {
            $printer->text($this->row('Customer:', $this->ascii((string) $customer))."\n");
        }
        $printer->text($rule);

        // Items: name on its own (wrapped) line, then "qty x price ... amount".
        foreach ($r['items'] as $item) {
            $printer->text($this->wrap($this->ascii((string) $item['name'])));
            $lineTotal = (float) $item['price'] * (int) $item['qty'];
            $printer->text($this->row('  '.$item['qty'].' x '.$this->money((float) $item['price']), $this->money($lineTotal))."\n");
        }
        $printer->text($rule);

        // Discount
        if (! empty($r['hasDiscount'])) {
            if (! empty($r['promoCode'])) {
                $printer->text($this->row('Promo Code:', $this->ascii((string) $r['promoCode']))."\n");
            }
            $printer->text($this->row('Discount ('.$r['discountRate'].'%):', '-'.$this->money((float) $r['discountAmount']))."\n");
            $printer->text($rule);
        }

        // VAT breakdown (prices are VAT-inclusive; this splits the total).
        $total = (float) $r['total'];
        $vat = (float) $r['vatAmount'];
        $printer->text($this->row('VATable Sales:', $this->money($total - $vat))."\n");
        $printer->text($this->row('VAT (12%):', $this->money($vat))."\n");
        $printer->text($this->row('Zero Rated:', $this->money(0))."\n");
        $printer->text($this->row('VAT Exempt:', $this->money(0))."\n");
        $printer->text($rule);

        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2);
        $printer->text($this->row('TOTAL:', $this->money($total))."\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text($rule);

        // Payment
        $method = (string) ($r['paymentMethod'] ?? 'cash');
        $printer->text($this->row('Payment:', $this->methodLabel($method))."\n");
        if (! empty($r['referenceNumber'])) {
            $printer->text($this->row($method === 'cheque' ? 'Cheque No.:' : 'Reference:', $this->ascii((string) $r['referenceNumber']))."\n");
        }
        if (! empty($r['bankName'])) {
            $printer->text($this->row('Bank:', $this->ascii((string) $r['bankName']))."\n");
        }
        if (! empty($r['paymentDate'])) {
            $printer->text($this->row($method === 'cheque' ? 'Cheque Date:' : 'Transfer Date:', Carbon::parse($r['paymentDate'])->format('m/d/Y'))."\n");
        }
        if ((float) ($r['paymentAmount'] ?? 0) > 0) {
            $printer->text($this->row('Amount Received:', $this->money((float) $r['paymentAmount']))."\n");
            $printer->text($this->row('Change:', $this->money((float) $r['change']))."\n");
        }
        $printer->text($rule);

        // Footer
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Thank you for your purchase!\n");
        $printer->text("Please come again\n");
        $printer->feed(3);
        // Models without an auto-cutter simply ignore this; the tear bar is used instead.
        $printer->cut();
    }

    /**
     * Left label and right value on one line, padded to the paper width.
     * If they don't fit together, the value drops to its own right-aligned line.
     */
    private function row(string $left, string $right): string
    {
        $space = $this->width - strlen($left) - strlen($right);

        if ($space >= 1) {
            return $left.str_repeat(' ', $space).$right;
        }

        return rtrim($this->wrap($left))."\n".str_pad($right, $this->width, ' ', STR_PAD_LEFT);
    }

    private function wrap(string $text): string
    {
        return wordwrap($text, $this->width, "\n", true)."\n";
    }

    /**
     * The printer's built-in code page has no peso sign, and accented or
     * other non-ASCII characters print as garbage, so everything sent is
     * plain ASCII ("P" stands in for the peso sign).
     */
    private function money(float $amount): string
    {
        return 'P'.number_format($amount, 2);
    }

    private function ascii(string $text): string
    {
        // Str::ascii maps accents properly (á -> a); iconv's Windows
        // transliteration turns them into stray apostrophes instead.
        return trim((string) preg_replace('/[^\x20-\x7E]/', '', Str::ascii($text)));
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'gcash' => 'GCash',
            'bank' => 'Bank Transfer',
            'cheque' => 'Cheque',
            default => 'Cash',
        };
    }
}
