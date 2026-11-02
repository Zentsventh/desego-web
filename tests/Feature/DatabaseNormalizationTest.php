<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyDesego;
use App\Models\User;
use App\Support\Desego\OrderItemOptions;
use App\Support\Desego\SqlDump;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RestaurantReferenceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

use function App\Support\Desego\desego_backup_dump;

class DatabaseNormalizationTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function order(array $attributes = []): int
    {
        $user = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);

        return DB::table('orders')->insertGetId(array_merge([
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function hold(array $attributes = []): int
    {
        return DB::table('reservation_holds')->insertGetId(array_merge([
            'hold_token' => Str::random(32), 'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999111222', 'guest_count' => 2,
            'reservation_at' => now()->addDay(), 'expires_at' => now()->addMinutes(10),
        ], $attributes));
    }

    public function test_legacy_json_is_backfilled_and_can_be_restored_without_losing_snapshots(): void
    {
        $migration = require database_path('migrations/2026_11_01_194719_normalize_reservation_and_dining_relations.php');
        $migration->down();
        $holdId = $this->hold(['table_ids' => json_encode([1, '2'])]);
        $sessionId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        DB::table('dining_sessions')->insert(['id' => $sessionId, 'table_id' => 1, 'join_code_hash' => 'hash', 'join_code_encrypted' => 'encrypted']);
        DB::table('dining_members')->insert(['id' => $memberId, 'dining_session_id' => $sessionId, 'name' => 'Ana', 'token_hash' => hash('sha256', 'legacy-token')]);
        $orderId = $this->order(['dining_session_id' => $sessionId, 'dining_member_id' => $memberId]);
        $actorId = DB::table('orders')->where('id', $orderId)->value('created_by');
        $cashId = DB::table('cash_sessions')->insertGetId(['opened_by' => $actorId, 'opened_at' => now()]);
        $attemptId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(10)->format('Y-m-d H:i:s');
        DB::table('dining_payment_attempts')->insert([
            'id' => $attemptId, 'dining_session_id' => $sessionId, 'dining_member_id' => $memberId,
            'purchase_number' => '123456789012', 'amount_cents' => 2550,
            'order_ids' => json_encode([$orderId]), 'cash_session_id' => $cashId,
            'actor_id' => $actorId, 'expires_at' => $expiresAt,
        ]);
        $groupId = DB::table('product_option_groups')->insertGetId(['product_id' => 1, 'name' => 'Guarnición']);
        $optionId = DB::table('product_options')->insertGetId(['group_id' => $groupId, 'name' => 'Nombre actual', 'price_delta' => 8]);
        $options = [['id' => $optionId, 'group_name' => 'Guarnición original', 'name' => 'Nombre vendido', 'price_delta' => 3.50]];
        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId, 'product_id' => 1, 'product_name' => 'Plato vendido',
            'unit_price' => 25.50, 'quantity' => 1, 'line_total' => 25.50,
            'selected_options' => json_encode($options),
        ]);
        $migration->up();
        $this->assertFalse(Schema::hasColumn('reservation_holds', 'table_ids'));
        $this->assertFalse(Schema::hasColumn('order_items', 'selected_options'));
        $this->assertDatabaseHas('reservation_hold_tables', ['hold_id' => $holdId, 'table_id' => 1]);
        $this->assertDatabaseHas('reservation_hold_tables', ['hold_id' => $holdId, 'table_id' => 2]);
        $this->assertDatabaseHas('dining_payment_attempt_orders', ['attempt_id' => $attemptId, 'order_id' => $orderId]);
        DB::table('dining_payment_attempts')->where('id', $attemptId)->update(['status' => 'ready']);
        $this->assertSame($expiresAt, DB::table('dining_payment_attempts')->where('id', $attemptId)->value('expires_at'));
        $this->assertSame($options, OrderItemOptions::forItem($itemId));
        DB::table('product_options')->where('id', $optionId)->delete();
        $this->assertSame('Nombre vendido', OrderItemOptions::forItem($itemId)[0]['name']);
        $this->assertNull(OrderItemOptions::forItem($itemId)[0]['id']);
        $migration->down();
        $this->assertSame([1, 2], json_decode(DB::table('reservation_holds')->where('id', $holdId)->value('table_ids'), true));
        $this->assertSame([$orderId], json_decode(DB::table('dining_payment_attempts')->where('id', $attemptId)->value('order_ids'), true));
        $snapshot = json_decode(DB::table('order_items')->where('id', $itemId)->value('selected_options'), true);
        $this->assertSame('Nombre vendido', $snapshot[0]['name']);
        $migration->up();
    }

    public function test_invalid_legacy_relations_stop_before_creating_or_dropping_tables(): void
    {
        $migration = require database_path('migrations/2026_11_01_194719_normalize_reservation_and_dining_relations.php');
        $migration->down();
        $holdId = $this->hold(['table_ids' => '[99999999]']);
        try {
            $migration->up();
            $this->fail('La migración debe rechazar referencias inexistentes.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('mesas inexistentes', $exception->getMessage());
            $this->assertTrue(Schema::hasColumn('reservation_holds', 'table_ids'));
            $this->assertFalse(Schema::hasTable('reservation_hold_tables'));
        } finally {
            DB::table('reservation_holds')->where('id', $holdId)->delete();
            $migration->up();
        }
    }

    public function test_workforce_and_purchase_rollback_reconstructs_derived_values(): void
    {
        $admin = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
        $this->withSession(['user_id' => $admin->id, 'auth_password_fingerprint' => hash_hmac('sha256', $admin->password_hash, (string) config('app.key')), '_token' => 'normalization-token'])
            ->withHeader('X-CSRF-TOKEN', 'normalization-token');
        $employeeId = DB::table('employees')->insertGetId(['full_name' => 'Empleado', 'job_title' => 'Cocina', 'branch_id' => 1]);
        $shiftId = DB::table('employee_shifts')->insertGetId(['employee_id' => $employeeId, 'branch_id' => 1, 'shift_date' => '2030-01-15', 'start_time' => '09:00', 'end_time' => '17:00']);
        $this->postJson('/api/workforce.php', ['action' => 'shift.clock_in', 'id' => $shiftId])->assertOk()->assertJsonPath('ok', true);
        $eventId = DB::table('attendance_events')->where('shift_id', $shiftId)->value('id');
        $supplierId = DB::table('suppliers')->insertGetId(['name' => 'Proveedor']);
        $purchaseId = DB::table('purchase_orders')->insertGetId(['order_number' => 'PO-TEST', 'supplier_id' => $supplierId, 'order_date' => '2030-01-15', 'status' => 'sent']);
        $purchaseItemId = DB::table('purchase_order_items')->insertGetId(['purchase_order_id' => $purchaseId, 'ingredient_id' => 1, 'ingredient_name_snapshot' => 'Pescado', 'unit' => 'g', 'ordered_quantity' => 100, 'unit_cost' => 1, 'line_total' => 100]);
        $this->postJson('/api/purchasing.php', ['action' => 'order.receive', 'id' => $purchaseId, 'items' => [['item_id' => $purchaseItemId, 'quantity' => 100, 'unit_cost' => 1]]])->assertOk()->assertJsonPath('ok', true);
        $receiptId = DB::table('purchase_receipts')->where('purchase_order_id', $purchaseId)->value('id');
        $receiptItemId = DB::table('purchase_receipt_items')->where('receipt_id', $receiptId)->value('id');
        $this->assertDatabaseHas('ingredients', ['id' => 1, 'stock_quantity' => 8100]);
        $migration = require database_path('migrations/2026_11_01_195023_remove_derived_workforce_and_purchase_columns.php');
        $migration->down();
        $this->assertDatabaseHas('attendance_events', ['id' => $eventId, 'employee_id' => $employeeId, 'branch_id' => 1]);
        $this->assertDatabaseHas('purchase_receipts', ['id' => $receiptId, 'supplier_id' => $supplierId]);
        $this->assertDatabaseHas('purchase_receipt_items', ['id' => $receiptItemId, 'ingredient_id' => 1]);
        $migration->up();
        $this->assertFalse(Schema::hasColumn('attendance_events', 'employee_id'));
        $this->assertFalse(Schema::hasColumn('purchase_receipts', 'supplier_id'));
        $this->assertFalse(Schema::hasColumn('purchase_receipt_items', 'ingredient_id'));
        $this->assertDatabaseHas('attendance_events', ['id' => $eventId, 'shift_id' => $shiftId]);
        $this->assertDatabaseHas('purchase_receipt_items', ['id' => $receiptItemId, 'quantity' => 100]);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('fk_step14_attendance_shift');
        DB::table('employee_shifts')->where('id', $shiftId)->delete();
    }

    public function test_hold_tables_reject_nonexistent_tables(): void
    {
        $holdId = $this->hold();
        $this->expectException(QueryException::class);
        DB::table('reservation_hold_tables')->insert(['hold_id' => $holdId, 'table_id' => 99999999]);
    }

    public function test_negative_prices_are_rejected_by_the_database(): void
    {
        $this->expectException(QueryException::class);
        DB::table('products')->where('id', 1)->update(['price' => -1]);
    }

    public function test_reservation_payments_prevent_accidental_deletion_of_the_reservation(): void
    {
        $reservationId = DB::table('reservations')->insertGetId(['customer_name' => 'Cliente', 'table_id' => 1, 'guest_count' => 2, 'reservation_at' => now()->addDay()]);
        DB::table('reservation_payments')->insert(['reservation_id' => $reservationId, 'amount' => 10]);
        $this->expectException(QueryException::class);
        DB::table('reservations')->where('id', $reservationId)->delete();
    }

    public function test_a_customer_can_have_multiple_addresses_but_only_one_active_default(): void
    {
        $customerId = DB::table('customers')->insertGetId(['full_name' => 'Cliente']);
        foreach ([0, 0, 1] as $isDefault) {
            DB::table('customer_addresses')->insert(['customer_id' => $customerId, 'address_line' => 'Dirección', 'is_default' => $isDefault]);
        }
        $this->assertDatabaseCount('customer_addresses', 3);
        $this->expectException(QueryException::class);
        DB::table('customer_addresses')->insert(['customer_id' => $customerId, 'address_line' => 'Otra dirección', 'is_default' => 1]);
    }

    public function test_orders_cannot_reference_a_member_of_a_different_dining_session(): void
    {
        $sessions = [(string) Str::uuid(), (string) Str::uuid()];
        foreach ($sessions as $session) {
            DB::table('dining_sessions')->insert(['id' => $session, 'table_id' => 1, 'join_code_hash' => 'hash', 'join_code_encrypted' => 'encrypted']);
        }
        $memberId = (string) Str::uuid();
        DB::table('dining_members')->insert(['id' => $memberId, 'dining_session_id' => $sessions[0], 'name' => 'Ana', 'token_hash' => hash('sha256', 'token')]);
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('fk_order_member_session');
        $this->order(['dining_session_id' => $sessions[1], 'dining_member_id' => $memberId]);
    }

    public function test_business_day_name_is_derived_and_each_day_has_one_schedule(): void
    {
        DB::table('business_hours')->insert(['day_of_week' => 3]);
        $this->assertSame('Miércoles', DB::table('business_hours')->where('day_of_week', 3)->value('day_name'));
        $this->expectException(QueryException::class);
        DB::table('business_hours')->insert(['day_of_week' => 3]);
    }

    public function test_reapplying_table_configuration_preserves_unchanged_table_timestamps(): void
    {
        DB::table('reservation_configurations')->insert(['id' => 1]);
        DB::table('restaurant_tables')->update(['updated_at' => '2020-01-01 00:00:00']);
        $migration = require database_path('migrations/2026_11_01_194953_normalize_business_hours_and_configuration.php');
        $migration->down();
        $migration->up();
        $this->assertSame(12, DB::table('restaurant_tables')->where('updated_at', '2020-01-01 00:00:00')->count());
    }

    public function test_production_seeding_is_repeatable_and_preserves_operator_changes(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (DB::connection()->getPdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if ($table !== 'migrations') {
                    DB::table($table)->delete();
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        config(['desego.seeding.admin_password' => 'initial-test-password', 'desego.seeding.admin_email' => 'initial@example.test']);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('products', 35);
        $this->assertDatabaseCount('ingredients', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('business_hours', 7);
        $hash = DB::table('users')->where('email', 'initial@example.test')->value('password_hash');
        $this->assertTrue(Hash::check('initial-test-password', $hash));
        $productId = DB::table('products')->where('code', 'PF001')->value('id');
        DB::table('products')->where('id', $productId)->update(['price' => 99, 'status' => 'unavailable']);
        DB::table('restaurant_tables')->where('table_number', 1)->update(['capacity' => 8, 'is_active' => false]);
        DB::table('business_hours')->where('day_of_week', 1)->update(['open_time' => '10:00:00']);
        DB::table('invoice_sequences')->where('document_type', 'boleta')->update(['current_number' => 42]);
        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');
        DB::table('role_permissions')->where('role_id', $adminRoleId)->delete();
        config(['desego.seeding.admin_password' => 'different-test-password']);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('products', 35);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('products', ['id' => $productId, 'price' => 99, 'status' => 'unavailable']);
        $this->assertDatabaseHas('restaurant_tables', ['table_number' => 1, 'capacity' => 8, 'is_active' => false]);
        $this->assertDatabaseHas('business_hours', ['day_of_week' => 1, 'open_time' => '10:00:00']);
        $this->assertDatabaseHas('invoice_sequences', ['document_type' => 'boleta', 'current_number' => 42]);
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $adminRoleId)->count());
        $this->assertSame($hash, DB::table('users')->where('email', 'initial@example.test')->value('password_hash'));
    }

    public function test_reference_seeding_uses_codes_instead_of_assuming_numeric_role_ids(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('role_permissions')->delete();
        DB::table('roles')->delete();
        DB::table('permissions')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        DB::table('permissions')->insert(['id' => 500, 'code' => 'dashboard.view', 'name' => 'Mi panel', 'module' => 'dashboard']);
        $this->seed(RestaurantReferenceSeeder::class);
        $adminId = DB::table('roles')->where('code', 'admin')->value('id');
        $this->assertDatabaseHas('role_permissions', ['role_id' => $adminId, 'permission_id' => 500]);
        $this->assertDatabaseHas('permissions', ['id' => 500, 'name' => 'Mi panel']);
    }

    public function test_backups_restore_generated_columns_and_normalized_relations(): void
    {
        DB::table('business_hours')->insert(['day_of_week' => 1]);
        $customerId = DB::table('customers')->insertGetId(['full_name' => 'Cliente respaldo']);
        DB::table('customer_addresses')->insert(['customer_id' => $customerId, 'address_line' => 'Dirección guardada', 'is_default' => 1]);
        $holdId = $this->hold();
        DB::table('reservation_hold_tables')->insert(['hold_id' => $holdId, 'table_id' => 1]);
        $source = DB::connection()->getPdo();
        $tables = $source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $before = VerifyDesego::inspect($source, $tables);
        $dump = desego_backup_dump($source, self::$testDatabase, 67108864);
        $restored = self::$testDatabase.'_restored';
        self::$server->exec('CREATE DATABASE `'.$restored.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection = config('database.connections.mysql');
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $connection['host'], $connection['port'], $restored), $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {
            foreach (app(SqlDump::class)->statements($dump) as $statement) {
                $pdo->exec($statement);
            }
            $this->assertSame($before, VerifyDesego::inspect($pdo, $tables));
        } finally {
            $pdo = null;
            self::$server->exec('DROP DATABASE `'.$restored.'`');
        }
    }
}
