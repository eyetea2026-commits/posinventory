<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories whose products are sold by the piece without a serial
     * number (they keep using the product barcode).
     *
     * @var list<string>
     */
    private const CATEGORIES_WITHOUT_SERIALS = ['ACCESSORIES', 'FIBER CONNECTOR', 'CONSUMABLES', 'WIRE'];

    /**
     * One serial number (SN00001, one count across all products) per piece
     * in stock, for every category except consumables and wires.
     */
    public function up(): void
    {
        Schema::table('Category', function (Blueprint $table) {
            $table->boolean('TracksSerialNumbers')->default(false);
        });

        DB::table('Category')
            ->whereNotIn(DB::raw('UPPER(TRIM(CategoryName))'), self::CATEGORIES_WITHOUT_SERIALS)
            ->update(['TracksSerialNumbers' => true]);

        Schema::create('ProductSerial', function (Blueprint $table) {
            $table->increments('ProductSerialID');
            $table->unsignedInteger('ProductID');
            // Filled in right after insert from the id (SN00001...).
            $table->string('SerialNumber', 20)->nullable()->unique();
            $table->string('Status', 30)->default('In Stock');
            $table->unsignedInteger('SalesTransactionID')->nullable();
            $table->dateTime('StatusDate')->nullable();
            $table->timestamps();

            $table->foreign('ProductID')->references('ProductID')->on('Product')->onDelete('cascade');
            $table->index(['ProductID', 'Status']);
        });

        // Pieces already in stock get their serial numbers now.
        $inStock = DB::table('Inventory')
            ->join('Product', 'Product.ProductID', '=', 'Inventory.ProductID')
            ->join('Category', 'Category.CategoryID', '=', 'Product.CategoryID')
            ->where('Category.TracksSerialNumbers', true)
            ->where('Inventory.Quantity', '>', 0)
            ->orderBy('Inventory.ProductID')
            ->get(['Inventory.ProductID', 'Inventory.Quantity']);

        foreach ($inStock as $row) {
            for ($i = 0; $i < (int) $row->Quantity; $i++) {
                $id = DB::table('ProductSerial')->insertGetId([
                    'ProductID' => $row->ProductID,
                    'Status' => 'In Stock',
                    'StatusDate' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'ProductSerialID');
                DB::table('ProductSerial')->where('ProductSerialID', $id)
                    ->update(['SerialNumber' => 'SN'.str_pad((string) $id, 5, '0', STR_PAD_LEFT)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ProductSerial');

        Schema::table('Category', function (Blueprint $table) {
            $table->dropColumn('TracksSerialNumbers');
        });
    }
};
