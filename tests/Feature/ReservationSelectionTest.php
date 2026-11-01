<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReservationAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class ReservationSelectionTest extends TestCase
{
    use CreatesRestaurantDatabase {
        setUp as private setUpRestaurantDatabase;
    }

    protected function setUp(): void
    {
        $this->setUpRestaurantDatabase();
        DB::table('branches')->where('is_main', 1)->update(['opening_time' => '12:00:00', 'closing_time' => '22:00:00']);
        $this->withSession(['_token' => 'reservation-token'])->withHeader('X-CSRF-TOKEN', 'reservation-token');
    }

    private function adminSession(): void
    {
        $user = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $this->withSession(['user_id' => $user->id, 'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key'))]);
    }

    private function payload(array $changes = []): array
    {
        return [...['customer_name' => 'Cliente Reserva', 'phone' => '999111222', 'date' => '2030-01-15', 'time' => '15:00', 'people' => 4, 'table_ids' => [1, 2]], ...$changes];
    }

    public function test_only_future_slots_are_offered_in_peru_and_last_slot_finishes_before_closing(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 15:01:00', 'America/Lima'));
        $result = $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=14:00')->assertOk()->assertJsonPath('today', '2030-01-15')->assertJsonPath('time', null)->assertJsonCount(0, 'tables');
        $this->assertSame('15:30', $result->json('slots.0'));
        $this->assertSame('20:00', collect($result->json('slots'))->last());
        $this->getJson('/reservas/disponibilidad?date=2030-01-14')->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson('/reservas/disponibilidad?date=2030-01-16')->assertOk()->assertJsonPath('slots.0', '12:00');
        $this->travelTo(CarbonImmutable::parse('2030-01-15 20:01:00', 'America/Lima'));
        $this->getJson('/reservas/disponibilidad?date=2030-01-15')->assertOk()->assertJsonCount(0, 'slots');
    }

    public function test_past_dates_times_and_outside_opening_hours_are_rejected_without_writes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:30', 'America/Lima'));
        foreach ([['date' => '2030-01-14'], ['time' => '13:30'], ['time' => '14:00'], ['time' => '11:30'], ['time' => '20:30']] as $changes) {
            $this->postJson('/reservar', $this->payload($changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_multiple_tables_are_one_reservation_and_all_tables_become_unavailable(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->adminSession();
        $this->from('/')->post('/reservar', $this->payload())->assertRedirect('/')->assertSessionHasNoErrors()->assertSessionHas('result.seating', 'Mesas separadas')->assertSessionHas('result.tables', fn ($tables) => count($tables) === 2);
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_tables', 2);
        $this->assertDatabaseHas('reservations', ['reservation_at' => '2030-01-15 20:00:00', 'guest_count' => 4]);
        $availability = $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=15:00')->assertOk()->json('tables');
        $this->assertCount(12, $availability);
        $this->assertSame(4, collect($availability)->where('capacity', 2)->count());
        $this->assertSame(8, collect($availability)->where('capacity', 4)->count());
        foreach ([1, 2] as $id) {
            $this->assertFalse(collect($availability)->firstWhere('id', $id)['available']);
        }
        $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=17:00')->assertOk()->assertJsonPath('tables.0.available', true);
        $this->getJson('/api/reservations')->assertOk()->assertJsonPath('reservations.0.table_label', 'Mesa 01 + Mesa 02')->assertJsonCount(2, 'reservations.0.tables');
    }

    public function test_capacity_duplicates_and_a_busy_secondary_table_are_rejected_atomically(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->adminSession();
        $this->postJson('/api/mobile/reservations', $this->payload(['table_ids' => [2], 'people' => 2]))->assertOk();
        $this->postJson('/reservar', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('table_ids');
        $this->postJson('/reservar', $this->payload(['time' => '17:00', 'people' => 5]))->assertUnprocessable()->assertJsonValidationErrors('table_ids');
        $this->postJson('/reservar', $this->payload(['table_ids' => [1, 1]]))->assertUnprocessable();
        $this->postJson('/reservar', $this->payload(['table_ids' => [1, 99999]]))->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_tables', 1);
    }

    public function test_admin_cannot_reserve_a_secondary_table_that_is_already_part_of_a_group(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->adminSession();
        $this->postJson('/api/mobile/reservations', $this->payload())->assertOk();
        $this->postJson('/api/reservations', ['action' => 'reservation.create', 'table_id' => 2, 'customer_name' => 'Otra reserva', 'guest_count' => 2, 'reservation_at' => '2030-01-15T15:30', 'duration_minutes' => 120])->assertConflict();
        $this->postJson('/api/reservations', ['action' => 'reservation.create', 'table_id' => 3, 'customer_name' => 'Hora pasada', 'guest_count' => 2, 'reservation_at' => '2030-01-15T13:59', 'duration_minutes' => 120])->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_join_groups_are_configured_by_staff_and_publicly_explain_seating(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->postJson('/api/reservations', ['action' => 'table.group', 'table_id' => 1, 'join_group' => 'Ventana'])->assertUnauthorized();
        $this->adminSession();
        foreach ([1, 2] as $id) {
            $this->post('/admin/acciones/reservations', ['action' => 'table.group', 'table_id' => $id, 'join_group' => 'Ventana'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->postJson('/api/mobile/reservations', $this->payload())->assertOk()->assertJsonPath('seating', 'Mesas que se pueden unir');
        $this->getJson('/reservas/disponibilidad?date=2030-01-16&time=15:00')->assertOk()->assertJsonPath('tables.0.join_group', 'Ventana');
    }

    public function test_cancellation_releases_every_table_and_customer_account_lists_the_assignment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->postJson('/api/mobile/register', ['name' => 'Cliente Reserva', 'email' => 'reserve@example.test', 'password' => 'Customer12345', 'password_confirmation' => 'Customer12345'])->assertCreated();
        $result = $this->postJson('/api/mobile/reservations', $this->payload())->assertOk();
        $this->getJson('/api/mobile/account')->assertOk()->assertJsonCount(2, 'reservations.0.tables')->assertJsonPath('reservations.0.seating', 'Mesas separadas');
        $this->postJson('/api/mobile/reservations/'.$result->json('id').'/cancel')->assertOk();
        $availability = $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=15:00')->assertOk()->json('tables');
        $this->assertTrue(collect($availability)->firstWhere('id', 1)['available']);
        $this->assertTrue(collect($availability)->firstWhere('id', 2)['available']);
    }

    public function test_unavailable_tables_and_legacy_single_table_reservations_are_respected(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->adminSession();
        DB::table('restaurant_tables')->where('id', 1)->update(['operational_status' => 'maintenance']);
        $this->postJson('/reservar', $this->payload())->assertUnprocessable();
        DB::table('restaurant_tables')->where('id', 1)->update(['operational_status' => 'occupied']);
        $this->postJson('/reservar', $this->payload())->assertUnprocessable();
        DB::table('restaurant_tables')->where('id', 1)->update(['operational_status' => 'available']);
        $id = $this->postJson('/api/mobile/reservations', $this->payload())->assertOk()->json('id');
        DB::table('reservation_tables')->where('reservation_id', $id)->delete();
        $this->assertTrue(app(ReservationAvailability::class)->overlaps(1, CarbonImmutable::parse('2030-01-15 20:00:00', 'UTC')));
        $this->postJson('/reservar', $this->payload(['table_ids' => [1], 'people' => 2]))->assertUnprocessable();
    }

    public function test_peru_day_is_used_even_when_utc_is_already_tomorrow(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-16 01:00:00', 'UTC'));
        $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=20:00')->assertOk()->assertJsonPath('today', '2030-01-15')->assertJsonPath('slots.0', '20:00');
        $this->travelTo(CarbonImmutable::parse('2030-01-16 05:00:00', 'UTC'));
        $this->getJson('/reservas/disponibilidad?date=2030-01-15')->assertUnprocessable();
    }

    public function test_staff_can_create_and_reschedule_a_group_without_losing_secondary_tables(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:00:00', 'America/Lima'));
        $this->adminSession();
        $payload = ['action' => 'reservation.create', 'table_id' => 1, 'table_ids' => [1, 2], 'customer_name' => 'Grupo del panel', 'guest_count' => 4, 'reservation_at' => '2030-01-15T15:00', 'duration_minutes' => 120];
        $id = $this->postJson('/api/reservations', $payload)->assertOk()->json('id');
        $this->assertDatabaseCount('reservation_tables', 2);
        unset($payload['table_ids']);
        $this->postJson('/api/reservations', [...$payload, 'action' => 'reservation.update', 'id' => $id, 'reservation_at' => '2030-01-15T17:00'])->assertOk();
        $this->assertDatabaseCount('reservation_tables', 2);
        $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=15:00')->assertOk()->assertJsonPath('tables.1.available', true);
        $this->getJson('/reservas/disponibilidad?date=2030-01-15&time=17:00')->assertOk()->assertJsonPath('tables.1.available', false);
        $this->postJson('/api/reservations', ['action' => 'table.update', 'id' => 2, 'table_number' => 2, 'label' => 'Mesa 02', 'zone' => 'Salón principal', 'capacity' => 2, 'is_active' => 1])->assertOk();
        $this->postJson('/api/reservations', ['action' => 'table.update', 'id' => 2, 'table_number' => 2, 'label' => 'Mesa 02', 'zone' => 'Salón principal', 'capacity' => 1, 'is_active' => 1])->assertConflict();
        $this->postJson('/api/reservations', ['action' => 'table.update', 'id' => 2, 'table_number' => 2, 'label' => 'Mesa 02', 'zone' => 'Salón principal', 'capacity' => 4, 'is_active' => 0])->assertConflict();
    }

    public function test_unconfigured_business_hours_do_not_prevent_future_reservations(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-15 14:01:00', 'America/Lima'));
        DB::table('branches')->where('is_main', 1)->update(['opening_time' => null, 'closing_time' => null]);
        $this->getJson('/reservas/disponibilidad?date=2030-01-15')->assertOk()->assertJsonPath('slots.0', '14:30');
        $this->getJson('/reservas/disponibilidad?date=2030-01-16')->assertOk()->assertJsonPath('slots.0', '00:00');
    }
}
