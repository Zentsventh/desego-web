<?php

namespace Tests\Feature;

use App\Services\ReservationAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class ReservationNiubizPaymentTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    private string $reservationDate;

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        $this->withSession(['_token' => 'reservation-csrf'])->withHeader('X-CSRF-TOKEN', 'reservation-csrf');
        $this->reservationDate = CarbonImmutable::now('America/Lima')->addDay()->toDateString();
        DB::table('branches')->where('is_main', true)->update([
            'opening_time' => '12:00:00',
            'closing_time' => '23:00:00',
        ]);
        config([
            'dining.niubiz.user' => 'sandbox-user',
            'dining.niubiz.password' => 'sandbox-password',
            'dining.niubiz.merchant_id' => '456879856',
            'dining.niubiz.environment' => 'sandbox',
        ]);
        Http::preventStrayRequests();
    }

    private function createHold(): string
    {
        return $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Ana Prueba',
            'phone' => '999888111',
            'email' => 'ana@example.test',
            'date' => $this->reservationDate,
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk()->json('hold_token');
    }

    private function fakeCheckoutSession(): void
    {
        Http::fake([
            '*api.security/v1/security' => Http::response(str_repeat('s', 50), 201),
            '*token/session/*' => Http::response(['sessionKey' => 'reservation-session-test']),
        ]);
    }

    private function startPayment(string $holdToken): object
    {
        $this->fakeCheckoutSession();
        $response = $this->postJson('/api/reservas/pagos/niubiz', ['hold_token' => $holdToken])
            ->assertOk()
            ->assertJsonPath('sandbox', true);

        $attempt = DB::table('reservation_payment_attempts')->first();
        $this->assertSame('ready', $attempt->status);
        $this->assertTrue(now()->lessThan($attempt->expires_at), 'now='.now().' attempt='.$attempt->expires_at);

        $checkoutUrl = $response->json('checkout_url');
        $checkoutResponse = $this->get($checkoutUrl);
        $this->assertSame(200, $checkoutResponse->status(), $checkoutResponse->getContent());
        $checkoutResponse
            ->assertSee('PAGO SEGURO CON NIUBIZ')
            ->assertSee('456879856')
            ->assertSee('datos de prueba a Niubiz')
            ->assertDontSee('4557 8800 0000 0000')
            ->assertDontSee('SANDBOX-DEMO');

        return $attempt;
    }

    private function callbackUrl(object $attempt): string
    {
        return URL::temporarySignedRoute(
            'reservation.payment.return',
            CarbonImmutable::parse($attempt->expires_at)->addMinutes(2),
            ['attempt' => $attempt->id],
            false
        );
    }

    private function fakeAuthorization(
        object $attempt,
        array $response = [],
        int $status = 200,
        int $confirmationStatus = 200,
        ?array $confirmationResponse = null
    ): void {
        Http::fake([
            '*api.security/v1/security' => Http::response(str_repeat('s', 50), 201),
            '*authorization/ecommerce/*' => Http::response(array_replace_recursive([
                'fulfillment' => ['merchantId' => '456879856'],
                'dataMap' => ['ACTION_CODE' => '000'],
                'order' => [
                    'currency' => 'PEN',
                    'purchaseNumber' => $attempt->purchase_number,
                    'authorizedAmount' => 10.00,
                    'transactionId' => '123456789012345',
                ],
            ], $response), $status),
            '*api.confirmation/v1/confirmation/ecommerce/*' => Http::response($confirmationResponse ?? [
                'header' => ['ecoreTransactionUUID' => 'CAPTURE-RESERVATION-001'],
                'order' => [
                    'actionCode' => '000',
                    'purchaseNumber' => $attempt->purchase_number,
                    'currency' => 'PEN',
                    'authorizedAmount' => 10.00,
                ],
            ], $confirmationStatus),
        ]);
    }

    public function test_reservation_stays_pending_until_niubiz_authorizes_the_transaction(): void
    {
        $holdToken = $this->createHold();
        $attempt = $this->startPayment($holdToken);

        $this->assertDatabaseMissing('reservations', ['hold_token' => $holdToken]);
        $this->assertDatabaseMissing('reservation_payments', ['external_reference' => '123456789012345']);

        $this->fakeAuthorization($attempt);
        $this->withHeader('X-CSRF-TOKEN', 'cross-site-callback');
        $this->post($this->callbackUrl($attempt), [
            'transactionCode' => str_repeat('t', 32),
        ])->assertOk()->assertSee('¡Reserva confirmada!');

        $this->assertDatabaseHas('reservation_payment_attempts', [
            'id' => $attempt->id,
            'status' => 'paid',
            'provider_reference' => '123456789012345',
        ]);
        $this->assertDatabaseHas('reservations', [
            'hold_token' => $holdToken,
            'payment_status' => 'PAID',
            'status' => 'CONFIRMED',
        ]);
        $this->assertDatabaseHas('reservation_payments', [
            'external_reference' => '123456789012345',
            'status' => 'PAID',
            'amount' => 10.00,
        ]);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api.confirmation/v1/confirmation/ecommerce/456879856')
            && $request['channel'] === 'web'
            && $request['captureType'] === 'manual'
            && $request['order']['purchaseNumber'] === $attempt->purchase_number
            && $request['order']['transactionId'] === '123456789012345');
    }

    public function test_declined_or_mismatched_niubiz_responses_never_confirm_a_reservation(): void
    {
        $holdToken = $this->createHold();
        $attempt = $this->startPayment($holdToken);
        $this->fakeAuthorization($attempt, [
            'dataMap' => ['ACTION_CODE' => '101'],
        ]);

        $this->post($this->callbackUrl($attempt), [
            'transactionToken' => str_repeat('d', 32),
        ])->assertOk()->assertSee('No pudimos confirmar el pago');

        $this->assertDatabaseHas('reservation_payment_attempts', [
            'id' => $attempt->id,
            'status' => 'declined',
        ]);
        $this->assertDatabaseMissing('reservations', ['hold_token' => $holdToken]);
        $this->assertDatabaseMissing('reservation_payments', ['status' => 'PAID']);
    }

    public function test_reservation_stays_pending_when_manual_capture_is_not_confirmed(): void
    {
        $holdToken = $this->createHold();
        $attempt = $this->startPayment($holdToken);
        $this->fakeAuthorization($attempt, confirmationStatus: 500);

        $this->post($this->callbackUrl($attempt), [
            'transactionToken' => str_repeat('t', 32),
        ])->assertOk()->assertSee('No pudimos confirmar el pago');

        $this->assertDatabaseHas('reservation_payment_attempts', [
            'id' => $attempt->id,
            'status' => 'unknown',
            'provider_reference' => '123456789012345',
        ]);
        $this->assertDatabaseMissing('reservations', ['hold_token' => $holdToken]);
        $this->assertDatabaseMissing('reservation_payments', ['external_reference' => '123456789012345']);
        $this->postJson('/api/reservas/pagos/niubiz', ['hold_token' => $holdToken])
            ->assertConflict();
    }

    public function test_public_fake_payment_confirmation_endpoint_is_not_registered(): void
    {
        $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => 'HOLD-fake',
            'provider' => 'niubiz',
            'external_reference' => 'FAKE-REFERENCE',
        ])->assertMethodNotAllowed();

        $this->assertSame(0, DB::table('reservations')->count());
    }

    public function test_unconfigured_niubiz_does_not_create_a_payment_attempt(): void
    {
        config([
            'dining.niubiz.user' => null,
            'dining.niubiz.password' => null,
            'dining.niubiz.merchant_id' => null,
        ]);
        $holdToken = $this->createHold();

        $this->postJson('/api/reservas/pagos/niubiz', ['hold_token' => $holdToken])
            ->assertServiceUnavailable();

        $this->assertDatabaseCount('reservation_payment_attempts', 0);
    }

    public function test_free_reservation_can_be_confirmed_without_a_fictitious_payment_record(): void
    {
        app(ReservationAvailabilityService::class)->config();
        DB::table('reservation_configurations')->update(['advance_amount' => 0]);
        $holdToken = $this->createHold();

        $this->postJson('/api/reservas/pagos/sin-anticipo', ['hold_token' => $holdToken])
            ->assertOk()
            ->assertJsonPath('status', 'CONFIRMED');

        $this->assertDatabaseHas('reservations', [
            'hold_token' => $holdToken,
            'payment_status' => 'PAID',
            'advance_amount' => 0,
        ]);
        $this->assertDatabaseCount('reservation_payments', 0);
    }
}
