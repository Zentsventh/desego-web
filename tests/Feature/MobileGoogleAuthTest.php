<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class MobileGoogleAuthTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    private \OpenSSLAsymmetricKey $key;

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        Http::preventStrayRequests();
        Cache::forget('google_identity_certificates');
        config(['services.google.client_id' => 'native-web-client-id']);
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        Http::fake(['https://www.googleapis.com/oauth2/v1/certs' => Http::response(['test-key' => openssl_pkey_get_details($this->key)['key']])]);
        $this->withSession(['_token' => 'google-test-csrf', 'csrf_token' => 'google-test-csrf'])->withHeader('X-CSRF-TOKEN', 'google-test-csrf');
    }

    private function challenge(): string
    {
        return $this->postJson('/api/mobile/google/challenge')->assertOk()->assertJsonPath('clientId', 'native-web-client-id')->json('nonce');
    }

    /** @param array<string, mixed> $overrides */
    private function token(string $nonce, array $overrides = []): string
    {
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode(json_encode(['alg' => 'RS256', 'kid' => 'test-key']));
        $claims = $encode(json_encode(array_replace([
            'aud' => 'native-web-client-id', 'iss' => 'https://accounts.google.com',
            'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp, 'nonce' => $nonce,
            'email_verified' => true, 'email' => 'native.google@example.test', 'sub' => '123456789012345', 'name' => 'Cliente Google',
        ], $overrides)));
        openssl_sign($header.'.'.$claims, $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $header.'.'.$claims.'.'.$encode($signature);
    }

    public function test_google_native_login_creates_a_real_customer_and_rejects_replay(): void
    {
        $token = $this->token($this->challenge());
        $response = $this->postJson('/api/mobile/google/login', ['id_token' => $token])->assertOk()
            ->assertJsonPath('user.role_code', 'customer')->assertJsonPath('user.email', 'native.google@example.test');
        $this->assertDatabaseHas('customers', ['email' => 'native.google@example.test']);
        $this->getJson('/api/mobile/account')->assertOk()->assertJsonPath('customer.email', 'native.google@example.test');
        $this->withHeader('X-CSRF-TOKEN', $response->json('csrfToken'))
            ->postJson('/api/mobile/google/login', ['id_token' => $token])->assertUnauthorized();
    }

    public function test_existing_web_customer_is_reused_without_duplicate_accounts(): void
    {
        $role = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->insertGetId(['code' => 'customer', 'name' => 'Cliente', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['role_id' => $role, 'email' => 'native.google@example.test']);
        $this->postJson('/api/mobile/google/login', ['id_token' => $this->token($this->challenge())])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertSame(1, DB::table('users')->where('email', $user->email)->count());
        $this->get('/cuenta')->assertOk();
    }

    public function test_wrong_audience_expired_nonce_and_unverified_email_never_authenticate(): void
    {
        foreach ([['aud' => 'another-client'], ['exp' => now()->subMinute()->timestamp], ['nonce' => 'wrong-nonce'], ['email_verified' => false], ['iss' => 'https://attacker.example']] as $override) {
            $this->postJson('/api/mobile/google/login', ['id_token' => $this->token($this->challenge(), $override)])->assertUnauthorized();
        }
        $this->assertDatabaseMissing('users', ['email' => 'native.google@example.test']);
    }

    public function test_unsigned_or_forged_token_is_rejected(): void
    {
        $token = $this->token($this->challenge());
        $parts = explode('.', $token);
        $parts[2] = rtrim(strtr(base64_encode(str_repeat('x', 256)), '+/', '-_'), '=');
        $this->postJson('/api/mobile/google/login', ['id_token' => implode('.', $parts)])->assertUnauthorized();
        $this->assertDatabaseMissing('users', ['email' => 'native.google@example.test']);
    }

    public function test_staff_google_identity_cannot_enter_customer_app(): void
    {
        $user = User::factory()->create(['email' => 'native.google@example.test', 'role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $this->postJson('/api/mobile/google/login', ['id_token' => $this->token($this->challenge())])->assertForbidden();
        $this->assertNotSame($user->id, session()->get('user_id'));
    }

    public function test_missing_configuration_is_reported_without_exposing_secrets(): void
    {
        config(['services.google.client_id' => null]);
        $this->postJson('/api/mobile/google/challenge')->assertServiceUnavailable();
        Http::assertNothingSent();
    }
}
