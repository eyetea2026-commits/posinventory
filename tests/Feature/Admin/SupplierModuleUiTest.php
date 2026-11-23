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

    public function test_add_supplier_link_opens_a_modal_instead_of_navigating(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.index'));

        $response->assertOk();
        $response->assertSee('openAddSupplierModal()', false);
        $response->assertSee('id="addSupplierModal"', false);
    }

    public function test_the_status_column_never_wraps(): void
    {
        Supplier::create(['SupplierName' => 'Rover Systems – SM City Davao', 'ContactNumber' => '(082) 295-7595', 'Address' => 'EA 270, SM City Davao Annex, Quimpo Blvd., Matina, Davao City']);

        $response = $this->actingAs($this->admin)->get(route('admin.suppliers.index'))->assertOk();

        $response->assertSee('<th class="col-status">Status</th>', false);
        $response->assertSee('<td class="col-status">', false);
        $response->assertSee('.suppliers-table .col-actions { width: 1%; white-space: nowrap; }', false);
        $response->assertSee('.suppliers-table .col-status .badge { white-space: nowrap;', false);
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

    public function test_add_supplier_via_ajax_actually_saves_the_supplier(): void
    {
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

    public function test_create_ajax_form_no_longer_offers_city_or_province_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get(route('admin.suppliers.create'));

        $html = $response->json('html');
        $this->assertStringNotContainsString('name="City"', $html);
        $this->assertStringNotContainsString('name="Province"', $html);
    }
}
