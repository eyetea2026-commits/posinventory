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
    /**
     * Blank lines fed after the footer so the whole receipt clears the tear
     * bar (the Officom's tear bar sits several lines above the print head).
     */
    private const TAIL_LINES = 6;

    /** Blank lines printed above the "CCTV Express" header. */
    private const HEAD_LINES = 2;

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
            $printer->feed(self::TAIL_LINES);
            $printer->cut();
        } finally {
            $printer->close();
        }
    }

    /**
     * The receipt one printed line at a time (centred lines already padded
     * to the paper width), each with the same bold / double-height styling
     * the direct thermal printout uses. The receipt page prints these from
     * the browser, so with a proper thermal driver the slip looks the same
     * as the direct printout.
     *
     * @param  array<string, mixed>  $r
     * @return list<array{text: string, bold: bool, tall: bool}>
     */
    public function printLines(array $r): array
    {
        $out = [];
        foreach ($this->lines($r) as $line) {
            foreach (explode("\n", rtrim($line['text'], "\n")) as $part) {
                $out[] = ['text' => $line['center'] ? $this->center($part) : $part, 'bold' => $line['bold'], 'tall' => $line['tall']];
            }
        }

        return $out;
    }

    /**
     * Blank lines after the footer so the paper feeds past the tear bar (the
     * last lines aren't left inside the printer). Some text-only drivers drop
     * trailing blank lines, so a final "." forces the feed.
     */
    public function tailText(): string
    {
        return str_repeat("\n", self::TAIL_LINES).$this->center('.')."\n";
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function writeReceipt(Printer $printer, array $r): void
    {
        $printer->initialize();

        foreach ($this->lines($r) as $line) {
            $printer->setJustification($line['center'] ? Printer::JUSTIFY_CENTER : Printer::JUSTIFY_LEFT);
            $printer->setEmphasis($line['bold']);
            $printer->setTextSize(1, $line['tall'] ? 2 : 1);
            $printer->text($line['text']);
        }

        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->feed(self::TAIL_LINES);
        // Models without an auto-cutter simply ignore this; the tear bar is used instead.
        $printer->cut();
    }

    /**
     * Every line of the sales receipt, in order. Shared by the thermal
     * printout and the plain-text version so the two never differ.
     *
     * @param  array<string, mixed>  $r
     * @return list<array{text: string, center: bool, bold: bool, tall: bool}>
     */
    private function lines(array $r): array
    {
        $lines = [];
        $add = function (string $text, bool $center = false, bool $bold = false, bool $tall = false) use (&$lines): void {
            $lines[] = ['text' => str_ends_with($text, "\n") ? $text : $text."\n", 'center' => $center, 'bold' => $bold, 'tall' => $tall];
        };
        $rule = str_repeat('-', $this->width);

        // Blank space above the header (a space, so the browser-printed
        // copy keeps the line's height too).
        for ($i = 0; $i < self::HEAD_LINES; $i++) {
            $add(' ');
        }

        // Header
        $add('CCTV Express', center: true, bold: true, tall: true);
        $add('Your Trusted Security Partner', center: true);
        if (config('app.business_address')) {
            $add($this->wrap($this->ascii((string) config('app.business_address'))), center: true);
        }
        $add($rule, center: true);
        $add('SALES RECEIPT', center: true, bold: true);

        // Meta
        $customer = ($r['customerName'] ?? null) === 'Walk-in Customer' ? 'N/A' : ($r['customerName'] ?? null);
        $add($this->row('Receipt #:', (string) $r['receiptNumber']));
        $add($this->row('Date:', (string) $r['date']));
        $add($this->row('Cashier:', $this->ascii((string) $r['cashierName'])));
        if ($customer) {
            $add($this->row('Customer:', $this->ascii((string) $customer)));
        }
        $add($rule);

        // Items: name on its own (wrapped) line, then "qty x price ... amount".
        foreach ($r['items'] as $item) {
            $add($this->wrap($this->ascii((string) $item['name'])));
            $lineTotal = (float) $item['price'] * (int) $item['qty'];
            $add($this->row('  '.$item['qty'].' x '.$this->money((float) $item['price']), $this->money($lineTotal)));
        }
        $add($rule);

        // Discount
        if (! empty($r['hasDiscount'])) {
            if (! empty($r['promoCode'])) {
                $add($this->row('Promo Code:', $this->ascii((string) $r['promoCode'])));
            }
            $add($this->row('Discount ('.$r['discountRate'].'%):', '-'.$this->money((float) $r['discountAmount'])));
            $add($rule);
        }

        // VAT breakdown (prices are VAT-inclusive; this splits the total).
        $total = (float) $r['total'];
        $vat = (float) $r['vatAmount'];
        $add($this->row('VATable Sales:', $this->money($total - $vat)));
        $add($this->row('VAT (12%):', $this->money($vat)));
        $add($this->row('Zero Rated:', $this->money(0)));
        $add($this->row('VAT Exempt:', $this->money(0)));
        $add($rule);

        $add($this->row('TOTAL:', $this->money($total)), bold: true, tall: true);
        $add($rule);

        // Payment
        $method = (string) ($r['paymentMethod'] ?? 'cash');
        $add($this->row('Payment:', $this->methodLabel($method)));
        if (! empty($r['referenceNumber'])) {
            $add($this->row($method === 'cheque' ? 'Cheque No.:' : 'Reference:', $this->ascii((string) $r['referenceNumber'])));
        }
        if (! empty($r['bankName'])) {
            $add($this->row('Bank:', $this->ascii((string) $r['bankName'])));
        }
        if (! empty($r['paymentDate'])) {
            $add($this->row($method === 'cheque' ? 'Cheque Date:' : 'Transfer Date:', Carbon::parse($r['paymentDate'])->format('m/d/Y')));
        }
        if ((float) ($r['paymentAmount'] ?? 0) > 0) {
            $add($this->row('Amount Received:', $this->money((float) $r['paymentAmount'])));
            $add($this->row('Change:', $this->money((float) $r['change'])));
        }
        $add($rule);

        // Footer
        $add('Thank you for your purchase!', center: true);
        $add('Please come again', center: true);

        return $lines;
    }

    private function center(string $text): string
    {
        $pad = max(0, intdiv($this->width - strlen($text), 2));

        return str_repeat(' ', $pad).$text;
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
