<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NiubizOrderPayment;
use App\Services\RestaurantPortal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class AccountPaymentAndDocumentTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    private int $customerId;

    private int $orderId;

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        Http::preventStrayRequests();
        config([
            'services.niubiz.user' => 'test-user',
            'services.niubiz.password' => 'test-password',
            'services.niubiz.merchant_id' => '123456789',
            'services.niubiz.env' => 'sandbox',
            'services.apiperu.token' => 'document-test-token',
            'services.apiperu.document_url' => 'https://documents.example/v2',
        ]);
        $roleId = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->insertGetId(['code' => 'customer', 'name' => 'Cliente', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['role_id' => $roleId, 'password_hash' => Hash::make('CustomerPass123!')]);
        $this->withSession([
            '_token' => 'test-csrf-token', 'csrf_token' => 'test-csrf-token', 'user_id' => $user->id,
            'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key')),
        ])->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
        $this->customerId = (int) app(RestaurantPortal::class)->customer()->id;
        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        DB::table('cash_sessions')->insert(['opened_by' => $admin->id, 'opening_amount' => 0, 'status' => 'open', 'opened_at' => now()]);
        $this->orderId = DB::table('orders')->insertGetId([
            'order_number' => 'WEB-TEST-001', 'customer_id' => $this->customerId, 'customer_name' => $user->name,
            'order_type' => 'Para llevar', 'order_channel' => 'web', 'status' => 'Pendiente',
            'payment_status' => 'Pendiente', 'payment_preference' => 'Niubiz', 'total' => 65.30, 'subtotal' => 65.30,
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fakeSession(): void
    {
        Http::fake([
            '*api.security/v1/security' => Http::response(str_repeat('s', 50)),
            '*token/session/*' => Http::response(['sessionKey' => 'niubiz-session-test']),
        ]);
    }

    private function startPayment(): object
    {
        $this->fakeSession();
        $response = $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertOk();
        $attempt = DB::table('order_payment_attempts')->where('order_id', $this->orderId)->first();
        $this->assertSame('ready', $attempt->status);
        $this->assertTrue(now()->lessThan($attempt->expires_at), 'Expires '.$attempt->expires_at.'; now '.now()->toDateTimeString());
        $this->get($response->json('checkout_url'))->assertOk()->assertSee('Pago seguro con Niubiz')->assertDontSee('SANDBOX-DEMO');

        return DB::table('order_payment_attempts')->where('order_id', $this->orderId)->first();
    }

    private function returnUrl(object $attempt): string
    {
        return URL::temporarySignedRoute('customer.payment.return', now()->addMinutes(10), ['attempt' => $attempt->id]);
    }

    private function fakeAuthorization(object $attempt, array $overrides = [], int $status = 200): void
    {
        Http::fake([
            '*api.security/v1/security' => Http::response(str_repeat('s', 50)),
            '*authorization/ecommerce/*' => Http::response(array_replace_recursive([
                'fulfillment' => ['merchantId' => '123456789'],
                'dataMap' => ['ACTION_CODE' => '000'],
                'order' => ['currency' => 'PEN', 'purchaseNumber' => $attempt->purchase_number, 'authorizedAmount' => 65.30, 'transactionId' => 'TX-TEST-001'],
            ], $overrides), $status),
        ]);
    }

    public function test_dni_uses_configured_provider_and_normalizes_nested_names(): void
    {
        Http::fake(['https://documents.example/v2/reniec/dni*' => Http::response([
            'success' => true, 'data' => ['nombres' => 'ANA MARIA', 'apellidoPaterno' => 'PEREZ', 'apellidoMaterno' => 'SOTO'],
        ])]);
        $this->getJson('/consulta-documento?type=DNI&number=12345678')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.nombres', 'ANA MARIA')->assertJsonPath('data.apellido_paterno', 'PEREZ');
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer document-test-token'));
        Http::assertSentCount(1);
    }

    public function test_invalid_dni_never_calls_provider(): void
    {
        Http::fake();
        $this->getJson('/consulta-documento?type=DNI&number=123')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_document_failure_allows_manual_entry_without_exposing_credentials(): void
    {
        Http::fake(['https://documents.example/*' => Http::response([], 503)]);
        $this->getJson('/consulta-documento?type=DNI&number=12345678')->assertOk()
            ->assertJsonPath('success', false)->assertDontSee('document-test-token');
    }

    public function test_only_owner_can_start_payment_and_client_amount_is_ignored(): void
    {
        $this->fakeSession();
        DB::table('orders')->where('id', $this->orderId)->update(['customer_id' => null]);
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar", ['amount' => 1])->assertNotFound();
        Http::assertNothingSent();
        DB::table('orders')->where('id', $this->orderId)->update(['customer_id' => $this->customerId]);
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar", ['amount' => 1])->assertOk();
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'token/session') && $request['amount'] === 65.3);
        $this->assertDatabaseHas('order_payment_attempts', ['order_id' => $this->orderId, 'amount_cents' => 6530, 'status' => 'ready']);
    }

    public function test_approved_payment_is_recorded_once_even_if_return_is_replayed(): void
    {
        $attempt = $this->startPayment();
        $this->fakeAuthorization($attempt);
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('t', 32)])->assertOk()->assertSee('Pago confirmado');
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('t', 32)])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'payment_status' => 'Pagado', 'payment_preference' => 'Niubiz']);
        $this->assertSame(1, DB::table('payments')->where('order_id', $this->orderId)->count());
        $this->assertDatabaseHas('order_payment_attempts', ['id' => $attempt->id, 'status' => 'paid', 'session_key' => null]);
        $this->assertCount(1, Http::recorded(fn ($request): bool => str_contains($request->url(), 'authorization/ecommerce')));
    }

    public function test_amount_mismatch_keeps_order_unpaid_and_blocks_repeated_charges(): void
    {
        $attempt = $this->startPayment();
        $this->fakeAuthorization($attempt, ['order' => ['authorizedAmount' => 1]]);
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('m', 32)])->assertOk()->assertSee('verificación');
        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'payment_status' => 'Pendiente']);
        $this->assertDatabaseHas('order_payment_attempts', ['id' => $attempt->id, 'status' => 'unknown']);
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertConflict();
        $this->assertSame(0, DB::table('payments')->where('order_id', $this->orderId)->count());
    }

    public function test_declined_payment_remains_unpaid(): void
    {
        $attempt = $this->startPayment();
        $this->fakeAuthorization($attempt, ['dataMap' => ['ACTION_CODE' => '100']], 400);
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('d', 32)])->assertOk()->assertSee('rechazó');
        $this->assertDatabaseHas('order_payment_attempts', ['id' => $attempt->id, 'status' => 'declined']);
        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'payment_status' => 'Pendiente']);
    }

    public function test_forged_or_expired_payment_links_are_rejected(): void
    {
        $attempt = $this->startPayment();
        $this->post("/pagos/niubiz/{$attempt->id}/retorno", ['transactionToken' => str_repeat('f', 32)])->assertForbidden();
        $url = URL::temporarySignedRoute('customer.payment.return', now()->subMinute(), ['attempt' => $attempt->id]);
        $this->post($url, ['transactionToken' => str_repeat('f', 32)])->assertForbidden();
    }

    public function test_ready_payment_can_resume_and_order_cannot_be_cancelled_during_payment(): void
    {
        $attempt = $this->startPayment();
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertOk();
        $this->assertSame(1, DB::table('order_payment_attempts')->where('order_id', $this->orderId)->count());
        $this->postJson("/cuenta/pedidos/{$this->orderId}/cancelar")->assertConflict();
        $this->postJson("/cuenta/pedidos/{$this->orderId}/modificar", ['notes' => 'Cambio'])->assertConflict();
    }

    public function test_session_failure_never_creates_a_fake_payment(): void
    {
        Http::fake(['*api.security/*' => Http::response([], 503)]);
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertServiceUnavailable();
        $this->assertDatabaseHas('order_payment_attempts', ['order_id' => $this->orderId, 'status' => 'failed']);
        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'payment_status' => 'Pendiente']);
    }

    public function test_merchant_mismatch_never_marks_order_paid(): void
    {
        $attempt = $this->startPayment();
        $this->fakeAuthorization($attempt, ['fulfillment' => ['merchantId' => 'another-merchant']]);
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('b', 32)])->assertOk();
        $this->assertDatabaseHas('order_payment_attempts', ['id' => $attempt->id, 'status' => 'unknown']);
        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'payment_status' => 'Pendiente']);
    }

    public function test_uncertain_bank_response_does_not_authorize_again_on_replay(): void
    {
        $attempt = $this->startPayment();
        $this->fakeAuthorization($attempt, [], 503);
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('u', 32)])->assertOk();
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('u', 32)])->assertOk()->assertSee('No vuelvas a pagar');
        $this->assertDatabaseHas('order_payment_attempts', ['id' => $attempt->id, 'status' => 'unknown']);
        $this->assertCount(1, Http::recorded(fn ($request): bool => str_contains($request->url(), 'authorization/ecommerce')));
    }

    public function test_approved_payment_after_cash_closes_preserves_reference_for_reconciliation(): void
    {
        $attempt = $this->startPayment();
        DB::table('cash_sessions')->where('id', $attempt->cash_session_id)->update(['status' => 'closed', 'closed_at' => now()]);
        $this->fakeAuthorization($attempt);
        $this->post($this->returnUrl($attempt), ['transactionToken' => str_repeat('c', 32)])->assertOk()->assertSee('conciliarlo');
        $this->assertDatabaseHas('order_payment_attempts', ['id' => $attempt->id, 'status' => 'review', 'provider_reference' => 'TX-TEST-001']);
        $this->assertSame(0, DB::table('payments')->where('order_id', $this->orderId)->count());
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertConflict();
    }

    public function test_store_checkout_with_niubiz_opens_real_provider_form(): void
    {
        $this->fakeSession();
        $customer = DB::table('customers')->where('id', $this->customerId)->first();
        $productId = DB::table('products')->where('status', 'available')->value('id');
        $response = $this->post('/pedido', [
            'customer_name' => $customer->full_name, 'email' => $customer->email, 'phone' => '987654321',
            'consent' => true, 'order_type' => 'Para llevar', 'payment_preference' => 'Niubiz',
            'checkout_key' => '22222222-3333-4000-8000-000000000099',
            'items' => [['product_id' => $productId, 'quantity' => 1]],
        ]);
        $response->assertRedirect();
        $this->assertStringContainsString('/pagos/niubiz/', $response->headers->get('Location'));
        $this->assertDatabaseHas('orders', ['web_checkout_key' => '22222222-3333-4000-8000-000000000099', 'payment_preference' => 'Niubiz', 'payment_status' => 'Pendiente']);
    }

    public function test_payment_unavailable_without_configuration_or_open_cash(): void
    {
        Http::fake();
        config(['services.niubiz.merchant_id' => null]);
        $this->assertFalse(app(NiubizOrderPayment::class)->available());
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertServiceUnavailable();
        config(['services.niubiz.merchant_id' => '123456789']);
        DB::table('cash_sessions')->update(['status' => 'closed', 'closed_at' => now()]);
        $this->postJson("/cuenta/pedidos/{$this->orderId}/pagar")->assertConflict();
        Http::assertNothingSent();
    }

    public function test_document_lookup_resolves_dni_successfully(): void
    {
        Http::fake([
            'https://api.apis.net.pe/v1/dni?numero=74721904' => Http::response([
                'nombre' => 'AYLEN FLOR INOCENTE SOTO',
                'nombres' => 'AYLEN FLOR',
                'apellidoPaterno' => 'INOCENTE',
                'apellidoMaterno' => 'SOTO',
                'tipoDocumento' => '1',
                'numeroDocumento' => '74721904',
            ]),
        ]);

        $response = $this->getJson('/consulta-documento?type=DNI&number=74721904');
        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame('AYLEN FLOR', $response->json('data.nombres'));
        $this->assertSame('INOCENTE', $response->json('data.apellido_paterno'));
        $this->assertSame('SOTO', $response->json('data.apellido_materno'));
        $this->assertSame('AYLEN FLOR INOCENTE SOTO', $response->json('data.nombre_completo'));
    }

    public function test_document_lookup_resolves_ruc_successfully(): void
    {
        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero=20131312955' => Http::response([
                'nombre' => 'SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT',
                'tipoDocumento' => '6',
                'numeroDocumento' => '20131312955',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'AV. GARCILASO DE LA VEGA NRO 1472',
            ]),
        ]);

        $response = $this->getJson('/consulta-documento?type=RUC&number=20131312955');
        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertStringContainsString('SUNAT', $response->json('data.nombre_o_razon_social'));
    }

    public function test_account_page_never_blocks_with_403_and_creates_customer_profile(): void
    {
        $adminRole = DB::table('roles')->where('code', 'admin')->value('id');
        $newAdmin = User::factory()->create(['role_id' => $adminRole, 'password_hash' => Hash::make('AdminPass123!')]);

        $response = $this->actingAs($newAdmin)
            ->withSession([
                '_token' => 'test-csrf-token',
                'user_id' => $newAdmin->id,
                'auth_password_fingerprint' => hash_hmac('sha256', $newAdmin->password_hash, (string) config('app.key')),
            ])
            ->get('/cuenta');

        $response->assertOk();
        $this->assertDatabaseHas('customers', ['user_id' => $newAdmin->id]);
    }
}
