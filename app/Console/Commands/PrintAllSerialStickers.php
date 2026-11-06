<?php

namespace App\Console\Commands;

use App\Models\ProductSerial;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * One printable PDF with the serial sticker of every in-stock piece of
 * every product (the same A4 4 x 10 price-tag stickers as Products → View →
 * Print Stickers). Built from the command line because thousands of
 * stickers take longer than a web page may. Each product starts on a new
 * page, so its stickers stay together.
 */
class PrintAllSerialStickers extends Command
{
    protected $signature = 'serials:stickers
        {--output= : Where to save the PDF (default storage/app/stickers/All-Serial-Stickers.pdf)}';

    protected $description = 'Save one PDF with the serial stickers of every in-stock piece of every product';

    private const STICKERS_PER_PAGE = 40;

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        $serials = ProductSerial::with('product')
            ->where('Status', ProductSerial::STATUS_IN_STOCK)
            ->whereHas('product')
            ->get()
            ->sortBy(fn (ProductSerial $serial) => [mb_strtolower($serial->product->ProductName), $serial->ProductSerialID])
            ->values();

        if ($serials->isEmpty()) {
            $this->warn('There are no in-stock serial numbers to print.');

            return self::SUCCESS;
        }

        /** @var Collection<int, Collection<int, ProductSerial>> $pages */
        $pages = $serials->groupBy('ProductID')
            ->flatMap(fn (Collection $productSerials) => $productSerials->chunk(self::STICKERS_PER_PAGE))
            ->values();

        $output = $this->option('output') ?: storage_path('app/stickers/All-Serial-Stickers.pdf');
        File::ensureDirectoryExists(dirname($output));

        Pdf::loadView('admin.products.serial-stickers-pdf', [
            'pages' => $pages,
            'title' => 'Serial Stickers - All Products',
        ])->setPaper('a4')->save($output);

        $this->info(sprintf(
            'Saved %d sticker(s) for %d product(s) on %d page(s): %s',
            $serials->count(),
            $serials->pluck('ProductID')->unique()->count(),
            $pages->count(),
            $output,
        ));

        return self::SUCCESS;
    }
}
