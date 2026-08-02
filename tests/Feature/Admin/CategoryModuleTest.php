<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    // Regression guard: the Add/Edit Category modal submits via fetch() with
    // X-Requested-With + Accept: application/json, then follows the
    // resulting redirect back to index() and parses that final response's
    // HTML for the "Auto-show session messages" Swal marker (see
    // admin/partials/ajax-modal-form). followingRedirects() reproduces that
    // exact round trip — this fails if index()'s live-search JSON branch
    // ever goes back to keying off those same XHR headers instead of the
    // explicit ?ajax=1 flag the search JS sends.
    public function test_store_via_the_modals_xhr_flow_still_returns_html_after_the_redirect(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->followingRedirects()
            ->post(route('admin.categories.store'), ['CategoryName' => 'CCTV Cameras', 'Description' => 'All camera types']);

        $response->assertOk();
        $response->assertSee('Auto-show session messages', false);
        $response->assertSee('Category created successfully.');
        $this->assertDatabaseHas('Category', ['CategoryName' => 'CCTV Cameras']);
    }

    public function test_xhr_headers_alone_without_ajax_flag_still_return_the_full_page(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.categories.index'));

        $response->assertOk();
        $response->assertSee('Category');
        $response->assertDontSee('"rows":', false);
    }

    public function test_live_search_ajax_returns_filtered_rows_as_json(): void
    {
        Category::create(['CategoryName' => 'CCTV Cameras', 'Description' => 'Cameras']);
        Category::create(['CategoryName' => 'Networking', 'Description' => 'Switches']);

        $response = $this->actingAs($this->admin)->getJson(route('admin.categories.index', ['search' => 'CCTV', 'ajax' => 1]));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'pagination']);
        $this->assertStringContainsString('CCTV Cameras', $response->json('rows'));
        $this->assertStringNotContainsString('Networking', $response->json('rows'));
    }

    public function test_check_name_flags_a_case_insensitive_duplicate(): void
    {
        Category::create(['CategoryName' => 'CCTV Cameras', 'Description' => 'Cameras']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.categories.check-name'), [
            'CategoryName' => 'cctv cameras',
        ]);

        $response->assertOk();
        $response->assertJson(['name' => true]);
    }

    public function test_check_name_excludes_the_categorys_own_current_name_when_editing(): void
    {
        $category = Category::create(['CategoryName' => 'CCTV Cameras', 'Description' => 'Cameras']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.categories.check-name'), [
            'CategoryName' => 'CCTV Cameras', 'exclude_id' => $category->CategoryID,
        ]);

        $response->assertOk();
        $response->assertJson(['name' => false]);
    }

    public function test_check_name_reports_available_for_a_brand_new_name(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.categories.check-name'), [
            'CategoryName' => 'Brand New Category',
        ]);

        $response->assertOk();
        $response->assertJson(['name' => false]);
    }
}
