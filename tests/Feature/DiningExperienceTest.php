<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DiningService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class DiningExperienceTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    /**
     * The shared trait only wipes tables from the legacy schema, so the dining tables are cleared here.
     */
    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['dining_payment_attempts', 'dining_requests', 'dining_members', 'dining_sessions'] as $table) {
            DB::table($table)->delete();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function tableToken(int $number = 1): string
    {
        app(DiningService::class)->prepare();

        return (string) DB::table('restaurant_tables')->where('table_number', $number)->value('dining_qr_token');
    }

    private function join(string $token, string $name = 'Ana'): array
    {
        return $this->postJson("/api/mobile/dining/tables/{$token}/join", ['name' => $name, 'confirm' => true])->assertOk()->json();
    }

    private function orderableProductId(): int
    {
        return (int) DB::table('products')->where('status', 'available')->value('id');
    }

    public function test_the_room_is_prepared_with_twelve_tables_and_unique_qr_tokens(): void
    {
        $this->tableToken();

        $this->assertSame(12, DB::table('restaurant_tables')->count());
        $this->assertSame(4, DB::table('restaurant_tables')->where('capacity', 2)->count());
        $this->assertSame(8, DB::table('restaurant_tables')->where('capacity', 4)->count());
        $this->assertSame(12, DB::table('restaurant_tables')->whereNotNull('dining_qr_token')->distinct()->count('dining_qr_token'));
    }

    public function test_scanning_an_unknown_qr_does_not_reveal_a_table(): void
    {
        $this->tableToken();

        $this->getJson('/api/mobile/dining/tables/'.str_repeat('x', 32))->assertNotFound();
    }

    public function test_confirming_the_seat_opens_the_visit_and_marks_the_table_occupied(): void
    {
        $token = $this->tableToken(7);

        $this->getJson("/api/mobile/dining/tables/{$token}")->assertOk()->assertJsonPath('occupied', false)->assertJsonPath('table.number', 7);
        $this->postJson("/api/mobile/dining/tables/{$token}/join", ['name' => 'Ana'])->assertUnprocessable();

        $joined = $this->join($token);

        $this->assertSame('occupied', DB::table('restaurant_tables')->where('table_number', 7)->value('operational_status'));
        $this->assertSame('open', $joined['visit']['status']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $joined['visit']['join_code']);
        $this->getJson("/api/mobile/dining/tables/{$token}")->assertJsonPath('occupied', true);
    }

    public function test_a_second_guest_needs_the_six_digit_code_to_share_the_table(): void
    {
        $token = $this->tableToken();
        $first = $this->join($token);

        $this->postJson("/api/mobile/dining/tables/{$token}/join", ['name' => 'Luis', 'confirm' => true])->assertUnprocessable()->assertJsonValidationErrors('join_code');
        $this->postJson("/api/mobile/dining/tables/{$token}/join", ['name' => 'Luis', 'confirm' => true, 'join_code' => '000000'])->assertUnprocessable();
        $second = $this->postJson("/api/mobile/dining/tables/{$token}/join", ['name' => 'Luis', 'confirm' => true, 'join_code' => $first['visit']['join_code']])->assertOk()->json();

        $this->assertSame($first['visit']['id'], $second['visit']['id']);
        $this->assertNotSame($first['member_token'], $second['member_token']);
    }

    public function test_the_session_endpoint_requires_a_valid_member_token(): void
    {
        $this->tableToken();

        $this->getJson('/api/mobile/dining/session')->assertUnauthorized();
        $this->getJson('/api/mobile/dining/session', ['X-Dining-Token' => str_repeat('a', 64)])->assertUnauthorized();
    }

    public function test_an_order_from_the_table_reaches_the_kitchen_once_even_if_retried(): void
    {
        User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $token = $this->tableToken();
        $member = $this->join($token)['member_token'];
        $payload = ['checkout_key' => '3f1c1d52-0a1e-4a54-9a35-9d0f2a8f6c11', 'items' => [['product_id' => $this->orderableProductId(), 'quantity' => 1]]];

        $first = $this->postJson('/api/mobile/dining/orders', $payload, ['X-Dining-Token' => $member])->assertOk()->json();
        $this->postJson('/api/mobile/dining/orders', $payload, ['X-Dining-Token' => $member])->assertOk()->assertJsonPath('order_id', $first['order_id']);

        $this->assertSame(1, DB::table('orders')->where('dining_session_id', $first['visit']['id'])->count());
        $this->assertSame('Mesa', DB::table('orders')->where('id', $first['order_id'])->value('order_type'));
        $this->assertSame('Pendiente', DB::table('orders')->where('id', $first['order_id'])->value('status'));
        $this->assertGreaterThan(0, $first['balance']);
    }

    public function test_table_order_is_sent_to_the_kitchen_when_inventory_recipe_is_missing(): void
    {
        User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $productId = $this->orderableProductId();
        DB::table('product_ingredients')->where('product_id', $productId)->delete();
        $token = $this->tableToken();
        $member = $this->join($token)['member_token'];

        $response = $this->postJson('/api/mobile/dining/orders', [
            'checkout_key' => 'f3f604f4-9109-4ad0-9ec8-0a0e54db1f90',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ], ['X-Dining-Token' => $member])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('inventory_warning', 'Pedido recibido; cocina debe verificar la receta y el inventario de: '.DB::table('products')->where('id', $productId)->value('name').'.');

        $orderId = $response->json('order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'Pendiente',
            'notes' => '[INVENTARIO SIN RECETA] '.DB::table('products')->where('id', $productId)->value('name'),
        ]);
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'product_id' => $productId]);
        $this->assertDatabaseMissing('order_ingredients', ['order_id' => $orderId]);
    }

    public function test_calling_the_waiter_is_not_duplicated_and_asking_for_the_bill_starts_checkout(): void
    {
        $token = $this->tableToken();
        $joined = $this->join($token);
        $headers = ['X-Dining-Token' => $joined['member_token']];

        $this->postJson('/api/mobile/dining/requests', ['kind' => 'waiter'], $headers)->assertOk();
        $this->postJson('/api/mobile/dining/requests', ['kind' => 'waiter'], $headers)->assertOk();
        $this->assertSame(1, DB::table('dining_requests')->where('dining_session_id', $joined['visit']['id'])->where('kind', 'waiter')->count());

        $this->postJson('/api/mobile/dining/requests', ['kind' => 'bill'], $headers)->assertOk()->assertJsonPath('visit.status', 'checkout');
        $this->postJson('/api/mobile/dining/requests', ['kind' => 'dance'], $headers)->assertUnprocessable();
    }

    public function test_only_staff_can_see_the_floor_plan_and_it_never_exposes_qr_tokens(): void
    {
        $token = $this->tableToken(3);
        $this->join($token);

        $this->getJson('/api/mobile/dining/floor')->assertForbidden();

        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $response = $this->withSession(['user_id' => $admin->id, 'auth_password_fingerprint' => hash_hmac('sha256', $admin->password_hash, (string) config('app.key'))])
            ->getJson('/api/mobile/dining/floor')->assertOk()->assertJsonCount(12, 'tables');

        $busy = collect($response->json('tables'))->firstWhere('table_number', 3);
        $this->assertSame('open', $busy['visit']['visit']['status']);
        $this->assertArrayNotHasKey('dining_qr_token', $busy);
        $this->assertStringContainsString("/mesa/{$token}", $busy['qr_url']);
    }

    public function test_the_table_page_offers_a_3d_model_only_for_dishes_that_have_one(): void
    {
        $token = $this->tableToken();
        $productId = $this->orderableProductId();
        config(['dining.product_models' => [$productId => '/models/plato.glb']]);

        $this->get("/mesa/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Restaurant/Table')
            ->where('catalog.products', fn ($products) => collect($products)->firstWhere('id', $productId)['model_url'] === '/models/plato.glb'
                && collect($products)->where('id', '<>', $productId)->every(fn ($product) => $product['model_url'] === null)));
    }

    public function test_the_payment_start_page_is_reachable_from_the_table_page(): void
    {
        $this->get('/mesa-pago')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Restaurant/TablePayment'));
    }

    public function test_online_payment_is_unavailable_until_niubiz_credentials_exist(): void
    {
        config(['dining.niubiz.merchant_id' => null, 'dining.niubiz.password' => null]);
        $member = $this->join($this->tableToken())['member_token'];

        $this->postJson('/api/mobile/dining/payments', ['scope' => 'mine', 'email' => 'ana@example.test', 'phone' => '999111222', 'address' => 'Av. Mar 123', 'city' => 'Lima', 'postal_code' => '15001'], ['X-Dining-Token' => $member])->assertStatus(503);
    }

    public function test_commission_free_table_payment_with_yape_plin_can_be_verified_from_the_3d_floor(): void
    {
        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $token = $this->tableToken(4);
        $joined = $this->join($token);
        $member = $joined['member_token'];

        $this->postJson('/api/mobile/dining/orders', [
            'checkout_key' => '7a1c1d52-0a1e-4a54-9a35-9d0f2a8f6c22',
            'items' => [['product_id' => $this->orderableProductId(), 'quantity' => 1]],
        ], ['X-Dining-Token' => $member])->assertOk();

        $bill = $this->postJson('/api/mobile/dining/requests', [
            'kind' => 'bill',
            'notes' => 'Pago con Yape/Plin en mesa',
        ], ['X-Dining-Token' => $member])->assertOk();

        $this->assertSame('Pago con Yape/Plin en mesa', $bill->json('requests.0.notes'));

        $sessionAuth = [
            'user_id' => $admin->id,
            'auth_password_fingerprint' => hash_hmac('sha256', $admin->password_hash, (string) config('app.key')),
        ];

        $this->withSession($sessionAuth)->postJson('/api/mobile/dining/actions', [
            'action' => 'pay',
            'session_id' => $joined['visit']['id'],
            'method' => 'Yape/Plin',
        ])->assertOk()->assertJsonPath('ok', true);

        $paid = $this->getJson('/api/mobile/dining/session', ['X-Dining-Token' => $member])->assertOk();
        $this->assertSame('paid', $paid->json('visit.status'));
        $this->assertSame(0.0, (float) $paid->json('balance'));
    }
}
