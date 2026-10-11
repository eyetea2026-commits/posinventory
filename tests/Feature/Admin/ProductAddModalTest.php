<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The Add Product modal submits over AJAX and must get a direct answer from
// store(): 201 "created" or 422 field errors — never the index's rows, which
// made it report a false "already exists" for products on a later page.
class ProductAddModalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->category = Category::create(['CategoryName' => 'CCTV', 'Description' => 'Cameras']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productFields(array $overrides = []): array
    {
        return array_merge([
            'ProductName' => 'DRS', 'Model' => '64253789064', 'Barcode' => '590385493485934589',
            'CostPrice' => 46574, 'Price' => 84680, 'CategoryID' => $this->category->CategoryID,
        ], $overrides);
    }

    private function ajaxStore(array $fields)
    {
        return $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.products.store'), $fields);
    }

    public function test_ajax_create_returns_success_even_when_the_product_sorts_onto_a_later_page(): void
    {
        // 15 products per page, ordered by name: "DRS" lands after these.
        foreach (range(1, 16) as $i) {
            Product::create([
                'ProductName' => sprintf('Camera %02d', $i), 'Model' => "CAM-{$i}", 'Barcode' => "BC-{$i}",
                'CostPrice' => 100, 'Price' => 150, 'CategoryID' => $this->category->CategoryID,
            ]);
        }

        $response = $this->ajaxStore($this->productFields());

        $response->assertCreated()
            ->assertJson(['success' => true, 'message' => 'Product added successfully.']);
        $this->assertDatabaseHas('Product', ['ProductName' => 'DRS', 'Model' => '64253789064']);
        $response->assertJsonPath('productId', Product::where('ProductName', 'DRS')->value('ProductID'));
    }

    public function test_ajax_duplicate_name_returns_a_field_error_and_creates_nothing(): void
    {
        $this->ajaxStore($this->productFields())->assertCreated();

        $response = $this->ajaxStore($this->productFields(['Model' => 'OTHER-MODEL', 'Barcode' => 'OTHER-BARCODE']));

        $response->assertStatus(422)->assertJsonValidationErrors(['ProductName'])->assertJsonMissingValidationErrors(['Model']);
        $this->assertSame(1, Product::where('ProductName', 'DRS')->count());
    }

    public function test_ajax_duplicate_model_returns_a_field_error(): void
    {
        $this->ajaxStore($this->productFields())->assertCreated();

        $this->ajaxStore($this->productFields(['ProductName' => 'Another Name', 'Barcode' => 'OTHER-BARCODE']))
            ->assertStatus(422)->assertJsonValidationErrors(['Model']);
    }

    public function test_normal_form_post_still_redirects(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productFields())
            ->assertRedirect(route('admin.products.index'));

        $this->actingAs($this->admin)->post(route('admin.products.store'), $this->productFields(['Barcode' => 'OTHER']))
            ->assertRedirect()->assertSessionHasErrors(['ProductName', 'Model']);
    }

    public function test_modal_script_trusts_the_server_answer_instead_of_scanning_rows(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('rowsHtml.includes(escapedName)', $html);
        $this->assertStringContainsString('if (!data.success)', $html);
    }
}
