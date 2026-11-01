<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class RestaurantExperienceTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
    }

    private function registerCustomer(string $email = 'client@example.test'): int
    {
        $this->withSession(['_token' => 'portal-token'])->withHeader('X-CSRF-TOKEN', 'portal-token');
        $this->postJson('/api/mobile/register', ['name' => 'Cliente Real', 'email' => $email, 'password' => 'SecureCustomer123', 'password_confirmation' => 'SecureCustomer123', 'phone' => '999111222'])->assertCreated()->assertJsonPath('user.role_code', 'customer');
        $this->withHeader('X-CSRF-TOKEN', session()->token());

        return (int) session()->get('user_id');
    }

    private function checkoutPayload(string $key = '12345678-1234-1234-1234-123456789abc'): array
    {
        return ['customer_name' => 'Cliente Real', 'phone' => '999111222', 'email' => 'client@example.test', 'order_type' => 'Para llevar', 'payment_preference' => 'Efectivo', 'checkout_key' => $key, 'consent' => true, 'items' => [['product_id' => DB::table('products')->where('status', 'available')->value('id'), 'quantity' => 1]]];
    }

    public function test_professional_store_receives_real_catalog_props(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Restaurant/Store')->has('catalog.products', 6)->has('catalog.categories', 4)->where('catalog.delivery_fee', 5)->where('auth.user', null));
        $this->get('/admin/')->assertRedirect('/ingresar?staff=1');
    }

    public function test_staff_dashboard_has_current_data_and_no_customer_can_enter(): void
    {
        $user = $this->admin();
        $this->withSession(['user_id' => $user->id, 'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key'))])->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Restaurant/Admin')->where('data.sales', 0)->where('data.orders_today', 0));
        $this->registerCustomer();
        $this->get('/admin')->assertForbidden();
        $this->getJson('/api/mobile/dashboard')->assertForbidden();
    }

    public function test_mobile_catalog_matches_the_web_and_profile_is_private(): void
    {
        $this->getJson('/api/mobile/catalog')->assertOk()->assertJsonCount(6, 'products')->assertJsonPath('delivery_fee', 5);
        $this->getJson('/api/mobile/account')->assertUnauthorized();
        $id = $this->registerCustomer();
        $this->getJson('/api/mobile/account')->assertOk()->assertJsonPath('customer.user_id', $id);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_tools_keeps_the_staff_session_and_uses_the_current_admin_panel(): void
    {
        $user = $this->admin();
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->get('/admin/herramientas')->assertOk()->assertSessionHas('user_id', $user->id)
            ->assertInertia(fn (Assert $page) => $page->component('Restaurant/Admin')
                ->where('section', 'herramientas')->where('title', 'Herramientas')
                ->where('auth.user.id', $user->id)->where('navigation.0.key', 'dashboard'));
        $this->get('/admin/seccion/pedidos')->assertOk()->assertSessionHas('user_id', $user->id);
    }

    public function test_tools_only_lists_modules_allowed_for_the_staff_role(): void
    {
        $user = User::factory()->create();
        $this->withSession(['user_id' => $user->id, 'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key'))]);
        $this->get('/admin/herramientas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Restaurant/Admin')->where('section', 'herramientas')
            ->has('navigation', 1)->where('navigation.0.key', 'dashboard'));
        $this->get('/admin/seccion/roles')->assertForbidden();
    }

    public function test_tools_rejects_guests_and_customers(): void
    {
        $this->get('/admin/herramientas')->assertRedirect('/ingresar?staff=1');
        $this->registerCustomer();
        $this->get('/admin/herramientas')->assertForbidden();
    }

    public function test_mobile_pickup_is_real_idempotent_and_uses_database_prices(): void
    {
        $this->admin();
        $this->registerCustomer();
        $payload = $this->checkoutPayload();
        $payload['total'] = 0.01;
        $product = DB::table('products')->where('id', $payload['items'][0]['product_id'])->first();
        $first = $this->postJson('/api/mobile/checkout', $payload)->assertOk();
        $this->assertEquals((float) $product->price, $first->json('total'));
        $this->postJson('/api/mobile/checkout', $payload)->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('order_delivery_details', 0);
        $this->assertDatabaseHas('orders', ['order_type' => 'Para llevar', 'payment_status' => 'Pendiente']);
        $this->getJson('/api/mobile/account')->assertOk()->assertJsonCount(1, 'orders');
        $this->postJson('/api/mobile/orders/'.$first->json('order_id').'/cancel')->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $first->json('order_id'), 'status' => 'Cancelado']);
    }

    public function test_customer_cannot_read_or_cancel_another_customers_orders_or_addresses(): void
    {
        $this->admin();
        $this->registerCustomer();
        $order = $this->postJson('/api/mobile/checkout', $this->checkoutPayload())->assertOk()->json('order_id');
        $address = $this->postJson('/api/mobile/addresses', ['label' => 'Casa', 'address_line' => 'Avenida Prueba 123', 'district' => 'Lima'])->assertOk()->json('id');
        $this->postJson('/api/mobile/logout')->assertOk();
        $this->registerCustomer('second@example.test');
        $this->getJson('/api/mobile/account')->assertJsonCount(0, 'orders')->assertJsonCount(0, 'addresses');
        $this->postJson('/api/mobile/orders/'.$order.'/cancel')->assertNotFound();
        $this->deleteJson('/api/mobile/addresses/'.$address)->assertNotFound();
    }

    public function test_inertia_checkout_redirects_with_confirmation_instead_of_json_api(): void
    {
        $this->admin();
        $payload = $this->checkoutPayload();
        $this->from('/')->withSession(['_token' => 'web-token'])->post('/pedido', $payload, ['X-CSRF-TOKEN' => 'web-token'])->assertRedirect('/')->assertSessionHas('result.type', 'order');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_real_reservations_can_be_seen_on_account_and_cancelled(): void
    {
        DB::table('branches')->where('is_main', 1)->update(['opening_time' => '12:00:00', 'closing_time' => '22:00:00']);
        $this->registerCustomer();
        $date = now('America/Lima')->addDays(2)->format('Y-m-d');
        $result = $this->postJson('/api/mobile/reservations', ['customer_name' => 'Cliente Real', 'phone' => '999111222', 'date' => $date, 'time' => '13:00', 'people' => 2])->assertOk();
        $this->assertDatabaseHas('reservations', ['id' => $result->json('id'), 'status' => 'Pendiente']);
        $this->getJson('/api/mobile/account')->assertJsonCount(1, 'reservations');
        $this->postJson('/api/mobile/reservations/'.$result->json('id').'/cancel')->assertOk();
    }

    public function test_csrf_protects_android_mutations_too(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/mobile/register', ['name' => 'Attacker'])->assertStatus(419);
    }
}
