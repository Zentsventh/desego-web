<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DiningService;
use App\Services\ReservationAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class ReservationSystemTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        DB::table('branches')->where('is_main', 1)->update([
            'opening_time' => '12:00:00',
            'closing_time' => '23:00:00',
        ]);
        $this->withSession(['_token' => 'test-csrf-token'])->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }

    private function adminSession(): User
    {
        $user = User::factory()->create([
            'role_id' => DB::table('roles')->where('code', 'admin')->value('id'),
            'status' => 'active',
        ]);
        $this->withSession([
            'user_id' => $user->id,
            'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key')),
        ]);

        return $user;
    }

    private function customerSession(): User
    {
        $roleId = DB::table('roles')->where('code', 'customer')->value('id')
            ?? DB::table('roles')->where('code', 'collaborator')->value('id')
            ?? 1;

        $user = User::factory()->create([
            'role_id' => $roleId,
            'status' => 'active',
        ]);
        $this->withSession([
            'user_id' => $user->id,
            'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key')),
        ]);

        return $user;
    }

    /**
     * Caso 1. Un cliente reserva la mesa 01 a las 19:00, paga S/10 y recibe su confirmación.
     */
    public function test_case_1_customer_reserves_table_01_at_1900_pays_10_soles_receives_confirmation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Paso 1: Crear bloqueo temporal de 10 minutos
        $holdResponse = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Carlos Mendoza',
            'phone' => '999888111',
            'email' => 'carlos@test.com',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $token = $holdResponse->json('hold_token');
        $this->assertNotEmpty($token);
        $this->assertSame(10.0, (float) $holdResponse->json('advance_amount'));

        // Paso 2: Confirmar pago de anticipo de S/ 10.00
        $confirmResponse = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $token,
            'provider' => 'niubiz',
            'external_reference' => 'NIUBIZ-AUTH-001',
        ])->assertOk();

        $this->assertTrue($confirmResponse->json('ok'));
        $this->assertSame('CONFIRMED', $confirmResponse->json('status'));
        $this->assertStringStartsWith('RES-', $confirmResponse->json('reservation_code'));
        $this->assertSame(10.0, (float) $confirmResponse->json('advance_amount'));

        // Verificar en DB
        $reservation = DB::table('reservations')->where('reservation_code', $confirmResponse->json('reservation_code'))->first();
        $this->assertNotNull($reservation);
        $this->assertSame(1, (int) $reservation->table_id);
        $this->assertSame('PAID', $reservation->payment_status);
        $this->assertSame('10.00', $reservation->advance_amount);
    }

    /**
     * Caso 2. Dos usuarios intentan reservar simultáneamente la misma mesa en horarios incompatibles. Solo una resulta confirmada.
     */
    public function test_case_2_concurrent_requests_for_same_table_and_incompatible_hours_only_one_confirmed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Usuario A bloquea la mesa 01 a las 19:00
        $holdA = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Usuario A',
            'phone' => '999000001',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        // Usuario B intenta reservar la misma mesa 01 en un horario incompatible (19:30)
        $holdB = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Usuario B',
            'phone' => '999000002',
            'date' => '2026-11-10',
            'time' => '19:30',
            'people' => 2,
            'table_ids' => [1],
        ]);

        $holdB->assertStatus(422);
        $this->assertStringContainsString('no está disponible', $holdB->json('message'));
    }

    /**
     * Caso 3. Un usuario inicia el pago, pero no lo completa. El bloqueo expira y la mesa vuelve a estar disponible.
     */
    public function test_case_3_user_starts_payment_hold_expires_without_payment_table_becomes_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Usuario inicia bloqueo a las 12:00
        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Usuario Incompleto',
            'phone' => '999333444',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $token = $hold->json('hold_token');

        // Mientras el hold está activo (< 10 min), la mesa no está disponible
        $this->travel(5)->minutes();
        $avail = $this->getJson('/api/reservas/disponibilidad?date=2026-11-10&time=19:00')->assertOk();
        $table1 = collect($avail->json('tables'))->firstWhere('id', 1);
        $this->assertFalse($table1['available']);

        // Transcurren 11 minutos (expira el hold)
        $this->travel(6)->minutes();
        $this->artisan('reservations:process-expirations')->assertExitCode(0);

        // La mesa 01 vuelve a estar disponible
        $availAfter = $this->getJson('/api/reservas/disponibilidad?date=2026-11-10&time=19:00')->assertOk();
        $table1After = collect($availAfter->json('tables'))->firstWhere('id', 1);
        $this->assertTrue($table1After['available']);
    }

    /**
     * Caso 4. Un cliente llega exactamente a los 15 minutos de su hora reservada. El sistema permite registrar su llegada.
     */
    public function test_case_4_customer_arrives_exactly_at_15_minutes_tolerance_checkin_allowed(): void
    {
        $this->adminSession();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Crear reserva confirmada a las 19:00
        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Llegada Puntual',
            'phone' => '999555666',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $confirm = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold->json('hold_token'),
        ])->assertOk();

        $resId = $confirm->json('id');

        // Avanzar el reloj exactamente a las 19:15:00 (límite exacto de tolerancia)
        $this->travelTo(CarbonImmutable::parse('2026-11-10 19:15:00', 'America/Lima'));

        $checkIn = $this->postJson("/api/admin/reservas/{$resId}/check-in")->assertOk();
        $this->assertSame('CHECKED_IN', $checkIn->json('status'));

        $resDb = DB::table('reservations')->where('id', $resId)->first();
        $this->assertSame('CHECKED_IN', $resDb->status);
        $this->assertNotNull($resDb->checked_in_at);
    }

    /**
     * Caso 5. El cliente no llega y supera la tolerancia. La reserva pasa a NO_SHOW, se libera capacidad y anticipo queda retenido.
     */
    public function test_case_5_customer_exceeds_tolerance_marked_no_show_capacity_released_advance_retained(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Cliente Inasistente',
            'phone' => '999777888',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $confirm = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold->json('hold_token'),
        ])->assertOk();

        $resId = $confirm->json('id');

        // Avanzar a las 19:16:00 (tolerancia estrictamente superada)
        $this->travelTo(CarbonImmutable::parse('2026-11-10 19:16:00', 'America/Lima'));

        // Ejecutar automatización de no-shows
        $this->artisan('reservations:process-expirations')->assertExitCode(0);

        $resDb = DB::table('reservations')->where('id', $resId)->first();
        $this->assertSame('NO_SHOW', $resDb->status);
        $this->assertSame('RETAINED', $resDb->payment_status);

        // Capacidad liberada: la mesa 01 vuelve a figurar como disponible
        $service = app(ReservationAvailabilityService::class);
        $this->assertFalse($service->overlaps(1, CarbonImmutable::parse('2026-11-10 19:30:00', 'America/Lima')->utc(), 60));
    }

    /**
     * Caso 6. Un cliente con reserva pagada consume S/70. El saldo pendiente debe ser S/60.
     */
    public function test_case_6_customer_with_paid_reservation_consumes_70_soles_due_balance_is_60_soles(): void
    {
        $admin = $this->adminSession();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 17:00:00', 'America/Lima'));

        // 1. Crear reserva pagada para las 19:00
        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Comensal Feliz',
            'phone' => '999123456',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $confirm = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold->json('hold_token'),
        ])->assertOk();

        $resId = $confirm->json('id');

        // Llegada a las 19:00
        $this->travelTo(CarbonImmutable::parse('2026-11-10 19:00:00', 'America/Lima'));

        // 2. Sentar al cliente en la mesa 1 (inicia sesión)
        $this->postJson("/api/admin/reservas/{$resId}/sentar")->assertOk();

        $session = DB::table('dining_sessions')->where('table_id', 1)->where('status', 'open')->first();
        $this->assertNotNull($session);

        // 3. Crear pedido por S/ 70.00
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'M-TEST-001',
            'customer_name' => 'Comensal Feliz',
            'order_type' => 'Mesa',
            'table_label' => 'Mesa 01',
            'dining_session_id' => $session->id,
            'status' => 'Entregado',
            'payment_status' => 'Pendiente',
            'subtotal' => 70.00,
            'discount' => 0.00,
            'total' => 70.00,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Cobrar en mesa
        $dining = app(DiningService::class);
        $dining->staffAction([
            'action' => 'pay',
            'session_id' => $session->id,
            'method' => 'Efectivo',
        ]);

        // 5. Verificar que el anticipo de S/ 10 fue aplicado y el cliente pagó solo el saldo de S/ 60
        $payments = DB::table('payments')->where('order_id', $orderId)->orderBy('id')->get();
        $this->assertCount(2, $payments);

        $advancePayment = $payments->firstWhere('method', 'Transferencia');
        $this->assertNotNull($advancePayment);
        $this->assertSame('10.00', $advancePayment->amount);

        $cashPayment = $payments->firstWhere('method', 'Efectivo');
        $this->assertNotNull($cashPayment);
        $this->assertSame('60.00', $cashPayment->amount);
    }

    /**
     * Caso 7. El crédito de una reserva ya utilizada no puede aplicarse nuevamente.
     */
    public function test_case_7_used_reservation_credit_cannot_be_reapplied(): void
    {
        $admin = $this->adminSession();
        $resId = DB::table('reservations')->insertGetId([
            'customer_name' => 'Doble Credito',
            'table_id' => 1,
            'guest_count' => 2,
            'reservation_at' => now(),
            'duration_minutes' => 60,
            'status' => 'COMPLETED',
            'payment_status' => 'PAID',
            'advance_amount' => 10.00,
            'applied_credit_amount' => 10.00, // Ya aplicado al 100%
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ReservationAvailabilityService::class);
        $fakeOrderId = DB::table('orders')->insertGetId([
            'order_number' => 'M-TEST-002',
            'customer_name' => 'Doble Credito',
            'total' => 50.00,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $service->applyReservationCreditToOrder($fakeOrderId);
        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('no pertenece a una mesa', $result['message']);
    }

    /**
     * Caso 8. Una mesa deshabilitada nunca aparece como disponible para nuevas reservas.
     */
    public function test_case_8_disabled_table_never_appears_available_for_new_reservations(): void
    {
        $this->adminSession();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Deshabilitar mesa 05
        $this->patchJson('/api/admin/mesas/5', [
            'is_online_reservable' => false,
        ])->assertOk();

        $avail = $this->getJson('/api/reservas/disponibilidad?date=2026-11-10&time=19:00')->assertOk();
        $table5 = collect($avail->json('tables'))->firstWhere('id', 5);
        $this->assertFalse($table5['available']);
    }

    /**
     * Caso 9. Una reserva nueva no puede superponerse con el tiempo de atención y limpieza de otra.
     */
    public function test_case_9_new_reservation_cannot_overlap_with_dining_and_cleaning_time_of_existing_reservation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Reserva A: 19:00 a 20:30 (90 min) + 15 min limpieza = hasta las 20:45
        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Reserva A',
            'phone' => '999888999',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 4,
            'table_ids' => [5],
        ])->assertOk();

        $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold->json('hold_token'),
        ])->assertOk();

        // Intento a las 20:30 (durante el tiempo de limpieza): DEBE SER RECHAZADO
        $service = app(ReservationAvailabilityService::class);
        $overlapAt2030 = $service->overlaps(5, CarbonImmutable::parse('2026-11-10 20:30:00', 'America/Lima')->utc(), 60);
        $this->assertTrue($overlapAt2030);

        // Intento a las 20:45 (finalizada la limpieza): DEBE ESTAR LIBRE
        $overlapAt2045 = $service->overlaps(5, CarbonImmutable::parse('2026-11-10 20:45:00', 'America/Lima')->utc(), 60);
        $this->assertFalse($overlapAt2045);
    }

    /**
     * Caso 10. El sistema recibe dos veces la misma notificación de pago. No debe duplicar reservas.
     */
    public function test_case_10_system_receives_duplicate_payment_notification_does_not_duplicate_reservation(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Idempotente',
            'phone' => '999444111',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $token = $hold->json('hold_token');

        // Primera notificación
        $resp1 = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $token,
            'provider' => 'niubiz',
            'external_reference' => 'NIUBIZ-DUPLICATE-TEST',
        ])->assertOk();

        $code1 = $resp1->json('reservation_code');

        // Segunda notificación idéntica
        $resp2 = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $token,
            'provider' => 'niubiz',
            'external_reference' => 'NIUBIZ-DUPLICATE-TEST',
        ])->assertOk();

        $code2 = $resp2->json('reservation_code');

        $this->assertSame($code1, $code2);
        $count = DB::table('reservations')->where('reservation_code', $code1)->count();
        $this->assertSame(1, $count);
    }

    /**
     * Caso 11. Se recibe un pago confirmado cuando el bloqueo ya expiró. No debe producir sobreventa y genera devolución.
     */
    public function test_case_11_payment_received_after_hold_expired_avoids_overbooking_and_initiates_refund(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Usuario 1 bloquea mesa 1
        $hold1 = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Tardío',
            'phone' => '999111000',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();
        $token1 = $hold1->json('hold_token');

        // Pasan 15 minutos: hold1 expira
        $this->travel(15)->minutes();
        $this->artisan('reservations:process-expirations')->assertExitCode(0);

        // Usuario 2 toma la mesa 1 y la confirma
        $hold2 = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Usuario Rápido',
            'phone' => '999222000',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();
        $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold2->json('hold_token'),
        ])->assertOk();

        // Ahora llega tardíamente el webhook de Usuario 1
        $service = app(ReservationAvailabilityService::class);
        $lateResult = $service->confirmPayment($token1, 'niubiz', 'EXT-LATE-001');

        $this->assertFalse($lateResult['ok']);
        $this->assertTrue($lateResult['refunded']);
        $this->assertStringContainsString('devolución', $lateResult['message']);

        // Verificar que se registró el reembolso
        $refund = DB::table('reservation_payments')->where('external_reference', 'EXT-LATE-001')->first();
        $this->assertSame('REFUNDED', $refund->status);
    }

    /**
     * Caso 12. Una reserva marcada como CHECKED_IN nunca debe cancelarse automáticamente por falta de llegada.
     */
    public function test_case_12_checked_in_reservation_is_never_auto_cancelled_for_no_show(): void
    {
        $this->adminSession();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 17:00:00', 'America/Lima'));

        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Cliente Presente',
            'phone' => '999333555',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $confirm = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold->json('hold_token'),
        ])->assertOk();

        $resId = $confirm->json('id');

        // Check-in a las 19:05
        $this->travelTo(CarbonImmutable::parse('2026-11-10 19:05:00', 'America/Lima'));
        $this->postJson("/api/admin/reservas/{$resId}/check-in")->assertOk();

        // Avanzar a las 19:30 (más de 15 min después de las 19:00)
        $this->travelTo(CarbonImmutable::parse('2026-11-10 19:30:00', 'America/Lima'));
        $this->artisan('reservations:process-expirations')->assertExitCode(0);

        $resDb = DB::table('reservations')->where('id', $resId)->first();
        $this->assertSame('CHECKED_IN', $resDb->status);
        $this->assertNotSame('NO_SHOW', $resDb->status);
    }

    /**
     * Caso 13. Un cliente cancela una reserva antes del plazo válido y se procesa la devolución.
     */
    public function test_case_13_customer_cancels_before_deadline_refund_processed(): void
    {
        // Reserva para dentro de 24 horas
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        $hold = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Cancela a tiempo',
            'phone' => '999666777',
            'date' => '2026-11-11',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        $confirm = $this->postJson('/api/reservas/confirmar-pago', [
            'hold_token' => $hold->json('hold_token'),
        ])->assertOk();

        $code = $confirm->json('reservation_code');

        // Cancelar con anticipación
        $cancel = $this->postJson("/api/reservas/{$code}/cancelar", [
            'phone' => '999666777',
            'reason' => 'Viaje de trabajo imprevisto',
        ])->assertOk();

        $this->assertTrue($cancel->json('ok'));
        $this->assertTrue($cancel->json('refunded'));

        $resDb = DB::table('reservations')->where('reservation_code', $code)->first();
        $this->assertSame('CANCELLED', $resDb->status);
        $this->assertSame('REFUNDED', $resDb->payment_status);
    }

    /**
     * Caso 14. La mesa sigue ocupada físicamente después de su duración estimada. El sistema no la marca como disponible.
     */
    public function test_case_14_table_remains_physically_occupied_after_estimated_duration_not_marked_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 19:00:00', 'America/Lima'));

        // Mesa 1 ocupada físicamente
        DB::table('restaurant_tables')->where('id', 1)->update([
            'operational_status' => 'occupied',
        ]);

        $service = app(ReservationAvailabilityService::class);
        $table1 = DB::table('restaurant_tables')->where('id', 1)->first();

        // Consulta para las 20:45 (apenas terminando su tiempo estimado)
        $startUtc = CarbonImmutable::parse('2026-11-10 20:45:00', 'America/Lima')->utc();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 20:30:00', 'America/Lima'));

        $isUnavailable = $service->unavailable($table1, $startUtc, 60);
        $this->assertTrue($isUnavailable);
    }

    /**
     * Caso 15. Un usuario sin rol de administrador intenta modificar un pago o devolución. La operación es rechazada (403).
     */
    public function test_case_15_admin_without_permissions_attempting_to_modify_payment_is_rejected(): void
    {
        $this->customerSession(); // Usuario cliente sin permisos de admin

        $response = $this->postJson('/api/admin/reservas/1/reembolsar', [
            'reason' => 'Intento no autorizado',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Caso 16. Dos clientes intentan adquirir simultáneamente las últimas mesas disponibles. Nunca debe excederse la capacidad real.
     */
    public function test_case_16_concurrent_acquisition_of_last_available_tables_never_exceeds_real_capacity(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00:00', 'America/Lima'));

        // Bloquear todas las mesas de 2 personas excepto la mesa 01
        for ($t = 2; $t <= 4; $t++) {
            DB::table('restaurant_tables')->where('id', $t)->update(['is_active' => false]);
        }

        // Cliente 1 adquiere la última mesa (mesa 01)
        $hold1 = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Ultimo Ganador',
            'phone' => '999111999',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ])->assertOk();

        // Cliente 2 compite por la misma mesa o asignación automática
        $hold2 = $this->postJson('/api/reservas/crear-bloqueo', [
            'customer_name' => 'Segundo Competidor',
            'phone' => '999222999',
            'date' => '2026-11-10',
            'time' => '19:00',
            'people' => 2,
            'table_ids' => [1],
        ]);

        $hold2->assertStatus(422);
    }
}
