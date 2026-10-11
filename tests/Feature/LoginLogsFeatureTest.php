<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginLogsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_web_login_creates_login_row_with_right_user_id_ip_and_user_agent(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $agent = 'Mozilla/5.0 Test Browser';

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10', 'HTTP_USER_AGENT' => $agent])
            ->post('/login', ['identifier' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect();

        $this->assertDatabaseCount('login_logs', 1);
        $log = LoginLog::first();
        $this->assertSame('login', $log->event);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('192.168.1.10', $log->ip_address);
        $this->assertSame($agent, $log->user_agent);
    }

    public function test_failed_login_creates_failed_row(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        $this->from(route('login'))
            ->post('/login', ['identifier' => $user->email, 'password' => 'wrong']);

        $this->assertDatabaseCount('login_logs', 1);
        $log = LoginLog::first();
        $this->assertSame('failed', $log->event);
    }

    public function test_logout_creates_logout_row(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123!']);
        $this->assertAuthenticated();
        $this->post('/logout');
        $this->assertDatabaseCount('login_logs', 2); // login + logout
        $this->assertDatabaseHas('login_logs', ['event' => 'logout']);
    }

    public function test_exceeding_rate_limit_creates_lockout_row(): void
    {
        $this->app['config']->set('cache.default', 'array');
        $user = User::factory()->create(['password' => 'Password123!']);

        foreach (range(1, 5) as $i) {
            $this->from(route('login'))
                ->post('/login', ['identifier' => $user->email, 'password' => 'wrong']);
        }
        $this->from(route('login'))
            ->post('/login', ['identifier' => $user->email, 'password' => 'wrong']);

        $this->assertDatabaseHas('login_logs', ['event' => 'lockout']);
    }

    public function test_identifier_that_is_not_email_or_mobile_is_stored_as_null(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $this->post('/login', ['identifier' => 'just-a-name', 'password' => 'Password123!']);
        $log = LoginLog::first();
        $this->assertSame('failed', $log->event);
        $this->assertNull($log->identifier);
    }

    public function test_no_password_or_token_value_appears_in_any_column(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123!']);
        $log = LoginLog::first();
        $columns = ['identifier', 'ip_address', 'user_agent'];
        foreach ($columns as $col) {
            $val = $log->{$col} ?? '';
            $this->assertStringNotContainsString('Password123!', (string) $val);
            $this->assertStringNotContainsString('token', strtolower((string) $val));
        }
    }

    public function test_exception_inside_logger_does_not_stop_login(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);

        // Make the log write blow up: the table is gone, so LoginLog::create()
        // throws. Authentication must still succeed regardless.
        Schema::drop('login_logs');

        $this->post('/login', ['identifier' => $user->email, 'password' => 'Password123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_api_login_and_logout_are_logged(): void
    {
        $user = User::factory()->create(['password' => 'Password123!']);
        $login = $this->postJson('/api/login', ['identifier' => $user->email, 'password' => 'Password123!']);
        $login->assertOk();
        $this->assertDatabaseHas('login_logs', ['event' => 'login']);
        $token = $login->json('token');
        $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->assertDatabaseHas('login_logs', ['event' => 'logout']);
    }

    public function test_prune_command_deletes_only_old_rows(): void
    {
        LoginLog::insert([
            ['event' => 'login', 'created_at' => now()->subDays(200), 'updated_at' => now()->subDays(200)],
            ['event' => 'login', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('login-logs:prune', ['--days' => 180])->assertExitCode(0);

        $this->assertSame(1, LoginLog::count());
        $this->assertSame(1, LoginLog::where('created_at', '>=', now()->subDays(180))->count());
    }
}
