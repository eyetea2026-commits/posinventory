<?php

namespace App\Console\Commands;

use App\Services\ThermalReceiptPrinter;
use Illuminate\Console\Command;
use Throwable;

class PrinterTest extends Command
{
    protected $signature = 'pos:printer-test';

    protected $description = 'Print a short test slip on the local thermal receipt printer';

    public function handle(ThermalReceiptPrinter $printer): int
    {
        $name = config('services.receipt_printer.name');

        if (! ThermalReceiptPrinter::isEnabled()) {
            $this->warn('Direct thermal printing is OFF (set POS_PRINTER_ENABLED=true in .env). Testing the printer anyway...');
        }

        try {
            $printer->printTestPage();
        } catch (Throwable $e) {
            $this->error("Could not print to \"{$name}\": {$e->getMessage()}");
            $this->line('Check the printer is on, has paper, and is shared in Windows under that exact share name.');

            return self::FAILURE;
        }

        $this->info("Test slip sent to \"{$name}\".");

        return self::SUCCESS;
    }
}
