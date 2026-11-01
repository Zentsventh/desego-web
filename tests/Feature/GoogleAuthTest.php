<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'mock-google-client-id',
            'services.google.client_secret' => 'mock-google-client-secret',
            'services.google.redirect_uri' => 'http://localhost:8000/auth/google/callback',
        ]);
    }

    public function test_google_redirect_redirects_to_google_with_correct_parameters_and_stores_state(): void
    {
        $this->configureGoogle();
        $response = $this->get('/auth/google/redirect');

        $response->assertStatus(302);
        $targetUrl = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $targetUrl);
        $this->assertStringContainsString('client_id=mock-google-client-id', $targetUrl);
        $this->assertStringContainsString('redirect_uri='.urlencode('http://localhost:8000/auth/google/callback'), $targetUrl);
        $this->assertStringContainsString('response_type=code', $targetUrl);
        $this->assertStringContainsString('scope='.urlencode('openid profile email'), $targetUrl);

        $sessionState = session()->get('google_oauth_state');
        $this->assertNotEmpty($sessionState);
        $this->assertStringContainsString('state='.$sessionState, $targetUrl);
    }

    public function test_google_callback_rejects_state_mismatch(): void
    {
        $this->configureGoogle();
        $response = $this->withSession(['google_oauth_state' => 'correct-state'])
            ->get('/auth/google/callback?code=mock-code&state=wrong-state');

        $response->assertRedirect('/ingresar');
        $response->assertSessionHas('error');
    }

    public function test_google_callback_creates_new_user_and_customer_account(): void
    {
        $this->configureGoogle();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock-access-token',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => '10987654321',
                'name' => 'Lucia Ramirez',
                'email' => 'lucia.ramirez@example.test',
                'picture' => 'https://example.test/lucia.jpg',
                'email_verified' => true,
            ], 200),
        ]);

        $state = 'valid-test-state';

        $response = $this->withSession(['google_oauth_state' => $state])
            ->get('/auth/google/callback?code=valid-code&state='.$state);

        $response->assertRedirect('/cuenta');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'lucia.ramirez@example.test',
            'name' => 'Lucia Ramirez',
            'google_id' => '10987654321',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'lucia.ramirez@example.test',
            'full_name' => 'Lucia Ramirez',
            'status' => 'active',
        ]);

        $customer = DB::table('customers')->where('email', 'lucia.ramirez@example.test')->first();
        $this->assertNotNull($customer);
        $this->assertDatabaseHas('loyalty_accounts', [
            'customer_id' => $customer->id,
        ]);

        $this->assertNotEmpty(session()->get('user_id'));
    }

    public function test_google_callback_logs_in_existing_user_and_updates_google_id(): void
    {
        $this->configureGoogle();
        $roleId = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->insertGetId([
                'code' => 'customer',
                'name' => 'Cliente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        $user = User::factory()->create([
            'email' => 'existente@example.test',
            'name' => 'Usuario Existente',
            'role_id' => $roleId,
            'password_hash' => Hash::make('password123'),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock-access-token',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => '20987654321',
                'name' => 'Usuario Existente',
                'email' => 'existente@example.test',
            ], 200),
        ]);

        $state = 'valid-test-state-2';

        $response = $this->withSession(['google_oauth_state' => $state])
            ->get('/auth/google/callback?code=valid-code&state='.$state);

        $response->assertRedirect('/cuenta');
        $this->assertEquals($user->id, session()->get('user_id'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => '20987654321',
        ]);
    }
}
