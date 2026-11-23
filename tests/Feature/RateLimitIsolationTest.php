<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function pollInBackground(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->get(route('live.signature'));
            $this->get(route('session.alive'));
        }
    }

    public function test_background_polling_does_not_use_up_the_send_otp_limit(): void
    {
        $this->pollInBackground(10);

        $this->from(route('admin.forgot'))->post(route('admin.forgot.post'), ['email' => 'nobody@cctvexpress.local'])
            ->assertRedirect(route('admin.forgot'))
            ->assertSessionHasErrors('email');
    }

    public function test_background_polling_does_not_block_logging_in(): void
    {
        $this->pollInBackground(20);

        $this->from(route('welcome'))->post(route('login.post'), ['username' => 'nobody', 'password' => 'wrong-password'])
            ->assertRedirect(route('welcome'))
            ->assertSessionHasErrors('username');
    }

    public function test_barcode_scans_do_not_use_up_the_custom_discount_approvals(): void
    {
        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($cashier)->getJson('/api/products/barcode/NOT-A-PRODUCT')->assertNotFound();
        }

        $this->actingAs($cashier)->postJson(route('cashier.custom-discount.approve'), ['percent' => 10, 'admin_password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_each_route_still_has_its_own_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('admin.forgot.post'), ['email' => 'nobody@cctvexpress.local']);
        }

        $this->post(route('admin.forgot.post'), ['email' => 'nobody@cctvexpress.local'])->assertStatus(429);
    }
}
