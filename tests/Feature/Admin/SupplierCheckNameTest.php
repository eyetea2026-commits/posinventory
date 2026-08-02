<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCheckNameTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['role_name' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    public function test_check_name_flags_a_duplicate_name_case_insensitively(): void
    {
        Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.suppliers.check-name'), [
            'SupplierName' => 'acme supplies',
        ]);

        $response->assertOk();
        $response->assertJson(['name' => true]);
    }

    public function test_check_name_flags_a_duplicate_email(): void
    {
        Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.suppliers.check-name'), [
            'Email' => 'ACME@EXAMPLE.COM',
        ]);

        $response->assertOk();
        $response->assertJson(['email' => true]);
    }

    public function test_check_name_excludes_the_suppliers_own_record_when_editing(): void
    {
        $supplier = Supplier::create(['SupplierName' => 'Acme Supplies', 'ContactNumber' => '0000', 'Email' => 'acme@example.com', 'Address' => 'N/A']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.suppliers.check-name'), [
            'SupplierName' => 'Acme Supplies', 'Email' => 'acme@example.com', 'exclude_id' => $supplier->SupplierID,
        ]);

        $response->assertOk();
        $response->assertJson(['name' => false, 'email' => false]);
    }

    public function test_check_name_reports_available_for_new_values(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('admin.suppliers.check-name'), [
            'SupplierName' => 'Brand New Co', 'Email' => 'new@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['name' => false, 'email' => false]);
    }
}
