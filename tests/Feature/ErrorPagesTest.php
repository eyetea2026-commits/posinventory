<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $this->cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);
    }

    public function test_an_admin_opening_a_cashier_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->admin)->get(route('cashier.pos'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('error', 'That page is for Cashier accounts only.');
    }

    public function test_a_cashier_opening_an_admin_page_goes_to_the_pos(): void
    {
        $this->actingAs($this->cashier)->get(route('admin.purchase-orders.index'))
            ->assertRedirect(route('cashier.pos'))
            ->assertSessionHas('error', 'That page is for Admin accounts only.');
    }

    public function test_data_requests_from_the_wrong_role_still_get_403(): void
    {
        $this->actingAs($this->admin)->getJson(route('cashier.pos'))->assertForbidden();
        $this->actingAs($this->admin)->get('/api/products/barcode/123')->assertForbidden();
    }

    public function test_an_account_without_a_role_gets_403_instead_of_a_redirect_loop(): void
    {
        $noRole = User::factory()->create(['role_id' => null]);

        $this->actingAs($noRole)->get(route('cashier.pos'))->assertForbidden();
    }

    public function test_server_errors_show_the_styled_500_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test-server-error', fn () => throw new RuntimeException('boom'));

        $this->get('/_test-server-error')
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertSee('Try Again')
            ->assertSee('Go to Home')
            ->assertDontSee('boom');
    }
}
