<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductImage;
use Illuminate\Console\Command;
use Throwable;

/**
 * Attaches a folder of product photos in one go. Each file is matched to a
 * product by its file name (without the extension): the product's SKU
 * (SKU-000041), barcode (CCTVEX-000041), or exact product name. Photos are
 * stored shrunk, like uploads from Edit Product.
 */
class ImportProductPhotos extends Command
{
    protected $signature = 'products:import-photos
                            {folder : Folder holding the photos (jpg, jpeg, png, webp)}
                            {--replace : Also replace photos products already have}';

    protected $description = 'Attach product photos from a folder, matched by SKU, barcode or product name';

    public function handle(): int
    {
        $folder = rtrim((string) $this->argument('folder'), '\\/');
        if (! is_dir($folder)) {
            $this->error("Folder not found: {$folder}");

            return self::FAILURE;
        }

        $files = glob($folder.DIRECTORY_SEPARATOR.'*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
        $products = Product::all();
        $attached = 0;
        $unmatched = [];

        foreach ($files as $file) {
            $key = strtolower(trim(pathinfo($file, PATHINFO_FILENAME)));
            $product = $products->first(fn (Product $p) => in_array($key, array_map('strtolower', array_filter([
                (string) $p->SKU, (string) $p->Barcode, trim((string) $p->ProductName),
            ])), true));

            if (! $product) {
                $unmatched[] = basename($file);

                continue;
            }

            if ($product->ImagePath && ! $this->option('replace')) {
                $this->line("Skipped (already has a photo): {$product->ProductName}");

                continue;
            }

            try {
                $old = $product->ImagePath;
                $product->update(['ImagePath' => ProductImage::storeFromPath($file)]);
                ProductImage::delete($old);
                $attached++;
                $this->info("Attached: {$product->ProductName} <- ".basename($file));
            } catch (Throwable $e) {
                $this->warn('Could not read '.basename($file).': '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("{$attached} photo(s) attached.");
        if ($unmatched) {
            $this->warn('No product matched: '.implode(', ', $unmatched));
        }

        return self::SUCCESS;
    }
}
