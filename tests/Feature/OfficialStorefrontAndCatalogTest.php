<?php

namespace Tests\Feature;

use Database\Seeders\OfficialCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class OfficialStorefrontAndCatalogTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        (new OfficialCatalogSeeder)->run();

        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id') ?? 1;
        DB::table('users')->insertOrIgnore([
            'id' => 1,
            'name' => 'Admin Desego',
            'email' => 'admin@desego.pe',
            'password_hash' => bcrypt('admin123'),
            'role_id' => $adminRoleId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession([
            '_token' => 'test-csrf-token',
            'csrf_token' => 'test-csrf-token',
        ])->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }

    public function test_catalog_contains_exactly_35_official_dishes_and_correct_categories(): void
    {
        $response = $this->get('/api/storefront');
        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['ok']);

        // Check products count
        $products = collect($data['products']);
        $this->assertCount(35, $products);

        // Check exact code counts per category
        $frescos = $products->filter(fn ($p) => str_starts_with($p['code'], 'PF'));
        $this->assertCount(8, $frescos);
        $this->assertEquals(['PF001', 'PF002', 'PF003', 'PF004', 'PF005', 'PF006', 'PF007', 'PF008'], $frescos->pluck('code')->all());

        $calientes = $products->filter(fn ($p) => str_starts_with($p['code'], 'PC'));
        $this->assertCount(11, $calientes);
        $this->assertEquals(['PC001', 'PC002', 'PC003', 'PC004', 'PC005', 'PC006', 'PC007', 'PC008', 'PC009', 'PC010', 'PC011'], $calientes->pluck('code')->all());

        $frituras = $products->filter(fn ($p) => str_starts_with($p['code'], 'FR'));
        $this->assertCount(6, $frituras);
        $this->assertEquals(['FR001', 'FR002', 'FR003', 'FR004', 'FR005', 'FR006'], $frituras->pluck('code')->all());

        $duos = $products->filter(fn ($p) => str_starts_with($p['code'], 'DT'));
        $this->assertCount(10, $duos);
        $this->assertEquals(['DT001', 'DT002', 'DT003', 'DT004', 'DT005', 'DT006', 'DT007', 'DT008', 'DT009', 'DT010'], $duos->pluck('code')->all());

        // Check categories
        $categories = collect($data['categories'])->pluck('name')->all();
        $this->assertContains('Platos frescos', $categories);
        $this->assertContains('Platos calientes', $categories);
        $this->assertContains('Frituras', $categories);
        $this->assertContains('Dúos, tríos y rondas marinas', $categories);
        $this->assertContains('Bebidas', $categories);

        // Check official business hours and delivery zones
        $this->assertNotEmpty($data['business_hours']);
        $this->assertNotEmpty($data['delivery_zones']);
    }

    public function test_storefront_order_flow_for_delivery_modality(): void
    {
        $cevicheId = (int) DB::table('products')->where('code', 'PF001')->value('id');
        $checkoutKey = 'abc12345-6789-4000-8000-000000000001';

        $response = $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'order_type' => 'Delivery',
            'delivery_zone_id' => 1,
            'customer_name' => 'Carlos Mendoza',
            'phone' => '999123456',
            'email' => 'carlos.mendoza@gmail.com',
            'address_line' => 'Av. Los Incas 123, Dpto 4B',
            'district' => 'Ate',
            'reference' => 'Frente al parque',
            'payment_preference' => 'Efectivo',
            'consent' => true,
            'checkout_key' => $checkoutKey,
            'items' => [
                ['product_id' => $cevicheId, 'quantity' => 2],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['ok']);
        $this->assertNotEmpty($data['order_number']);

        $this->assertDatabaseHas('orders', [
            'web_checkout_key' => $checkoutKey,
            'order_type' => 'Delivery',
            'customer_name' => 'Carlos Mendoza',
        ]);
    }

    public function test_storefront_order_flow_for_takeout_modality(): void
    {
        $arrozId = (int) DB::table('products')->where('code', 'PC001')->value('id');
        $checkoutKey = 'abc12345-6789-4000-8000-000000000002';

        $response = $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'order_type' => 'Para llevar',
            'customer_name' => 'Lucía Vargas',
            'phone' => '987654321',
            'email' => 'lucia.vargas@outlook.com',
            'payment_preference' => 'Yape/Plin',
            'consent' => true,
            'checkout_key' => $checkoutKey,
            'items' => [
                ['product_id' => $arrozId, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['ok']);

        $this->assertDatabaseHas('orders', [
            'web_checkout_key' => $checkoutKey,
            'order_type' => 'Para llevar',
            'customer_name' => 'Lucía Vargas',
        ]);
    }

    public function test_storefront_order_flow_for_dine_in_table_modality(): void
    {
        $duoId = (int) DB::table('products')->where('code', 'DT005')->value('id');
        $checkoutKey = 'abc12345-6789-4000-8000-000000000003';

        $response = $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'order_type' => 'Consumo en local',
            'table_label' => 'Mesa 4',
            'customer_name' => 'Jorge Quispe',
            'phone' => '999888777',
            'payment_preference' => 'Tarjeta al recibir',
            'consent' => true,
            'checkout_key' => $checkoutKey,
            'items' => [
                ['product_id' => $duoId, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['ok']);

        $this->assertDatabaseHas('orders', [
            'web_checkout_key' => $checkoutKey,
            'order_type' => 'Mesa',
            'table_label' => 'Mesa 4',
            'customer_name' => 'Jorge Quispe',
        ]);
    }

    public function test_customer_promotion_limits_allow_once_or_unlimited_use(): void
    {
        $productId = (int) DB::table('products')->where('code', 'PF001')->value('id');
        $now = now('UTC');
        $promotionId = DB::table('promotions')->insertGetId([
            'name' => 'Cupón de un solo uso',
            'promotion_type' => 'coupon',
            'code' => 'UNAVEZ',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_subtotal' => 0,
            'starts_at' => $now->copy()->subMinute(),
            'per_customer_limit' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'order_type' => 'Para llevar',
            'customer_name' => 'Cliente de prueba',
            'phone' => '999123456',
            'email' => 'cliente@desego.pe',
            'payment_preference' => 'Efectivo',
            'consent' => true,
            'checkout_key' => 'abc12345-6789-4000-8000-000000000004',
            'coupon_code' => 'UNAVEZ',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ])->assertOk()->assertJsonPath('ok', true);

        DB::table('promotions')->insert([
            'name' => 'Cupón permanente',
            'promotion_type' => 'coupon',
            'code' => 'SIEMPRE',
            'discount_type' => 'fixed',
            'discount_value' => 5,
            'min_subtotal' => 0,
            'starts_at' => $now->copy()->subMinute(),
            'per_customer_limit' => 0,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $quote = [
            'action' => 'quote',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
            'customer_phone' => '999 123 456',
        ];

        $this->postJson('/api/storefront', [
            'action' => 'quote',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
            'coupon_code' => 'UNAVEZ',
        ])->assertStatus(422);

        $this->postJson('/api/storefront', [
            ...$quote,
            'coupon_code' => 'UNAVEZ',
            'customer_email' => 'otro-correo@desego.pe',
        ])->assertStatus(409);

        $this->postJson('/api/storefront', [
            ...$quote,
            'coupon_code' => 'SIEMPRE',
            'customer_email' => 'cliente@desego.pe',
        ])->assertOk()->assertJsonPath('quote.discount', 5);

        $this->assertDatabaseHas('promotion_redemptions', [
            'promotion_id' => $promotionId,
            'customer_key' => hash('sha256', 'phone:999123456'),
        ]);
    }
}
