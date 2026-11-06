<?php

namespace Tests\Feature\Cashier;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosProductOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $cashierRole = Role::create(['role_name' => 'cashier']);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id]);
    }

    private function makeProduct(string $name, Category $category, int $stock = 10): Product
    {
        $product = Product::create([
            'ProductName' => $name, 'Model' => 'M-'.uniqid(),
            'Price' => 100, 'CategoryID' => $category->CategoryID,
        ]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => $stock, 'Status' => 'Available']);

        return $product;
    }

    public function test_consumables_are_shown_first_then_the_newest_products(): void
    {
        $cameras = Category::create(['CategoryName' => 'IP CAMERA']);
        $consumables = Category::create(['CategoryName' => 'CONSUMABLES']);

        $this->makeProduct('Old Camera', $cameras);
        $this->makeProduct('RJ45 Regular', $consumables);
        $this->makeProduct('Junction Box', $consumables);
        $this->makeProduct('New Camera', $cameras);
        $this->makeProduct('BNC Silver', $consumables, 0);

        $names = $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()
            ->viewData('products')->pluck('ProductName')->all();

        $this->assertSame(['Junction Box', 'RJ45 Regular', 'New Camera', 'Old Camera'], $names);
    }

    public function test_items_with_no_serial_to_scan_come_before_serial_numbered_items(): void
    {
        $cameras = Category::create(['CategoryName' => 'IP CAMERA', 'TracksSerialNumbers' => true]);
        $wires = Category::create(['CategoryName' => 'WIRE', 'TracksSerialNumbers' => false]);
        $consumables = Category::create(['CategoryName' => 'CONSUMABLES', 'TracksSerialNumbers' => false]);

        $this->makeProduct('UTP Cable', $wires);
        $this->makeProduct('Dome Camera', $cameras);
        $this->makeProduct('RJ45 Regular', $consumables);
        $this->makeProduct('Bullet Camera', $cameras);
        $this->makeProduct('Coaxial Wire', $wires);

        $names = $this->actingAs($this->cashier)->get(route('cashier.pos'))->assertOk()
            ->viewData('products')->pluck('ProductName')->all();

        $this->assertSame(['RJ45 Regular', 'Coaxial Wire', 'UTP Cable', 'Bullet Camera', 'Dome Camera'], $names);
    }
}
