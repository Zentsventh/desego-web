<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class MobileSynchronizationTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function customer(): User
    {
        $roleId = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->insertGetId(['code' => 'customer', 'name' => 'Cliente', 'created_at' => now(), 'updated_at' => now()]);

        return User::factory()->create([
            'role_id' => $roleId,
            'password_hash' => Hash::make('CustomerPass123!'),
        ]);
    }

    private function login(User $user): void
    {
        $this->withSession(['_token' => 'mobile-test-token', 'csrf_token' => 'mobile-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'mobile-test-token');
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'CustomerPass123!'])
            ->assertOk()->assertJsonPath('user.email', $user->email)->assertJsonPath('user.role_code', 'customer');
    }

    public function test_public_sync_contains_catalog_but_no_customer_account(): void
    {
        $this->getJson('/api/mobile/sync')->assertOk()
            ->assertJsonPath('authenticated', false)->assertJsonPath('user', null)
            ->assertJsonPath('account', null)->assertJsonStructure(['catalog' => ['products', 'categories'], 'server_time']);
        $this->putJson('/api/mobile/favorites/1', ['favorited' => true])->assertUnauthorized();
    }

    public function test_web_customer_credentials_access_native_account_and_profile_changes_reach_web(): void
    {
        $user = $this->customer();
        $this->login($user);
        $this->getJson('/api/mobile/sync')->assertOk()->assertJsonPath('authenticated', true)
            ->assertJsonPath('account.customer.email', $user->email);
        $this->postJson('/api/mobile/profile', ['name' => 'Cliente sincronizado', 'phone' => '999888777'])->assertOk();
        $this->getJson('/api/mobile/account')->assertOk()->assertJsonPath('customer.full_name', 'Cliente sincronizado');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Cliente sincronizado']);
        $this->get('/cuenta')->assertOk();
    }

    public function test_repeated_offline_favorite_updates_are_idempotent_and_visible_in_web_catalog(): void
    {
        $user = $this->customer();
        $this->login($user);
        $productId = DB::table('products')->value('id');
        $path = '/api/mobile/favorites/'.$productId;
        $this->putJson($path, ['favorited' => true])->assertOk();
        $this->putJson($path, ['favorited' => true])->assertOk();
        $this->assertSame(1, DB::table('storefront_favorites')->where('product_id', $productId)->count());
        $this->getJson('/api/mobile/sync')->assertOk()->assertJsonPath('catalog.favorite_ids.0', (int) $productId);
        $this->putJson($path, ['favorited' => false])->assertOk();
        $this->putJson($path, ['favorited' => false])->assertOk();
        $this->assertSame(0, DB::table('storefront_favorites')->where('product_id', $productId)->count());
    }

    public function test_sync_never_exposes_another_customers_account(): void
    {
        $first = $this->customer();
        $this->login($first);
        $this->postJson('/api/mobile/profile', ['name' => 'Perfil privado uno', 'phone' => '999888111'])->assertOk();
        $this->postJson('/api/mobile/logout')->assertOk();
        $second = $this->customer();
        $this->login($second);
        $this->getJson('/api/mobile/sync')->assertOk()->assertJsonPath('account.customer.email', $second->email)
            ->assertDontSee('Perfil privado uno');
    }

    public function test_mobile_registration_creates_real_customer_and_reuses_web_password_validation(): void
    {
        $this->withSession(['_token' => 'mobile-test-token', 'csrf_token' => 'mobile-test-token'])
            ->withHeader('X-CSRF-TOKEN', 'mobile-test-token');
        $data = ['name' => 'Cliente de la app', 'email' => 'native@example.test', 'phone' => '999888777', 'password' => 'CustomerPass123!', 'password_confirmation' => 'CustomerPass123!'];
        $this->postJson('/api/mobile/register', [...$data, 'password' => 'short', 'password_confirmation' => 'short'])->assertUnprocessable();
        $this->postJson('/api/mobile/register', $data)->assertCreated()->assertJsonPath('user.role_code', 'customer');
        $this->assertDatabaseHas('customers', ['email' => $data['email'], 'phone' => $data['phone']]);
        $this->postJson('/api/mobile/logout')->assertOk();
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->login($user);
        $this->get('/cuenta')->assertOk();
    }
}
