<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DropUnusedLegacyColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_22_152835_drop_unused_legacy_columns.php');
    }

    public function test_the_unused_columns_are_gone(): void
    {
        $this->assertFalse(Schema::hasColumn('SalesReturn', 'ProductID'));
        $this->assertFalse(Schema::hasColumn('SalesReturn', 'Quantity'));
        $this->assertFalse(Schema::hasColumn('SalesReturn', 'Reason'));
        $this->assertFalse(Schema::hasColumn('Discount', 'ProductID'));
        $this->assertFalse(Schema::hasColumn('PurchaseOrderItem', 'ReceiptNumber'));

        $this->assertTrue(Schema::hasColumn('SalesReturn', 'CustomerName'));
        $this->assertTrue(Schema::hasColumn('ProductSerial', 'SalesReturnID'));
        $this->assertTrue(Schema::hasColumns('Product', ['SellsByPurchaseUnit', 'AllowsFractionalQuantity']));
    }

    public function test_it_can_be_rolled_back_and_run_again(): void
    {
        $this->migration()->down();
        $this->assertTrue(Schema::hasColumns('SalesReturn', ['ProductID', 'Quantity', 'Reason']));
        $this->assertTrue(Schema::hasColumn('Discount', 'ProductID'));
        $this->assertTrue(Schema::hasColumn('PurchaseOrderItem', 'ReceiptNumber'));

        $this->migration()->up();
        $this->assertFalse(Schema::hasColumn('SalesReturn', 'Reason'));
        $this->assertFalse(Schema::hasColumn('PurchaseOrderItem', 'ReceiptNumber'));
    }

    public function test_nothing_is_dropped_while_a_column_still_has_data(): void
    {
        $this->migration()->down();
        DB::table('Discount')->insert(['Name' => 'Old promo', 'PromoCode' => 'OLD1', 'DiscountType' => 'percentage', 'DiscountRate' => 5, 'Status' => 'active']);

        $category = Category::create(['CategoryName' => 'CCTV']);
        $product = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 100, 'CostPrice' => 50, 'CategoryID' => $category->CategoryID, 'Barcode' => 'CCTVEX-000001']);

        DB::table('Discount')->update(['ProductID' => $product->ProductID]);

        try {
            $this->migration()->up();
            $this->fail('The migration should have stopped.');
        } catch (RuntimeException $error) {
            $this->assertSame('Discount.ProductID still has data; nothing was dropped.', $error->getMessage());
        }

        $this->assertTrue(Schema::hasColumns('SalesReturn', ['ProductID', 'Quantity', 'Reason']));
        $this->assertSame($product->ProductID, (int) DB::table('Discount')->value('ProductID'));
    }
}
