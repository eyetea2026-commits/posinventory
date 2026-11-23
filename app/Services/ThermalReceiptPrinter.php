<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class ThermalReceiptPrinter
{
    private const TAIL_LINES = 6;

    private const HEAD_LINES = 2;

    private const QR_MODULE_SIZE = 8;

    private int $width;

    public function __construct(private ?Closure $connectorFactory = null)
    {
        $this->width = max(24, (int) config('services.receipt_printer.chars_per_line', 32));
    }

    public static function isEnabled(): bool
    {
        return (bool) config('services.receipt_printer.enabled', false);
    }

    public function printReceipt(array $receipt, bool $openDrawer = false): void
    {
        $this->printLineSpecs($this->lines($receipt), $openDrawer);
    }

    public function printLineSpecs(array $lines, bool $openDrawer = false): void
    {
        $connector = $this->connectorFactory
            ? ($this->connectorFactory)()
            : new WindowsPrintConnector((string) config('services.receipt_printer.name', 'POS58'));

        $printer = new Printer($connector);

        try {
            $this->writeReceipt($printer, $lines);

            if ($openDrawer) {
                $printer->pulse();
            }
        } finally {
            $printer->close();
        }
    }

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

    public function printLines(array $r): array
    {
        return $this->browserLines($this->lines($r));
    }

    public function browserLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            if (! empty($line['qr'])) {
                $out[] = ['text' => '', 'bold' => false, 'tall' => false, 'qr' => $line['qr']];

                continue;
            }
            foreach (explode("\n", rtrim($line['text'], "\n")) as $part) {
                $out[] = ['text' => $line['center'] ? $this->center($part) : $part, 'bold' => $line['bold'], 'tall' => $line['tall']];
            }
        }

        return $out;
    }

    public function tailText(): string
    {
        return str_repeat("\n", self::TAIL_LINES).$this->center('.')."\n";
    }

    private function writeReceipt(Printer $printer, array $lines): void
    {
        $printer->initialize();

        foreach ($lines as $line) {
            $printer->setJustification($line['center'] ? Printer::JUSTIFY_CENTER : Printer::JUSTIFY_LEFT);

            if (! empty($line['qr'])) {
                $printer->qrCode($line['qr'], Printer::QR_ECLEVEL_M, self::QR_MODULE_SIZE);
                $printer->text($line['text']);

                continue;
            }

            $printer->setEmphasis($line['bold']);
            $printer->setTextSize(1, $line['tall'] ? 2 : 1);
            $printer->text($line['text']);
        }

        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->feed(self::TAIL_LINES);
        $printer->cut();
    }

    public function lines(array $r): array
    {
        $lines = [];
        $add = function (string $text, bool $center = false, bool $bold = false, bool $tall = false) use (&$lines): void {
            $lines[] = ['text' => str_ends_with($text, "\n") ? $text : $text."\n", 'center' => $center, 'bold' => $bold, 'tall' => $tall];
        };
        $rule = str_repeat('-', $this->width);

        for ($i = 0; $i < self::HEAD_LINES; $i++) {
            $add(' ');
        }

        $add('CCTV Express', center: true, bold: true, tall: true);
        $add('Your Trusted Security Partner', center: true);
        if (config('app.business_address')) {
            $add($this->wrap($this->ascii((string) config('app.business_address'))), center: true);
        }
        $add($rule, center: true);
        $add('SALES RECEIPT', center: true, bold: true);

        $customer = ($r['customerName'] ?? null) === 'Walk-in Customer' ? 'N/A' : ($r['customerName'] ?? null);
        $add($this->row('Receipt #:', (string) $r['receiptNumber']));
        $add($this->row('Date:', (string) $r['date']));
        $add($this->row('Cashier:', $this->ascii((string) $r['cashierName'])));
        if ($customer) {
            $add($this->row('Customer:', $this->ascii((string) $customer)));
        }
        $add($rule);

        foreach ($r['items'] as $item) {
            $add($this->wrap($this->ascii((string) $item['name'])));
            $lineTotal = round((float) $item['price'] * (float) $item['qty'], 2);
            $add($this->row('  '.($item['qty_label'] ?? $item['qty']).' x '.$this->money((float) $item['price']), $this->money($lineTotal)));
        }
        $add($rule);

        if (! empty($r['hasDiscount'])) {
            if (! empty($r['promoCode'])) {
                $add($this->row('Promo Code:', $this->ascii((string) $r['promoCode'])));
            }
            $add($this->row('Discount ('.$r['discountRate'].'%):', '-'.$this->money((float) $r['discountAmount'])));
            $add($rule);
        }
        if (! empty($r['customDiscountPercent'])) {
            $add($this->row('Discount ('.$this->percent((float) $r['customDiscountPercent']).'):', '-'.$this->money((float) $r['customDiscountAmount'])));
            $add($rule);
        }

        $total = (float) $r['total'];
        $vat = (float) $r['vatAmount'];
        $add($this->row('VATable Sales:', $this->money($total - $vat)));
        $add($this->row('VAT (12%):', $this->money($vat)));
        $add($this->row('Zero Rated:', $this->money(0)));
        $add($this->row('VAT Exempt:', $this->money(0)));
        $add($rule);

        $add($this->row('TOTAL:', $this->money($total)), bold: true, tall: true);
        $add($rule);

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

        $add('Thank you for your purchase!', center: true);
        $add('Please come again', center: true);

        $lines[] = ['text' => "\n", 'center' => true, 'bold' => false, 'tall' => false, 'qr' => (string) $r['receiptNumber']];

        return $lines;
    }

    public function refundLines(array $r): array
    {
        $lines = [];
        $add = function (string $text, bool $center = false, bool $bold = false, bool $tall = false) use (&$lines): void {
            $lines[] = ['text' => str_ends_with($text, "\n") ? $text : $text."\n", 'center' => $center, 'bold' => $bold, 'tall' => $tall];
        };
        $rule = str_repeat('-', $this->width);

        for ($i = 0; $i < self::HEAD_LINES; $i++) {
            $add(' ');
        }

        $add('CCTV Express', center: true, bold: true, tall: true);
        $add('Your Trusted Security Partner', center: true);
        if (config('app.business_address')) {
            $add($this->wrap($this->ascii((string) config('app.business_address'))), center: true);
        }
        $add($rule, center: true);
        $add('REFUND RECEIPT', center: true, bold: true);

        $add($this->row('Refund #:', (string) $r['receiptNumber']));
        $add($this->row('Date:', (string) $r['date']));
        $add($this->row('Cashier:', $this->ascii((string) $r['cashierName'])));
        $add($this->row('Orig. Receipt:', (string) $r['originalReceipt']));
        if (! empty($r['customerName'])) {
            $add($this->row('Customer:', $this->ascii((string) $r['customerName'])));
        }
        $add($rule);

        $add('RETURNED ITEM(S)', bold: true);
        foreach ($r['items'] as $item) {
            $add($this->wrap($this->ascii((string) $item['name'])));
            $add($this->row('  '.$item['qty_label'].' x '.$this->money((float) $item['price']), $this->money((float) $item['amount'])));
            $add($this->row('  Reason:', $this->ascii((string) $item['reason'])));
        }
        $add($rule);

        $add($this->row('REFUND:', $this->money((float) $r['refundAmount'])), bold: true, tall: true);
        $add($rule);

        $method = (string) ($r['refundMethod'] ?? 'cash');
        $add($this->row('Refund Method:', $this->methodLabel($method)));
        if (! empty($r['referenceNumber'])) {
            $add($this->row('Reference:', $this->ascii((string) $r['referenceNumber'])));
        }
        $add($rule);

        $add('Refund processed.', center: true);
        $add('Thank you!', center: true);

        return $lines;
    }

    public function printRefundReceipt(array $refund): void
    {
        $this->printLineSpecs($this->refundLines($refund));
    }

    private function center(string $text): string
    {
        $pad = max(0, intdiv($this->width - strlen($text), 2));

        return str_repeat(' ', $pad).$text;
    }

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

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }

    private function money(float $amount): string
    {
        return 'P'.number_format($amount, 2);
    }

    private function ascii(string $text): string
    {
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
