<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->category = Category::create(['CategoryName' => 'UPS', 'Description' => 'Power']);
    }

    private function makeProduct(array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'ProductName' => 'UPS 1000VA', 'Model' => 'SECURE UPS1000VA', 'Barcode' => 'CCTVEX-000041',
            'CostPrice' => 2000, 'Price' => 3300, 'CategoryID' => $this->category->CategoryID,
        ], $overrides));
        $product->update(['SKU' => Product::generateSku($product->ProductID)]);
        Inventory::create(['ProductID' => $product->ProductID, 'Quantity' => 5, 'ReorderThreshold' => 2, 'Status' => 'Available']);

        return $product;
    }

    private function updateFields(Product $product, array $extra = []): array
    {
        return array_merge([
            '_method' => 'PUT',
            'ProductName' => $product->ProductName, 'Model' => $product->Model, 'Barcode' => $product->Barcode,
            'CostPrice' => $product->CostPrice, 'Price' => $product->Price, 'CategoryID' => $product->CategoryID,
        ], $extra);
    }

    public function test_an_uploaded_photo_is_saved_shrunk_to_a_small_file(): void
    {
        $photo = UploadedFile::fake()->image('ups.jpg', 3000, 2000)->size(4000);

        $this->actingAs($this->admin)
            ->post(route('admin.products.store'), [
                'ProductName' => 'UPS 1000VA', 'Model' => 'SECURE UPS1000VA', 'Barcode' => 'CCTVEX-000041',
                'CostPrice' => 2000, 'Price' => 3300, 'CategoryID' => $this->category->CategoryID, 'Image' => $photo,
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $path = Product::where('Barcode', 'CCTVEX-000041')->value('ImagePath');
        $this->assertNotNull($path);
        $this->assertStringStartsWith('products/', $path);
        Storage::disk('public')->assertExists($path);

        $stored = Storage::disk('public')->get($path);
        [$width, $height] = getimagesizefromstring($stored);
        $this->assertSame(ProductImage::MAX_SIDE, $width);
        $this->assertSame(320, $height);
        $this->assertLessThan(60 * 1024, strlen($stored), 'Stored photo should be a small file');
    }

    public function test_a_new_photo_replaces_the_old_one_and_remove_clears_it(): void
    {
        $product = $this->makeProduct();
        $this->actingAs($this->admin)->post(route('admin.products.update', $product), $this->updateFields($product, ['Image' => UploadedFile::fake()->image('a.png', 800, 800)]));
        $first = $product->fresh()->ImagePath;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($this->admin)->post(route('admin.products.update', $product), $this->updateFields($product, ['Image' => UploadedFile::fake()->image('b.jpg', 600, 400)]));
        $second = $product->fresh()->ImagePath;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Saving without a new photo keeps it.
        $this->actingAs($this->admin)->post(route('admin.products.update', $product), $this->updateFields($product));
        $this->assertSame($second, $product->fresh()->ImagePath);

        $this->actingAs($this->admin)->post(route('admin.products.update', $product), $this->updateFields($product, ['RemoveImage' => '1']));
        $this->assertNull($product->fresh()->ImagePath);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_a_file_that_is_not_an_image_is_rejected(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)->postJson(route('admin.products.update', $product), $this->updateFields($product, [
            'Image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors('Image');

        $this->assertNull($product->fresh()->ImagePath);
    }

    public function test_photos_show_in_products_inventory_and_the_pos(): void
    {
        $withPhoto = $this->makeProduct();
        $photo = UploadedFile::fake()->image('x.jpg', 100, 100);
        $withPhoto->update(['ImagePath' => ProductImage::storeFromPath($photo->getRealPath())]);
        $withoutPhoto = $this->makeProduct(['ProductName' => 'HDD 1TB', 'Model' => 'WD10', 'Barcode' => 'CCTVEX-000025']);
        $img = '<img src="'.$withPhoto->fresh()->image_url.'" alt="UPS 1000VA" loading="lazy">';

        $this->actingAs($this->admin)->get(route('admin.products.index'))->assertOk()
            ->assertSee($img, false)
            ->assertSee('<i class="fas fa-box" aria-hidden="true"></i>', false);
        $this->actingAs($this->admin)->get(route('admin.inventory.index'))->assertOk()->assertSee($img, false);

        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
        Staff::create(['FirstName' => 'Cash', 'MiddleName' => '-', 'LastName' => 'Ier', 'ContactNumber' => '0000', 'Email' => 'cash@example.com', 'Age' => 30, 'Gender' => 'F', 'UserID' => $cashier->id]);
        $this->actingAs($cashier)->get(route('cashier.pos'))->assertOk()
            ->assertSee('<span class="product-thumb thumb-card">', false)
            ->assertSee($img, false);
        $this->assertNull($withoutPhoto->fresh()->image_url);
    }

    public function test_photos_can_be_imported_from_a_folder_by_sku_barcode_or_name(): void
    {
        $bySku = $this->makeProduct();
        $byBarcode = $this->makeProduct(['ProductName' => 'HDD 1TB', 'Model' => 'WD10', 'Barcode' => 'CCTVEX-000025']);
        $byName = $this->makeProduct(['ProductName' => 'RJ45', 'Model' => 'RJ', 'Barcode' => '1234567890']);

        $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'photos-'.uniqid();
        mkdir($folder);
        foreach ([$bySku->fresh()->SKU.'.jpg', 'CCTVEX-000025.png', 'rj45.jpeg', 'unknown.jpg'] as $name) {
            $photo = UploadedFile::fake()->image($name, 300, 300);
            copy($photo->getRealPath(), $folder.DIRECTORY_SEPARATOR.$name);
        }

        $this->artisan('products:import-photos', ['folder' => $folder])
            ->expectsOutputToContain('3 photo(s) attached.')
            ->expectsOutputToContain('No product matched: unknown.jpg')
            ->assertSuccessful();

        foreach ([$bySku, $byBarcode, $byName] as $product) {
            Storage::disk('public')->assertExists($product->fresh()->ImagePath);
        }

        // Existing photos are kept unless --replace is given.
        $kept = $bySku->fresh()->ImagePath;
        $this->artisan('products:import-photos', ['folder' => $folder])->expectsOutputToContain('0 photo(s) attached.')->assertSuccessful();
        $this->assertSame($kept, $bySku->fresh()->ImagePath);
        $this->artisan('products:import-photos', ['folder' => $folder, '--replace' => true])->expectsOutputToContain('3 photo(s) attached.')->assertSuccessful();
        $this->assertNotSame($kept, $bySku->fresh()->ImagePath);
        Storage::disk('public')->assertMissing($kept);

        array_map('unlink', glob($folder.DIRECTORY_SEPARATOR.'*'));
        rmdir($folder);
    }
}
