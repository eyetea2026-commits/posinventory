<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSerial;
use App\Services\SerialNumbers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives every in-stock piece of a serial-numbered product its serial
 * number. Serials are normally made when stock goes up (InventoryObserver),
 * but stock copied straight into the database never got them. Only adds
 * what's missing; never retires or renumbers an existing serial.
 */
class GenerateMissingSerials extends Command
{
    protected $signature = 'serials:generate {--dry-run : Only list what would be made}';

    protected $description = 'Make the missing serial numbers so every in-stock piece of a serial-numbered product has one';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $total = 0;

        $products = Product::with(['category', 'inventory'])->orderBy('ProductID')->get()
            ->filter(fn (Product $product) => $product->tracksSerialNumbers());

        foreach ($products as $product) {
            $stock = max(0, (int) ($product->inventory?->Quantity ?? 0));
            $inStock = ProductSerial::where('ProductID', $product->ProductID)
                ->where('Status', ProductSerial::STATUS_IN_STOCK)
                ->count();
            $missing = $stock - $inStock;

            if ($missing <= 0) {
                continue;
            }

            if (! $dryRun) {
                DB::transaction(fn () => SerialNumbers::create($product->ProductID, $missing));
            }

            $rows[] = [$product->ProductID, $product->ProductName, $product->category?->CategoryName, $stock, $inStock, $missing];
            $total += $missing;
        }

        if ($rows) {
            $this->table(['ID', 'Product', 'Category', 'Stock', 'Had serials', $dryRun ? 'Would make' : 'Made'], $rows);
        }

        $this->info(sprintf(
            '%s %d serial number(s) for %d product(s); %d serial-numbered product(s) checked.',
            $dryRun ? 'Would make' : 'Made',
            $total,
            count($rows),
            $products->count(),
        ));

        return self::SUCCESS;
    }
}
