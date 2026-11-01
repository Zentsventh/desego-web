<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\OfficialCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class WebPlatformCompletionTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    protected User $admin;

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        (new OfficialCatalogSeeder)->run();

        $adminRoleId = (int) (DB::table('roles')->where('code', 'admin')->value('id') ?? 1);
        $this->admin = User::factory()->create([
            'name' => 'Admin DeSego',
            'email' => 'admin@desego.test',
            'role_id' => $adminRoleId,
            'password_hash' => Hash::make('AdminSecret123!'),
            'status' => 'active',
        ]);

        $this->withCsrfSession();
    }

    private function withCsrfSession(array $extra = []): void
    {
        $this->withSession(array_merge([
            '_token' => 'test-csrf-token',
            'csrf_token' => 'test-csrf-token',
        ], $extra))->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }

    private function authenticateAs(User $user): void
    {
        $this->withCsrfSession([
            'user_id' => $user->id,
            'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key')),
        ]);
    }

    private function createCustomerUser(string $password = 'CustomerPass123!'): User
    {
        $roleId = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->insertGetId([
                'code' => 'customer',
                'name' => 'Cliente',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::factory()->create([
            'name' => 'Valeria Marina',
            'email' => 'valeria@cliente.test',
            'role_id' => $roleId,
            'password_hash' => Hash::make($password),
            'status' => 'active',
        ]);
    }

    public function test_club_desego_loyalty_coupon_applies_in_storefront_quote_and_checkout(): void
    {
        $customerUser = $this->createCustomerUser();
        $this->authenticateAs($customerUser);
        $this->get('/cuenta')->assertOk();

        $customer = DB::table('customers')->where('user_id', $customerUser->id)->first();
        $this->assertNotNull($customer);

        DB::table('loyalty_accounts')->updateOrInsert(
            ['customer_id' => $customer->id],
            [
                'points_balance' => 120,
                'lifetime_earned' => 120,
                'lifetime_redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $rewardId = (int) DB::table('loyalty_rewards')->where('reward_code', 'DESEGO-S5')->value('id');
        $this->post('/cuenta/fidelizacion/canjear', ['reward_id' => $rewardId])
            ->assertRedirect()
            ->assertSessionHas('success');

        $redemption = DB::table('loyalty_redemptions')
            ->where('customer_id', $customer->id)
            ->where('status', 'issued')
            ->first();
        $this->assertNotNull($redemption);

        $cevicheId = (int) DB::table('products')->where('code', 'PF001')->value('id');

        // 1. Quote applies S/ 5.00 Club DeSego coupon
        $quoteResponse = $this->postJson('/api/storefront', [
            'action' => 'quote',
            'csrf_token' => 'test-csrf-token',
            'order_type' => 'Para llevar',
            'coupon_code' => $redemption->redemption_code,
            'email' => $customerUser->email,
            'phone' => '987654321',
            'items' => [
                ['product_id' => $cevicheId, 'quantity' => 1],
            ],
        ]);

        $quoteResponse->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('quote.subtotal', 22)
            ->assertJsonPath('quote.discount', 5)
            ->assertJsonPath('quote.total', 17);

        // 2. Checkout deducts S/ 5.00 and marks redemption as used
        $checkoutKey = '11111111-2222-4000-8000-000000000099';
        $checkoutResponse = $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'csrf_token' => 'test-csrf-token',
            'order_type' => 'Para llevar',
            'customer_name' => 'Valeria Marina',
            'phone' => '987654321',
            'email' => $customerUser->email,
            'payment_preference' => 'Yape/Plin',
            'coupon_code' => $redemption->redemption_code,
            'consent' => true,
            'checkout_key' => $checkoutKey,
            'items' => [
                ['product_id' => $cevicheId, 'quantity' => 1],
            ],
        ]);

        $checkoutResponse->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertEquals(17.0, (float) $checkoutResponse->json('total'));
        $this->assertDatabaseHas('loyalty_redemptions', [
            'id' => $redemption->id,
            'status' => 'used',
        ]);

        // 3. Reusing the same Club DeSego redemption code is rejected with 409
        $reuseResponse = $this->postJson('/api/storefront', [
            'action' => 'quote',
            'csrf_token' => 'test-csrf-token',
            'order_type' => 'Para llevar',
            'coupon_code' => $redemption->redemption_code,
            'items' => [
                ['product_id' => $cevicheId, 'quantity' => 1],
            ],
        ]);
        $reuseResponse->assertStatus(409);
    }

    public function test_admin_product_update_preserves_code_and_supports_gallery_options_and_item_notes_in_checkout(): void
    {
        $this->authenticateAs($this->admin);

        $trio = DB::table('products')->where('code', 'DT001')->first();
        $this->assertNotNull($trio);

        $updateResponse = $this->postJson('/api/catalog', [
            'action' => 'product.update',
            'csrf_token' => 'test-csrf-token',
            'id' => $trio->id,
            'category_id' => $trio->category_id,
            'name' => 'Trío marino 1',
            'description' => $trio->description,
            'price' => 35.00,
            'status' => 'available',
            'included_items' => 'Ceviche de pescado + Arroz con mariscos + Chicharrón de pescado',
            'image_url' => '/storage/products/trio-marino-1.jpg',
            'is_featured' => true,
            'is_popular' => true,
            'is_new' => true,
            'gallery' => [
                '/storage/products/trio-marino-1-side.jpg',
            ],
            'option_groups' => [
                [
                    'name' => 'Guarniciones adicionales',
                    'is_required' => false,
                    'max_selections' => 2,
                    'options' => [
                        ['name' => 'Porción extra de chifles', 'price_delta' => 3.50, 'is_available' => true],
                    ],
                ],
            ],
        ]);

        $updateResponse->assertOk()->assertJsonPath('ok', true);

        // Verify code DT001 was preserved even though it was omitted from payload
        $this->assertDatabaseHas('products', [
            'id' => $trio->id,
            'code' => 'DT001',
            'included_items' => 'Ceviche de pescado + Arroz con mariscos + Chicharrón de pescado',
            'image_url' => '/storage/products/trio-marino-1.jpg',
            'is_new' => 1,
        ]);

        $optionId = (int) DB::table('product_options')->where('name', 'Porción extra de chifles')->value('id');
        $this->assertGreaterThan(0, $optionId);

        // Verify public storefront exposes gallery and option_groups
        $storefrontData = $this->getJson('/api/storefront')->assertOk()->json('products');
        $publicTrio = collect($storefrontData)->firstWhere('code', 'DT001');
        $this->assertNotNull($publicTrio);
        $this->assertNotEmpty($publicTrio['gallery']);
        $this->assertNotEmpty($publicTrio['option_groups']);

        // Checkout with selected_options (+S/ 3.50) and per-item notes
        $checkoutKey = '22222222-3333-4000-8000-000000000088';
        $checkoutResponse = $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'csrf_token' => 'test-csrf-token',
            'order_type' => 'Para llevar',
            'customer_name' => 'Mateo Ríos',
            'phone' => '998877665',
            'payment_preference' => 'Efectivo',
            'consent' => true,
            'checkout_key' => $checkoutKey,
            'items' => [
                [
                    'product_id' => $trio->id,
                    'quantity' => 2,
                    'notes' => 'Poco ají limo y cebolla aparte',
                    'selected_options' => [$optionId],
                ],
            ],
        ]);

        $checkoutResponse->assertOk()->assertJsonPath('ok', true);
        $this->assertEquals(77.0, (float) $checkoutResponse->json('total'));

        $orderId = (int) $checkoutResponse->json('order_id');
        $orderItem = DB::table('order_items')->where('order_id', $orderId)->first();
        $this->assertNotNull($orderItem);
        $this->assertEquals(38.5, (float) $orderItem->unit_price);
        $this->assertEquals('Poco ají limo y cebolla aparte', $orderItem->notes);
        $this->assertStringContainsString('Porción extra de chifles', (string) $orderItem->selected_options);
    }

    public function test_delivery_zones_crud_and_live_geolocation_coverage_validation(): void
    {
        $this->authenticateAs($this->admin);

        $createZone = $this->postJson('/api/operations', [
            'action' => 'zone.create',
            'csrf_token' => 'test-csrf-token',
            'name' => 'Huaycán Zona B Especial',
            'district' => 'Ate',
            'zone_sector' => 'Zona B',
            'center_latitude' => -12.0125,
            'center_longitude' => -76.8285,
            'radius_km' => 2.0,
            'delivery_fee' => 4.50,
            'fee_confirmed' => true,
            'min_order_amount' => 20.00,
            'estimated_minutes_min' => 25,
            'estimated_minutes_max' => 40,
            'is_active' => true,
        ]);

        $createZone->assertStatus(201)->assertJsonPath('ok', true);

        // Inside coverage (Huaycán coordinates)
        $covered = $this->getJson('/api/storefront?view=coverage&lat=-12.0125&lng=-76.8285');
        $covered->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('covered', true)
            ->assertJsonStructure(['zone' => ['id', 'name']]);

        // Outside coverage (Miraflores coordinates)
        $outside = $this->getJson('/api/storefront?view=coverage&lat=-12.1219&lng=-77.0297');
        $outside->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('covered', false);

        // Checkout with out-of-coverage coordinates must fail with 422
        $cevicheId = (int) DB::table('products')->where('code', 'PF001')->value('id');
        $rejectedCheckout = $this->postJson('/api/storefront', [
            'action' => 'checkout',
            'csrf_token' => 'test-csrf-token',
            'order_type' => 'Delivery',
            'customer_name' => 'Cliente Lejano',
            'phone' => '999111222',
            'address_line' => 'Av. Larco 500',
            'district' => 'Miraflores',
            'latitude' => -12.1219,
            'longitude' => -77.0297,
            'payment_preference' => 'Efectivo',
            'consent' => true,
            'checkout_key' => '33333333-4444-4000-8000-000000000077',
            'items' => [
                ['product_id' => $cevicheId, 'quantity' => 1],
            ],
        ]);

        $rejectedCheckout->assertStatus(422);
    }

    public function test_admin_commercial_hours_reservation_settings_and_reviews_reply_flow(): void
    {
        $this->authenticateAs($this->admin);

        // 1. Update business hours
        $this->postJson('/api/admin-tools', [
            'action' => 'update_business_hours',
            'csrf_token' => 'test-csrf-token',
            'hours' => [
                ['day_of_week' => 1, 'open_time' => '09:30', 'close_time' => '17:30', 'is_closed' => false],
                ['day_of_week' => 2, 'open_time' => '09:00', 'close_time' => '17:00', 'is_closed' => false],
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('business_hours', [
            'day_of_week' => 1,
            'open_time' => '09:30:00',
            'close_time' => '17:30:00',
        ]);

        // 2. Update commercial settings
        $this->postJson('/api/admin-tools', [
            'action' => 'update_commercial_settings',
            'csrf_token' => 'test-csrf-token',
            'restaurant_name' => 'DeSego Cevichería',
            'restaurant_specialty' => 'Pescados, mariscos y comida peruana',
            'restaurant_address' => 'UCV 19, Lote 13, Zona A, Huaycán, Ate',
            'restaurant_district' => 'Ate',
            'restaurant_postal_code' => '15479',
            'restaurant_latitude' => -12.01685,
            'restaurant_longitude' => -76.82112,
            'restaurant_whatsapp' => '+51 922 119 238',
            'restaurant_facebook' => 'https://facebook.com/desegocevicheria',
            'restaurant_instagram' => 'https://instagram.com/desegocevicheria',
            'restaurant_tiktok' => 'https://www.tiktok.com/@desegocevicheria/',
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'dine_in_enabled' => true,
            'reservations_enabled' => true,
            'payment_methods' => ['Efectivo', 'Yape/Plin', 'Tarjeta al recibir'],
        ])->assertOk()->assertJsonPath('ok', true);

        // 3. Update reservation settings (e.g. S/ 0.00 advance)
        $this->postJson('/api/admin-tools', [
            'action' => 'update_reservation_settings',
            'csrf_token' => 'test-csrf-token',
            'advance_amount' => 0.00,
            'tolerance_minutes' => 20,
            'cancellation_deadline_hours' => 2,
            'reservations_enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $settingsView = $this->getJson('/api/admin-tools?view=settings')->assertOk();
        $settingsView->assertJsonPath('reservation_config.tolerance_minutes', 20);
        $this->assertEquals(0.0, (float) $settingsView->json('reservation_config.advance_amount'));

        // 4. Official reply on review + helpful counter + public endpoint
        $customerId = DB::table('customers')->insertGetId([
            'full_name' => 'Rosa Huamán',
            'phone' => '988776655',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = (int) DB::table('products')->where('code', 'PF004')->value('id');
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-REV-777',
            'customer_id' => $customerId,
            'customer_name' => 'Rosa Huamán',
            'created_by' => $this->admin->id,
            'subtotal' => 32.00,
            'discount' => 0.00,
            'total' => 32.00,
            'status' => 'Entregado',
            'order_type' => 'Para llevar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reviewId = DB::table('product_reviews')->insertGetId([
            'order_id' => $orderId,
            'product_id' => $productId,
            'customer_id' => $customerId,
            'reviewer_name' => 'Rosa Huamán',
            'rating' => 5,
            'comment' => 'El Ceviche DeSego estuvo fresco y abundante.',
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/reviews', [
            'action' => 'review.reply',
            'csrf_token' => 'test-csrf-token',
            'id' => $reviewId,
            'admin_reply' => '¡Muchas gracias Rosa! Te esperamos pronto en Huaycán.',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->postJson('/api/reviews', [
            'action' => 'review.helpful',
            'csrf_token' => 'test-csrf-token',
            'id' => $reviewId,
        ])->assertOk()->assertJsonPath('ok', true);

        $publicReviews = $this->getJson('/productos/'.$productId.'/resenas')->assertOk();
        $publicReviews->assertJsonPath('reviews.0.admin_reply', '¡Muchas gracias Rosa! Te esperamos pronto en Huaycán.')
            ->assertJsonPath('reviews.0.helpful_count', 1);
    }

    public function test_customer_account_complete_flows_and_technical_seo(): void
    {
        $password = 'SecurePass2026!';
        $user = $this->createCustomerUser($password);
        $this->authenticateAs($user);

        // 1. Update profile (verifies Schema import fix)
        $this->post('/cuenta/perfil', [
            'name' => 'Valeria Marina Quispe',
            'phone' => '922334455',
            'document_type' => 'DNI',
            'document_number' => '46778899',
        ])->assertRedirect()->assertSessionHas('success', 'Perfil actualizado.');

        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        $this->assertNotNull($customer);

        // 2. Create pending Delivery order, modify it, and render printable receipt
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-MOD-101',
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'created_by' => $user->id,
            'subtotal' => 28.00,
            'discount' => 0.00,
            'total' => 28.00,
            'status' => 'Pendiente',
            'order_type' => 'Delivery',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_delivery_details')->insert([
            'order_id' => $orderId,
            'recipient_phone' => '922334455',
            'address_line' => 'UCV 19 Lote 5, Huaycán',
            'zone' => 'Ate, Lima, Lima',
            'delivery_fee' => 0.00,
            'status' => 'Pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/cuenta/pedidos/'.$orderId.'/modificar', [
            'notes' => 'Timbre segundo piso',
            'recipient_phone' => '933445566',
            'address_line' => 'UCV 19 Lote 8, Zona A, Huaycán',
            'reference' => 'Portón azul',
            'district' => 'Ate',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'notes' => 'Timbre segundo piso',
        ]);
        $this->assertDatabaseHas('order_delivery_details', [
            'order_id' => $orderId,
            'recipient_phone' => '933445566',
        ]);

        $receiptHtml = $this->get('/cuenta/pedidos/'.$orderId.'/comprobante')->assertOk()->getContent();
        $this->assertStringContainsString('ORD-MOD-101', (string) $receiptHtml);

        // 3. Reschedule and cancel a CONFIRMED reservation from Mi Cuenta
        $futureSlot = CarbonImmutable::now('America/Lima')->addDays(2)->setTime(13, 0)->setTimezone('UTC');
        $reservationId = DB::table('reservations')->insertGetId([
            'reservation_code' => 'RES-900100',
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'customer_phone' => '922334455',
            'customer_email' => $user->email,
            'table_id' => 1,
            'guest_count' => 2,
            'reservation_at' => $futureSlot,
            'duration_minutes' => 120,
            'status' => 'CONFIRMED',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reservation_tables')->insert([
            'reservation_id' => $reservationId,
            'table_id' => 1,
        ]);

        DB::table('restaurant_tables')->where('id', '<>', 1)->update(['is_active' => 0]);
        DB::table('restaurant_tables')->where('id', 1)->update([
            'is_active' => 1,
            'operational_status' => 'available',
            'is_online_reservable' => 1,
            'capacity' => 10,
        ]);

        $sameLimaDate = $futureSlot->setTimezone('America/Lima')->format('Y-m-d');
        $this->post('/cuenta/reservas/'.$reservationId.'/reprogramar', [
            'date' => $sameLimaDate,
            'time' => '13:00',
            'people' => 2,
            'notes' => 'Mismo horario, mesa conservada',
        ])->assertRedirect()->assertSessionHas('success');

        $newLimaDate = CarbonImmutable::now('America/Lima')->addDays(3)->format('Y-m-d');
        $this->post('/cuenta/reservas/'.$reservationId.'/reprogramar', [
            'date' => $newLimaDate,
            'time' => '14:00',
            'people' => 4,
            'notes' => 'Celebración familiar',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservationId,
            'guest_count' => 4,
            'notes' => 'Celebración familiar',
        ]);

        $this->post('/cuenta/reservas/'.$reservationId.'/cancelar')
            ->assertRedirect()
            ->assertSessionHas('success');

        $cancelledStatus = DB::table('reservations')->where('id', $reservationId)->value('status');
        $this->assertContains($cancelledStatus, ['CANCELLED', 'Cancelada']);

        // 4. Support ticket with 'reservations' category
        $this->post('/cuenta/soporte', [
            'category' => 'reservations',
            'priority' => 'normal',
            'subject' => 'Duda sobre mesa para 6 personas',
            'message' => 'Quisiera saber si juntan dos mesas en salón principal.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('support_tickets', [
            'created_by' => $user->id,
            'category' => 'reservations',
        ]);

        // 5. Email 6-digit OTP verification
        Mail::fake();
        $sendOtp = $this->post('/cuenta/verificar/enviar', ['channel' => 'email']);
        $sendOtp->assertRedirect()->assertSessionHas('result');
        $devCode = session('result.dev_code');
        $this->assertNotEmpty($devCode);

        $this->post('/cuenta/verificar/confirmar', [
            'channel' => 'email',
            'code' => $devCode,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('email_verified_at'));

        // 6. Technical SEO: dynamic sitemap.xml
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('<urlset', (string) $sitemap->getContent());

        // 7. Permanent account deletion & anonymization
        $this->post('/cuenta/eliminar', [
            'password' => $password,
            'confirmation' => 'ELIMINAR',
        ])->assertRedirect('/');

        $deletedUser = DB::table('users')->where('id', $user->id)->first();
        $this->assertEquals('inactive', $deletedUser->status);
        $this->assertStringStartsWith('eliminado_', (string) $deletedUser->email);
    }
}
