<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
    }

    private function prepareCsrf(): void
    {
        $this->withSession(['_token' => 'security-test-token'])->withHeader('X-CSRF-TOKEN', 'security-test-token');
    }

    public function test_wrong_password_returns_form_errors_instead_of_an_http_error(): void
    {
        $user = $this->admin();
        $this->from('/ingresar?staff=1')->post('/ingresar', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/ingresar?staff=1')->assertSessionHasErrors('email')->assertSessionMissing('user_id');
    }

    public function test_successful_staff_login_establishes_a_session_and_logout_removes_it(): void
    {
        $user = $this->admin();
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'password', 'remember' => true])
            ->assertRedirect('/admin')->assertSessionHas('user_id', $user->id)->assertSessionHas('auth_password_fingerprint')->assertCookie('DESEGO_REMEMBER');
        $this->get('/admin')->assertOk();
        $this->post('/salir')->assertRedirect('/')->assertSessionMissing('user_id')->assertSessionMissing('auth_password_fingerprint');
        $this->get('/admin')->assertRedirect('/ingresar?staff=1');
    }

    public function test_public_registration_cannot_select_a_staff_role_through_either_legacy_alias(): void
    {
        $this->prepareCsrf();
        foreach (['/api/auth', '/api/auth.php'] as $index => $path) {
            $this->postJson($path.'?action=register', [
                'name' => 'Public Customer', 'email' => "customer{$index}@example.test", 'password' => 'CustomerSecret123',
                'password_confirmation' => 'CustomerSecret123', 'role_id' => 1, 'role_code' => 'admin',
            ])->assertCreated()->assertJsonPath('user.role_code', 'customer')->assertJsonMissingPath('user.password_hash');
            $this->get('/admin')->assertForbidden();
            $this->withHeader('X-CSRF-TOKEN', session()->token());
        }
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_remember_cookie_restores_a_session_and_cannot_be_replayed_after_logout(): void
    {
        $user = $this->admin();
        $response = $this->post('/ingresar', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertRedirect('/admin');
        $cookie = $response->getCookie('DESEGO_REMEMBER')->getValue();
        $this->flushSession();
        $restored = $this->withCookie('DESEGO_REMEMBER', $cookie)->get('/admin')->assertOk()
            ->assertSessionHas('user_id', $user->id)->assertSessionHas('auth_password_fingerprint');
        $rotatedCookie = $restored->getCookie('DESEGO_REMEMBER')->getValue();
        $this->withCookie('DESEGO_REMEMBER', $rotatedCookie)->post('/salir')->assertRedirect('/');
        $this->withCookie('DESEGO_REMEMBER', $rotatedCookie)->get('/admin')->assertRedirect('/ingresar?staff=1');
    }

    public function test_login_limit_is_shared_across_web_mobile_and_legacy_aliases(): void
    {
        $this->prepareCsrf();
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $path = $attempt % 2 === 0 ? '/api/auth.php?action=login' : '/api/mobile/login';
            $this->postJson($path, ['email' => 'unknown@example.test', 'password' => 'wrong-password'])->assertUnauthorized();
        }
        $this->postJson('/api/auth?action=login', ['email' => 'unknown@example.test', 'password' => 'wrong-password'])->assertStatus(429);
        $this->from('/ingresar')->post('/ingresar', ['email' => 'unknown@example.test', 'password' => 'wrong-password'])
            ->assertRedirect('/ingresar')->assertSessionHasErrors('email');
        $this->postJson('/api/auth.php?action=logout')->assertOk();
    }

    public function test_recovery_limit_is_shared_across_aliases_and_preserves_the_web_form(): void
    {
        $this->prepareCsrf();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/password-reset.php', ['email' => 'unknown@example.test'])->assertOk();
        }
        $this->postJson('/api/password-reset', ['email' => 'unknown@example.test'])->assertStatus(429);
        $this->from('/recuperar-contrasena')->post('/recuperar-contrasena', ['email' => 'unknown@example.test'])
            ->assertRedirect('/recuperar-contrasena')->assertSessionHasErrors('email');
        $this->get('/recuperar-contrasena')->assertOk();
    }

    public function test_registration_limit_is_shared_with_legacy_registration(): void
    {
        $this->prepareCsrf();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/mobile/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/auth.php?action=register', [])->assertStatus(429);
        $this->postJson('/registro', [])->assertStatus(429);
    }

    public function test_expired_account_lock_starts_a_fresh_attempt_window(): void
    {
        $user = User::factory()->create(['failed_login_count' => 5, 'locked_until' => now()->subMinute()]);
        $this->prepareCsrf();
        $this->postJson('/api/auth.php?action=login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnauthorized();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'failed_login_count' => 1, 'locked_until' => null]);
        $this->postJson('/api/auth.php?action=login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'failed_login_count' => 0, 'locked_until' => null]);
    }

    public function test_five_wrong_passwords_lock_the_account_even_for_a_correct_password(): void
    {
        $user = $this->admin();
        $this->prepareCsrf();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth.php?action=login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnauthorized();
        }
        $this->postJson('/api/auth.php?action=login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized()->assertSessionMissing('user_id');
        $this->assertNotNull($user->fresh()->locked_until);
    }

    public function test_password_reset_email_opens_the_new_form_and_revokes_existing_sessions(): void
    {
        $user = $this->admin();
        $otherUser = $this->admin();
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertRedirect('/admin');
        $oldSession = session()->all();
        $this->post('/ingresar', ['email' => $otherUser->email, 'password' => 'password'])->assertRedirect('/admin');
        $otherSession = session()->all();
        config(['desego.mail.from_email' => 'noreply@example.test', 'desego.mail.app_url' => 'http://localhost', 'desego.mail.allow_local_debug_link' => false]);
        $resetUrl = null;
        Mail::shouldReceive('raw')->once()->withArgs(function (string $message, callable $callback) use (&$resetUrl): bool {
            preg_match('~http://localhost/restablecer-contrasena\?token=[a-f0-9]{64}~', $message, $matches);
            $resetUrl = $matches[0] ?? null;

            return $resetUrl !== null;
        });
        $this->from('/recuperar-contrasena')->post('/recuperar-contrasena', ['email' => $user->email])
            ->assertRedirect('/recuperar-contrasena')->assertSessionHas('success');
        $this->assertNotNull($resetUrl);
        parse_str(parse_url($resetUrl, PHP_URL_QUERY), $query);
        $this->get($resetUrl)->assertInertia(fn (Assert $page) => $page->component('Restaurant/Auth')->where('reset', true)->where('token', $query['token']));
        $this->post('/restablecer-contrasena', ['token' => $query['token'], 'password' => 'ReplacementSecret123', 'password_confirmation' => 'ReplacementSecret123'])
            ->assertRedirect('/ingresar')->assertSessionMissing('user_id');
        $this->assertTrue(Hash::check('ReplacementSecret123', $user->fresh()->password_hash));
        $this->assertDatabaseMissing('remember_tokens', ['user_id' => $user->id, 'revoked_at' => null]);
        $this->withSession($oldSession)->get('/admin')->assertRedirect('/ingresar?staff=1')->assertSessionMissing('user_id');
        $this->withSession($otherSession)->get('/admin')->assertOk();
        $this->from('/restablecer-contrasena')->post('/restablecer-contrasena', ['token' => $query['token'], 'password' => 'AnotherSecret123', 'password_confirmation' => 'AnotherSecret123'])
            ->assertRedirect('/restablecer-contrasena')->assertSessionHasErrors('request');
    }

    public function test_recovery_does_not_disclose_whether_an_account_exists(): void
    {
        $this->prepareCsrf();
        $user = $this->admin();
        config(['desego.mail.from_email' => 'noreply@example.test']);
        Mail::shouldReceive('raw')->once();
        $known = $this->postJson('/api/password-reset.php', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/password-reset.php', ['email' => 'unknown@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
    }

    public function test_recovery_pages_and_old_email_links_remain_accessible(): void
    {
        $this->get('/recuperar-contrasena')->assertInertia(fn (Assert $page) => $page->component('Restaurant/Auth')->where('forgot', true));
        $token = str_repeat('a', 64);
        $this->get('/index.php?reset_token='.$token)->assertRedirect('/restablecer-contrasena?token='.$token);
        $this->from('/restablecer-contrasena')->post('/restablecer-contrasena', ['token' => $token, 'password' => 'short', 'password_confirmation' => 'different'])
            ->assertRedirect('/restablecer-contrasena')->assertSessionHasErrors('password');
    }

    public function test_password_changes_also_revoke_legacy_customer_profile_access(): void
    {
        $this->post('/registro', [
            'name' => 'Customer Example', 'email' => 'customer@example.test', 'password' => 'CustomerSecret123', 'password_confirmation' => 'CustomerSecret123',
        ])->assertRedirect('/cuenta');
        $this->getJson('/api/storefront.php?view=profile')->assertOk()->assertJsonPath('profile.customer.email', 'customer@example.test');
        DB::table('users')->where('email', 'customer@example.test')->update(['password_hash' => Hash::make('ChangedPassword123')]);
        $this->getJson('/api/storefront.php?view=profile')->assertOk()->assertJsonPath('profile', null)->assertSessionMissing('user_id')->assertSessionMissing('storefront_customer_id');
    }
}
