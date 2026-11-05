<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_hashes_password_and_ignores_privileged_fields(): void
    {
        $this->post('/register', [
            'name' => 'Khách hàng', 'email' => 'NEW@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'role' => 'admin', 'status' => 'locked',
        ])->assertRedirect('/customer');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('Password123!', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        $user = User::factory()->create();
        $this->post('/register', ['name' => 'Test', 'email' => $user->email,
            'password' => 'Password123!', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_demo_accounts_login_render_their_dashboard_and_logout(): void
    {
        $this->seed();
        foreach (['customer', 'vendor', 'admin'] as $role) {
            $this->get('/login')->assertOk();
            $oldId = session()->getId();
            $this->post('/login', ['email' => "$role@beautypals.test", 'password' => DemoUserSeeder::PASSWORD])
                ->assertRedirect("/$role");
            $this->assertNotSame($oldId, session()->getId());
            $this->get("/$role")->assertOk();
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
    }

    public function test_locked_user_cannot_login_even_with_correct_password(): void
    {
        $user = User::factory()->create(['status' => 'locked']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('khóa', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_account_locked_during_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/customer')->assertOk();
        $user->update(['status' => 'locked']);
        $this->get('/customer')->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_failures_are_limited_by_email_across_ips_and_expire(): void
    {
        $user = User::factory()->create();
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$i"])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(901)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/customer');
        $this->assertAuthenticatedAs($user);
    }

    public function test_blocking_customer_does_not_block_admin_or_vendor_on_same_ip(): void
    {
        $this->seed();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'customer@beautypals.test', 'password' => 'wrong']);
        }
        foreach (['admin', 'vendor'] as $role) {
            $this->post('/login', ['email' => "$role@beautypals.test", 'password' => DemoUserSeeder::PASSWORD])
                ->assertRedirect("/$role");
            $this->assertAuthenticatedAs(User::where('email', "$role@beautypals.test")->firstOrFail());
            $this->post('/logout');
            $this->post('/login', ['email' => 'customer@beautypals.test', 'password' => DemoUserSeeder::PASSWORD])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_each_wrong_password_is_rejected_and_fifth_failure_immediately_blocks(): void
    {
        $user = User::factory()->create();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'definitely-wrong'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
            $message = session('errors')->first('email');
            $this->assertStringContainsString($attempt < 5 ? 'còn '.(5 - $attempt).' lần' : 'Đã đạt giới hạn 5', $message);
        }
        // Reloading the form or supplying the real password cannot bypass the block.
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_correct_password_after_one_failure_is_allowed_before_limit(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/customer');
        $this->assertAuthenticatedAs($user);
    }

    public function test_countdown_uses_server_time_survives_reload_and_expires(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->get('/login')->assertOk()->assertViewHas('lockoutSeconds', 900)
            ->assertSee('data-login-countdown="900"', false)->assertSee('15:00');
        $this->travel(61)->seconds();
        $this->get('/login')->assertOk()->assertViewHas('lockoutSeconds', 839)->assertSee('13:59');
        $this->get('/login')->assertViewHas('lockoutSeconds', 839);
        $this->travel(840)->seconds();
        $this->get('/login')->assertViewHas('lockoutSeconds', 0)->assertDontSee('data-login-countdown=');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/customer');
    }

    public function test_successful_login_resets_email_and_ip_for_next_login_cycle(): void
    {
        $user = User::factory()->create();
        for ($cycle = 0; $cycle < 3; $cycle++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertStringContainsString('còn 4 lần', session('errors')->first('email'));
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/customer');
            $this->assertAuthenticatedAs($user);
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
        }
        // A new cycle still blocks after five consecutive failures.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertStringContainsString($attempt < 5 ? 'còn '.(5 - $attempt).' lần' : 'Đã đạt giới hạn 5', session('errors')->first('email'));
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
