<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierModuleUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    // "Add Supplier" now opens a popup instead of navigating to the
    // standalone create page — the link's onclick calls
    // window.openAddSupplierModal(), which fetches the same shared
    // supplier-form-fields partial edit() already uses.
    public function test_add_supplier_link_opens_a_modal_instead_of_navigating(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.index'));

        $response->assertOk();
        $response->assertSee('openAddSupplierModal()', false);
        $response->assertSee('id="addSupplierModal"', false);
    }

    public function test_create_ajax_request_returns_the_shared_form_fields_partial(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.suppliers.create'));

        $response->assertOk();
        $response->assertJsonStructure(['html']);
        $html = $response->json('html');
        $this->assertStringContainsString('name="SupplierName"', $html);
        $this->assertStringContainsString('name="ContactNumber"', $html);
        $this->assertStringContainsString('name="Email"', $html);
        $this->assertStringContainsString('name="Address"', $html);
    }

    public function test_create_direct_navigation_still_returns_the_full_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.create'));

        $response->assertOk();
        $response->assertSee('Add Supplier');
        $response->assertSee('name="SupplierName"', false);
    }

    // The Add Supplier modal must still create a real Supplier row through
    // the existing store() validation/save — only the presentation changed.
    public function test_add_supplier_via_ajax_actually_saves_the_supplier(): void
    {
        // store() itself redirects with a flash message (unchanged) —
        // window.submitAjaxForm() follows that redirect and scrapes the
        // resulting page's Swal success/error markup, same as every other
        // "Add X" modal in this app; the fetch()-vs-redirect mechanics
        // aren't Supplier-specific, so the real thing to verify here is
        // that this endpoint still actually saves the record.
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('admin.suppliers.store'), [
                'SupplierName' => 'Acme Cameras',
                'ContactPerson' => 'Juan Dela Cruz',
                'ContactNumber' => '09171234567',
                'Email' => 'acme@example.com',
                'Address' => '123 Main St',
            ]);

        $response->assertRedirect(route('admin.suppliers.index'));
        $this->assertDatabaseHas('Supplier', ['SupplierName' => 'Acme Cameras', 'Email' => 'acme@example.com']);
    }
}
