<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// Besides server errors, laravel.log records JavaScript errors from the
// browser ("Browser error") and pages or saves that take too long to answer
// ("Slow request"), the two kinds of bug that left no trace before.
class RecordedErrorsTest extends TestCase
{
    use RefreshDatabase;

    private function browserError(array $overrides = []): array
    {
        return $overrides + [
            'message' => 'TypeError: Cannot read properties of undefined (reading \'html\')',
            'page' => 'https://cctvexpresstacurong.com/admin/inventory',
            'source' => 'https://cctvexpresstacurong.com/admin/inventory',
            'line' => 1234,
            'column' => 17,
            'stack' => 'TypeError: Cannot read properties of undefined',
        ];
    }

    public function test_a_browser_error_is_written_to_the_log_with_page_and_user(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id, 'name' => 'Superadmin']);
        Log::spy();

        $this->actingAs($admin)->postJson(route('client-errors.store'), $this->browserError())->assertNoContent();

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($admin) {
            return $message === 'Browser error: TypeError: Cannot read properties of undefined (reading \'html\')'
                && $context['page'] === 'https://cctvexpresstacurong.com/admin/inventory'
                && $context['source'] === 'https://cctvexpresstacurong.com/admin/inventory:1234:17'
                && $context['user_id'] === $admin->id
                && $context['username'] === 'Superadmin';
        });
    }

    public function test_the_login_page_can_report_too_and_a_report_needs_a_message(): void
    {
        Log::spy();

        $this->postJson(route('client-errors.store'), $this->browserError())->assertNoContent();
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => $context['user_id'] === null);

        $this->postJson(route('client-errors.store'), $this->browserError(['message' => '']))->assertUnprocessable();
    }

    public function test_every_screen_loads_the_reporter_before_its_other_scripts(): void
    {
        $admin = User::factory()->create(['role_id' => Role::create(['role_name' => 'admin'])->id]);
        $cashier = User::factory()->create(['role_id' => Role::create(['role_name' => 'cashier'])->id]);

        foreach ([
            $this->get(route('welcome'))->getContent(),
            $this->actingAs($admin)->get(route('admin.dashboard'))->getContent(),
            $this->actingAs($cashier)->get(route('cashier.pos'))->getContent(),
        ] as $html) {
            $reporter = strpos($html, 'if (window.__errorReporter) return;');
            $this->assertNotFalse($reporter);
            // No other script runs before it.
            $this->assertSame(1, substr_count(substr($html, 0, $reporter), '<script'));
        }
    }

    public function test_a_slow_page_is_written_to_the_log_with_its_time(): void
    {
        Log::spy();

        config(['logging.slow_request_seconds' => 10]);
        $this->get(route('welcome'))->assertOk();
        Log::shouldNotHaveReceived('warning');

        // Every request counts as slow when the limit is 0 seconds.
        config(['logging.slow_request_seconds' => 0]);
        $this->get(route('welcome'))->assertOk();
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            return preg_match('#^Slow request: GET / took \d+\.\d seconds$#', $message) === 1
                && $context['route'] === 'welcome'
                && $context['status'] === 200;
        });
    }
}
