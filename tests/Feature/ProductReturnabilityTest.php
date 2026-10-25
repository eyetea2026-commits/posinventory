<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesItem;
use App\Models\SalesTransaction;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Products can be tagged "Not returnable / refundable" (consumables, wires,
// fiber connectors: RJ45, coaxial cable...). The tag is set on Add/Edit
// Product, shows in the product list and details, and the Cashier can't put
// a tagged product on a return request.
class ProductReturnabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Category $category;

    private Product $camera;

    private Product $rj45;

    private SalesTransaction $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        $staff = Staff::create([
            'FirstName' => 'Jane', 'MiddleName' => '-', 'LastName' => 'Doe', 'ContactNumber' => '0000',
            'Email' => 'jane@example.com', 'Age' => 30, 'Gender' => 'F', 'UserID' => $this->cashier->id,
        ]);

        $this->category = Category::create(['CategoryName' => 'CONSUMABLES']);
        $this->camera = Product::create(['ProductName' => 'Dome Camera', 'Model' => 'DC-1', 'Price' => 1500, 'CategoryID' => $this->category->CategoryID, 'Barcode' => 'CCTVEX-000001']);
        $this->rj45 = Product::create(['ProductName' => 'RJ45 Regular', 'Model' => 'RJ45', 'Price' => 10, 'CategoryID' => $this->category->CategoryID, 'Barcode' => 'CCTVEX-000002', 'IsReturnable' => false]);
        foreach ([$this->camera, $this->rj45] as $product) {
            Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 10, 'Status' => 'Available']);
        }

        $this->sale = SalesTransaction::create(['CustomerName' => 'N/A', 'SalesTransactionDate' => now(), 'StaffID' => $staff->StaffID]);
        foreach ([[$this->camera, 1500], [$this->rj45, 10]] as [$product, $price]) {
            SalesItem::create(['Quantity' => 2, 'UnitPrice' => $price, 'ProductID' => $product->ProductID, 'SalesTransactionID' => $this->sale->SalesTransactionID]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productFields(array $overrides = []): array
    {
        return array_merge([
            'ProductName' => 'Coaxial RG59', 'Model' => 'RG59', 'Barcode' => 'CCTVEX-000099',
            'CostPrice' => 10, 'Price' => 15, 'CategoryID' => $this->category->CategoryID,
        ], $overrides);
    }

    public function test_the_tag_is_set_on_add_and_edit_product(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productFields(['NonReturnable' => '1']))->assertSessionHasNoErrors();
        $coax = Product::where('ProductName', 'Coaxial RG59')->firstOrFail();
        $this->assertFalse($coax->IsReturnable);

        // Unticked (the form always sends the hidden "0").
        $this->actingAs($this->admin)->put(route('admin.products.update', $coax), $this->productFields(['NonReturnable' => '0']))->assertSessionHasNoErrors();
        $this->assertTrue($coax->fresh()->IsReturnable);

        // A product added without the box is returnable.
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productFields(['ProductName' => 'Bullet Camera', 'Model' => 'BC-1', 'Barcode' => 'CCTVEX-000100']))->assertSessionHasNoErrors();
        $this->assertTrue(Product::where('ProductName', 'Bullet Camera')->firstOrFail()->IsReturnable);
    }

    public function test_the_form_list_and_details_show_the_tag(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<input type="checkbox" name="NonReturnable" value="1"', $html);
        $this->assertStringContainsString('Not returnable / refundable', $html);
        $this->assertSame(1, substr_count($html, 'non-returnable-badge" title='));

        $details = $this->actingAs($this->admin)->getJson(route('admin.products.show', $this->rj45), ['X-Requested-With' => 'XMLHttpRequest'])->json('html');
        $this->assertStringContainsString('Not returnable / refundable', $details);

        $edit = $this->actingAs($this->admin)->getJson(route('admin.products.edit', $this->rj45), ['X-Requested-With' => 'XMLHttpRequest'])->json('html');
        $this->assertMatchesRegularExpression('/name="NonReturnable" value="1"\s+checked/', $edit);
    }

    public function test_the_cashier_cannot_return_a_non_returnable_product(): void
    {
        $this->actingAs($this->cashier)->getJson(route('cashier.refunds.transaction', $this->sale->SalesTransactionID))->assertOk()
            ->assertJsonPath('transaction.items.0.IsReturnable', true)
            ->assertJsonPath('transaction.items.1.IsReturnable', false);
        $this->assertStringContainsString('const notReturnable = item.IsReturnable === false;', $this->actingAs($this->cashier)->get(route('cashier.refunds'))->getContent());

        $this->actingAs($this->cashier)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->sale->SalesTransactionID,
            'items' => [['product_id' => $this->rj45->ProductID, 'quantity' => 1, 'reason_code' => 'factory_defect']],
            'return_type' => 'refund',
        ])->assertStatus(400)->assertJsonPath('message', 'RJ45 Regular is not returnable / refundable.');
        $this->assertDatabaseMissing('SalesReturn', ['SalesTransactionID' => $this->sale->SalesTransactionID]);

        // A returnable product on the same sale still goes through.
        $this->actingAs($this->cashier)->postJson(route('cashier.refunds.create'), [
            'transaction_id' => $this->sale->SalesTransactionID,
            'items' => [['product_id' => $this->camera->ProductID, 'quantity' => 1, 'reason_code' => 'factory_defect']],
            'return_type' => 'refund',
        ])->assertOk()->assertJsonPath('success', true);
    }
}
