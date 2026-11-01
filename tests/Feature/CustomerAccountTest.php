<?php

namespace Tests\Feature;

use App\Mail\AccountVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function authenticateUser(?User $user = null, string $password = 'password123'): User
    {
        $roleId = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->insertGetId(['code' => 'customer', 'name' => 'Cliente', 'created_at' => now(), 'updated_at' => now()]);

        $user = $user ?? User::factory()->create([
            'role_id' => $roleId,
            'password_hash' => Hash::make($password),
        ]);

        $this->withSession([
            '_token' => 'test-csrf-token',
            'user_id' => $user->id,
            'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key')),
        ])->withHeader('X-CSRF-TOKEN', 'test-csrf-token');

        return $user;
    }

    public function test_authenticated_user_accesses_account_without_403_and_auto_provisions_customer(): void
    {
        $user = $this->authenticateUser();

        // Customer does not exist yet before accessing /cuenta
        $this->assertDatabaseMissing('customers', ['user_id' => $user->id]);

        $response = $this->get('/cuenta');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Restaurant/Account')
                ->has('account.customer')
                ->where('account.customer.user_id', $user->id)
                ->has('account.stats.total_spent')
                ->has('account.stats.orders_count')
                ->has('account.stats.active_reservations')
                ->has('account.stats.points_balance')
                ->has('account.loyalty.points_balance')
                ->has('account.loyalty.tier')
                ->has('account.orders')
                ->has('account.addresses')
                ->has('account.reservations'));

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_staff_can_also_open_account_cleanly_without_403(): void
    {
        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');
        $admin = User::factory()->create(['role_id' => $adminRoleId, 'password_hash' => Hash::make('secretadmin')]);

        $this->authenticateUser($admin, 'secretadmin');

        $response = $this->get('/cuenta');

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Restaurant/Account')
                ->where('account.customer.user_id', $admin->id)
                ->where('auth.user.role_code', 'admin'));
    }

    public function test_customer_can_update_profile_including_document_and_birth_date(): void
    {
        $user = $this->authenticateUser();

        $this->post('/cuenta/perfil', [
            'name' => 'Carlos Gastronomico',
            'phone' => '987654321',
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'birth_date' => '1995-08-15',
        ])->assertRedirect()->assertSessionHas('success', 'Perfil actualizado.');

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'full_name' => 'Carlos Gastronomico',
            'phone' => '987654321',
            'document_type' => 'DNI',
            'document_number' => '44556677',
            'birth_date' => '1995-08-15',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Carlos Gastronomico',
        ]);
    }

    public function test_customer_can_change_password_with_current_password_verification(): void
    {
        $user = $this->authenticateUser(null, 'OldPassword123');

        // Wrong current password
        $this->post('/cuenta/password', [
            'current_password' => 'WrongPassword!',
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertSessionHasErrors(['current_password']);

        // Correct password change
        $this->post('/cuenta/password', [
            'current_password' => 'OldPassword123',
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ])->assertRedirect()->assertSessionHas('success', 'Contraseña actualizada correctamente.');

        $updatedHash = DB::table('users')->where('id', $user->id)->value('password_hash');
        $this->assertTrue(Hash::check('NewSecurePassword123', $updatedHash));
    }

    public function test_customer_can_manage_addresses_and_set_default(): void
    {
        $user = $this->authenticateUser();

        $this->post('/cuenta/direcciones', [
            'label' => 'Casa',
            'address_line' => 'Av. Primavera 123',
            'district' => 'Surco',
            'reference' => 'Frente al parque',
            'phone' => '999888777',
            'is_default' => 1,
        ])->assertRedirect()->assertSessionHas('success', 'Direccion guardada.');

        $this->post('/cuenta/direcciones', [
            'label' => 'Oficina',
            'address_line' => 'Av. Canaval y Moreyra 456',
            'district' => 'San Isidro',
            'reference' => 'Piso 8',
            'phone' => '999888777',
            'is_default' => 0,
        ])->assertRedirect();

        $addressCasa = DB::table('customer_addresses')->where('label', 'Casa')->first();
        $addressOficina = DB::table('customer_addresses')->where('label', 'Oficina')->first();

        $this->assertEquals(1, $addressCasa->is_default);
        $this->assertEquals(0, $addressOficina->is_default);

        // Switch default to Oficina
        $this->post('/cuenta/direcciones/'.$addressOficina->id.'/predeterminada')
            ->assertRedirect()
            ->assertSessionHas('success', 'Dirección predeterminada actualizada.');

        $this->assertEquals(0, DB::table('customer_addresses')->where('id', $addressCasa->id)->value('is_default'));
        $this->assertEquals(1, DB::table('customer_addresses')->where('id', $addressOficina->id)->value('is_default'));
    }

    public function test_customer_can_redeem_loyalty_rewards(): void
    {
        $user = $this->authenticateUser();

        // Ensure user customer profile and loyalty points
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();

        DB::table('loyalty_accounts')->updateOrInsert(
            ['customer_id' => $customer->id],
            [
                'points_balance' => 150,
                'lifetime_earned' => 150,
                'lifetime_redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $rewardId = DB::table('loyalty_rewards')->insertGetId([
            'reward_code' => 'REW-POSTRE',
            'name' => 'Suspiro Limeño Gratis',
            'reward_type' => 'free_product',
            'points_cost' => 50,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insufficient reward costs 200 points
        $expensiveRewardId = DB::table('loyalty_rewards')->insertGetId([
            'reward_code' => 'REW-VIP',
            'name' => 'Cena Degustación',
            'reward_type' => 'discount',
            'points_cost' => 200,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/cuenta/fidelizacion/canjear', ['reward_id' => $expensiveRewardId])
            ->assertSessionHasErrors(['reward']);

        // Redeem 50 points reward successfully
        $this->post('/cuenta/fidelizacion/canjear', ['reward_id' => $rewardId])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(100, DB::table('loyalty_accounts')->where('customer_id', $customer->id)->value('points_balance'));
        $this->assertDatabaseHas('loyalty_redemptions', [
            'customer_id' => $customer->id,
            'reward_id' => $rewardId,
            'points_spent' => 50,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'transaction_type' => 'redeem',
            'points_delta' => -50,
            'balance_after' => 100,
        ]);
    }

    public function test_customer_can_toggle_dish_favorite(): void
    {
        $user = $this->authenticateUser();
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        $productId = (int) DB::table('products')->value('id');

        $visitorKey = hash('sha256', 'customer_'.$customer->id);

        $this->post('/cuenta/favoritos/'.$productId.'/toggle')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('storefront_favorites', [
            'visitor_key' => $visitorKey,
            'product_id' => $productId,
        ]);

        // Toggle off
        $this->post('/cuenta/favoritos/'.$productId.'/toggle')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('storefront_favorites', [
            'visitor_key' => $visitorKey,
            'product_id' => $productId,
        ]);
    }

    public function test_customer_can_save_preferences(): void
    {
        $user = $this->authenticateUser();
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        DB::table('customers')->where('id', $customer->id)->update(['notes' => 'Nota interna del cliente']);
        DB::table('user_preferences')->where('user_id', $user->id)->update([
            'show_notification_badge' => 1,
            'sound_notifications' => 1,
        ]);

        $this->post('/cuenta/preferencias', [
            'desktop_notifications' => false,
            'order_emails' => true,
            'preferred_payment' => 'Yape/Plin',
        ])->assertRedirect()->assertSessionHas('success', 'Preferencias actualizadas con éxito.');

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'show_notification_badge' => 1,
            'desktop_notifications' => 0,
            'sound_notifications' => 1,
            'account_order_emails' => 1,
            'account_preferred_payment' => 'Yape/Plin',
        ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'notes' => 'Nota interna del cliente',
        ]);
    }

    public function test_customer_account_statistics_include_records_beyond_visible_lists(): void
    {
        $user = $this->authenticateUser();
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        $tableId = (int) DB::table('restaurant_tables')->value('id');

        for ($index = 1; $index <= 51; $index++) {
            DB::table('orders')->insert([
                'order_number' => 'ORD-STAT-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'created_by' => $user->id,
                'subtotal' => 10.00,
                'discount' => 0.00,
                'total' => 10.00,
                'status' => 'Entregado',
                'order_type' => 'Para llevar',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        for ($index = 1; $index <= 31; $index++) {
            DB::table('reservations')->insert([
                'reservation_code' => 'RES-STAT-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'customer_name' => $customer->full_name,
                'customer_phone' => '999111222',
                'table_id' => $tableId,
                'guest_count' => 2,
                'reservation_at' => now()->addDays($index),
                'duration_minutes' => 120,
                'status' => 'CONFIRMED',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->get('/cuenta')
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.stats.orders_count', 51)
                ->where('account.stats.total_spent', 510)
                ->where('account.stats.active_reservations', 31)
                ->has('account.orders', 50)
                ->has('account.reservations', 30));
    }

    public function test_email_verification_sends_code_and_rejects_code_after_email_changes(): void
    {
        Mail::fake();
        $user = $this->authenticateUser();
        $this->get('/cuenta');

        $this->post('/cuenta/verificar/enviar', ['channel' => 'email'])
            ->assertRedirect()
            ->assertSessionHas('result');
        $code = (string) session('result.dev_code');
        $this->assertNotEmpty($code);
        Mail::assertSent(AccountVerificationCode::class, fn (AccountVerificationCode $mail): bool => $mail->hasTo($user->email) && $mail->code === $code);

        DB::table('users')->where('id', $user->id)->update(['email' => 'changed@example.test']);
        $this->post('/cuenta/verificar/confirmar', [
            'channel' => 'email',
            'code' => $code,
        ])->assertSessionHasErrors(['code']);

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('email_verified_at'));
        $this->assertNull(session('account_verification_email'));
    }

    public function test_customer_can_create_and_reply_support_ticket(): void
    {
        $user = $this->authenticateUser();
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();

        // Create support ticket
        $response = $this->post('/cuenta/soporte', [
            'category' => 'orders',
            'priority' => 'normal',
            'subject' => 'Consulta sobre mi pedido',
            'message' => 'Buenas tardes, quisiera consultar los tiempos estimados de entrega.',
        ]);
        $response->assertRedirect();
        $this->assertStringContainsString('Ticket', (string) session('success'));

        $ticket = DB::table('support_tickets')->where('created_by', $user->id)->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('orders', $ticket->category);
        $this->assertEquals('Consulta sobre mi pedido', $ticket->subject);

        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Buenas tardes, quisiera consultar los tiempos estimados de entrega.',
        ]);

        // Reply to ticket
        $this->post('/cuenta/soporte/'.$ticket->id.'/mensaje', [
            'message' => 'Muchas gracias por la atención, quedo atento.',
        ])->assertRedirect()->assertSessionHas('success', 'Mensaje agregado al ticket.');

        $this->assertDatabaseHas('support_ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Muchas gracias por la atención, quedo atento.',
        ]);
    }

    public function test_customer_can_submit_product_review_for_ordered_dish(): void
    {
        $user = $this->authenticateUser();
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        $productId = (int) DB::table('products')->value('id');

        // Create order with this product
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-REV-001',
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'created_by' => $user->id,
            'subtotal' => 45.00,
            'discount' => 0.00,
            'total' => 45.00,
            'status' => 'Entregado',
            'order_type' => 'Para llevar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => 'Ceviche Mixto',
            'unit_price' => 45.00,
            'quantity' => 1,
            'line_total' => 45.00,
        ]);

        $this->post('/cuenta/resenas', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'rating' => 5,
            'comment' => '¡Excepcional frescura y sabor norteño!',
        ])->assertRedirect()->assertSessionHas('success', '¡Gracias por tu calificación! Reseña registrada correctamente.');

        $this->assertDatabaseHas('product_reviews', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'customer_id' => $customer->id,
            'rating' => 5,
            'comment' => '¡Excepcional frescura y sabor norteño!',
            'status' => 'approved',
        ]);
    }

    public function test_customer_cannot_review_product_from_an_incomplete_order(): void
    {
        $user = $this->authenticateUser();
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        $productId = (int) DB::table('products')->value('id');
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-REV-PENDING',
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'created_by' => $user->id,
            'subtotal' => 45.00,
            'discount' => 0.00,
            'total' => 45.00,
            'status' => 'Pendiente',
            'order_type' => 'Para llevar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => 'Ceviche Mixto',
            'unit_price' => 45.00,
            'quantity' => 1,
            'line_total' => 45.00,
        ]);

        $this->post('/cuenta/resenas', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'rating' => 5,
            'comment' => 'Todavía no he recibido este pedido.',
        ])->assertSessionHasErrors(['order_id']);

        $this->assertDatabaseMissing('product_reviews', [
            'order_id' => $orderId,
            'product_id' => $productId,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_customer_can_deactivate_account(): void
    {
        $user = $this->authenticateUser(null, 'SafePassword123');
        $this->get('/cuenta');
        $customer = DB::table('customers')->where('user_id', $user->id)->first();

        // Wrong password fails
        $this->post('/cuenta/desactivar', [
            'password' => 'WrongPassword',
        ])->assertSessionHasErrors(['password']);

        // Correct password deactivates
        $this->post('/cuenta/desactivar', [
            'password' => 'SafePassword123',
        ])->assertRedirect('/');

        $this->assertEquals('inactive', DB::table('users')->where('id', $user->id)->value('status'));
        $this->assertEquals('inactive', DB::table('customers')->where('id', $customer->id)->value('status'));
    }
}
