<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyDesego;
use App\Models\User;
use App\Support\Desego\DatabaseIntegrityAudit;
use App\Support\Desego\LegacyDesegoMigration;
use App\Support\Desego\SqlDump;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PDO;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

class DesegoMigrationTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);
    }

    private function authorizeUser(User $user): void
    {
        $this->withSession(['user_id' => $user->id, 'auth_password_fingerprint' => hash_hmac('sha256', $user->password_hash, (string) config('app.key')), '_token' => 'test-csrf-token']);
        $this->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }

    public function test_native_migrations_match_the_original_schema_column_for_column(): void
    {
        $reference = self::$testDatabase.'_reference';
        self::$server->exec('CREATE DATABASE `'.$reference.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $config = config('database.connections.mysql');
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $reference), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {
            foreach (app(SqlDump::class)->parse(file_get_contents(database_path('desego/schema.sql')))['schema'] as $statement) {
                $pdo->exec($statement);
            }
            $this->assertSame($this->schemaDefinition($pdo), $this->schemaDefinition(DB::connection()->getPdo()));
            $this->assertTrue(LegacyDesegoMigration::nativeBaselinePrecedesLegacy());
            $this->assertFalse(LegacyDesegoMigration::skipNativeMigration(LegacyDesegoMigration::NATIVE_MIGRATIONS[0]));
        } finally {
            $pdo = null;
            self::$server->exec('DROP DATABASE `'.$reference.'`');
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function schemaDefinition(PDO $pdo): array
    {
        $filter = implode(', ', array_map(static fn ($name) => $pdo->quote($name), $this->tables));
        $queries = [
            'columns' => 'SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$filter.') ORDER BY TABLE_NAME, ORDINAL_POSITION',
            'indexes' => 'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$filter.') ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            'relations' => 'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME IN ('.$filter.') ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            'checks' => 'SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$filter.') ORDER BY TABLE_NAME, CONSTRAINT_NAME',
            'tables' => 'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$filter.') ORDER BY TABLE_NAME',
        ];
        $definition = [];
        foreach ($queries as $section => $sql) {
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $rows = array_values(array_filter($rows, fn ($row) => ! (($section === 'columns' && $row['TABLE_NAME'] === 'customers' && $row['COLUMN_NAME'] === 'user_id') || ($section === 'indexes' && $row['INDEX_NAME'] === 'uq_customers_user') || ($section === 'relations' && $row['CONSTRAINT_NAME'] === 'fk_customers_user'))));
            $positions = [];
            foreach ($rows as &$row) {
                if ($section === 'columns') {
                    $positions[$row['TABLE_NAME']] = ($positions[$row['TABLE_NAME']] ?? 0) + 1;
                    $row['ORDINAL_POSITION'] = $positions[$row['TABLE_NAME']];
                }
                foreach ($row as $key => &$value) {
                    if ($value !== null) {
                        $value = (string) $value;
                        if ($key === 'COLUMN_TYPE') {
                            $value = preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $value);
                        }
                    }
                }
                unset($value);
            }
            unset($row);
            $definition[$section] = $rows;
        }

        return $definition;
    }

    public function test_all_source_tables_rows_and_foreign_keys_are_preserved(): void
    {
        $report = VerifyDesego::inspect(DB::connection()->getPdo(), $this->tables);
        $this->assertCount(61, $report['tables']);
        $this->assertSame(443, array_sum(array_column($report['tables'], 'rows')));
        $this->assertSame(122, $report['foreign_keys']);
        $this->assertSame([], $report['orphans']);
        $this->assertDatabaseHas('allergens', ['name' => 'Crustáceos']);
        $this->assertDatabaseHas('products', ['id' => 1]);
    }

    public function test_administrator_can_read_every_migrated_module(): void
    {
        $this->authorizeUser($this->admin());
        $paths = [
            'auth.php?action=session', 'catalog.php', 'catalog.php?view=categories', 'catalog.php?view=ingredients',
            'inventory.php', 'orders.php', 'orders.php?view=products', 'orders.php?view=stats', 'cash.php',
            'billing-reports.php', 'billing-reports.php?view=reports', 'customers.php', 'loyalty.php',
            'operations.php?view=kitchen', 'operations.php?view=delivery', 'payments.php', 'profile.php?action=preferences',
            'promotions.php', 'purchasing.php', 'purchasing.php?view=costs', 'reservations.php',
            'reviews.php', 'roles.php', 'users.php', 'workforce.php', 'workforce.php?view=branches', 'workforce.php?view=employees',
            'admin-tools.php?view=analytics', 'admin-tools.php?view=audit', 'admin-tools.php?view=backups',
            'communications.php', 'communications.php?view=tickets', 'communications.php?view=integrations', 'storefront.php',
        ];
        foreach ($paths as $path) {
            $response = $this->getJson('/api/'.$path);
            $this->assertSame(200, $response->status(), $path.': '.$response->getContent());
            $response->assertJsonPath('ok', true);
        }
    }

    public function test_existing_password_hashes_work_and_logout_clears_access(): void
    {
        $user = $this->admin();
        $hash = DB::table('users')->where('id', $user->id)->value('password_hash');
        $this->withSession(['_token' => 'login-token'])->postJson('/api/auth.php?action=login', ['email' => $user->email, 'password' => 'password', 'remember' => true], ['X-CSRF-TOKEN' => 'login-token'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertSessionHas('user_id', $user->id)->assertCookie('DESEGO_REMEMBER');
        $this->assertSame($hash, DB::table('users')->where('id', $user->id)->value('password_hash'));
        $this->assertDatabaseCount('remember_tokens', 1);
        $token = session()->token();
        $this->postJson('/api/auth.php?action=logout', [], ['X-CSRF-TOKEN' => $token])->assertOk()->assertSessionMissing('user_id');
        $this->getJson('/api/users.php')->assertUnauthorized();
    }

    public function test_staff_permissions_and_wrong_passwords_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->authorizeUser($user);
        $this->getJson('/api/users.php')->assertForbidden()->assertJsonPath('ok', false);
        $this->postJson('/api/auth.php?action=login', ['email' => $user->email, 'password' => 'incorrect'])->assertUnauthorized();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'failed_login_count' => 1]);
    }

    public function test_paid_order_and_cash_close_preserve_financial_totals(): void
    {
        $this->authorizeUser($this->admin());
        $this->postJson('/api/cash.php', ['action' => 'open', 'opening_amount' => 100])->assertOk();
        $product = DB::table('products')->where('status', 'available')->first();
        $response = $this->postJson('/api/orders.php', ['action' => 'create', 'order_type' => 'Para llevar', 'payment_status' => 'paid', 'payment_method' => 'Efectivo', 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $response->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['payment_status' => 'Pagado', 'total' => $product->price]);
        $this->assertDatabaseHas('payments', ['amount' => $product->price, 'method' => 'Efectivo']);
        $this->postJson('/api/cash.php', ['action' => 'close', 'counted_closing' => 100 + (float) $product->price])->assertOk();
        $this->assertDatabaseHas('cash_sessions', ['status' => 'closed', 'difference_amount' => 0]);
    }

    public function test_checkout_is_idempotent_and_records_delivery_and_customer(): void
    {
        $this->admin();
        $this->withSession(['_token' => 'checkout-token'])->withHeader('X-CSRF-TOKEN', 'checkout-token');
        $product = DB::table('products')->where('status', 'available')->first();
        $payload = ['action' => 'checkout', 'customer_name' => 'Cliente de prueba', 'phone' => '999111222', 'email' => 'cliente@example.test', 'address_line' => 'Avenida de prueba 123', 'district' => 'Lima', 'consent' => true, 'checkout_key' => '12345678-1234-1234-1234-123456789abc', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
        $first = $this->postJson('/api/storefront.php', $payload)->assertOk();
        $this->postJson('/api/storefront.php', $payload)->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('order_id', $first->json('order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_addresses', 1);
        $this->assertDatabaseCount('order_delivery_details', 1);
        $this->assertDatabaseHas('orders', ['order_channel' => 'web', 'payment_status' => 'Pendiente', 'total' => (float) $product->price + 5]);
    }

    public function test_checkout_failure_rolls_back_customer_and_order_changes(): void
    {
        $this->admin();
        $this->withSession(['_token' => 'checkout-token'])->withHeader('X-CSRF-TOKEN', 'checkout-token');
        $this->postJson('/api/storefront.php', ['action' => 'checkout', 'customer_name' => 'Cliente de prueba', 'phone' => '999111222', 'address_line' => 'Avenida de prueba 123', 'district' => 'Lima', 'consent' => true, 'checkout_key' => '12345678-1234-1234-1234-123456789abc', 'items' => [['product_id' => 999999, 'quantity' => 1]]])->assertStatus(409);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_addresses', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
    }

    public function test_import_refuses_to_overwrite_an_existing_database(): void
    {
        $path = tempnam(storage_path('framework'), 'desego-import-');
        file_put_contents($path, file_get_contents(database_path('desego/schema.sql')).file_get_contents(database_path('desego/reference-data.sql')));
        try {
            $this->artisan('desego:import', ['path' => $path, '--database-name' => self::$testDatabase])->assertExitCode(1);
        } finally {
            unlink($path);
        }
        $this->assertDatabaseCount('products', 6);
    }

    public function test_password_recovery_uses_laravel_mail_transport(): void
    {
        $user = $this->admin();
        config(['mail.default' => 'array']);
        $this->withSession(['_token' => 'recovery-token'])->postJson('/api/password-reset.php?action=request', ['email' => $user->email], ['X-CSRF-TOKEN' => 'recovery-token'])->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($user->email, $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
        $this->assertStringContainsString('/restablecer-contrasena?token=', $messages->first()->getOriginalMessage()->getTextBody());
    }

    public function test_audit_csv_download_preserves_headers_and_records_the_export(): void
    {
        $this->authorizeUser($this->admin());
        $this->get('/api/admin-tools.php?view=audit-export')->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8')->assertSee('"ID";"Fecha UTC"', false);
        $this->assertDatabaseHas('audit_logs', ['event_code' => 'audit.exported']);
    }

    public function test_encrypted_backups_download_and_restore_with_a_safety_copy(): void
    {
        $this->authorizeUser($this->admin());
        $directory = storage_path('framework/desego-backup-test-'.bin2hex(random_bytes(6)));
        mkdir($directory, 0700);
        config(['desego.backup.storage_dir' => $directory, 'desego.backup.redundant_dir' => '', 'desego.backup.encryption_key_hex' => bin2hex(random_bytes(32))]);
        try {
            $created = $this->postJson('/api/admin-tools.php', ['action' => 'create_backup'])->assertOk();
            $backup = $created->json('backup');
            $this->assertStringStartsWith('DSGBK1', file_get_contents($directory.'/'.$backup['file_name']));
            $this->get('/api/admin-tools.php?view=download&id='.$backup['id'])->assertOk()->assertDownload($backup['file_name']);
            $later = User::factory()->create();
            $this->artisan('desego:restore', ['backup' => $backup['id'], '--confirm' => 'RESTORE-DESEGO'])->assertExitCode(0);
            $this->assertDatabaseMissing('users', ['email' => $later->email]);
            $this->assertDatabaseHas('backup_history', ['backup_kind' => 'pre_restore', 'status' => 'completed']);
            $this->assertSame([], VerifyDesego::inspect(DB::connection()->getPdo(), $this->tables)['orphans']);
        } finally {
            foreach (glob($directory.'/*.dsgbk') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_sessions_reject_unknown_users(): void
    {
        try {
            DB::table('sessions')->insert(['id' => 'invalid-user-session', 'user_id' => 999999999, 'payload' => '', 'last_activity' => time()]);
            $this->fail('A session referencing a nonexistent user must be rejected.');
        } catch (QueryException $error) {
            $this->assertSame(1452, $error->errorInfo[1]);
        }
    }

    public function test_deleting_a_user_preserves_the_session_and_clears_its_reference(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'retained-session', 'user_id' => $user->id, 'payload' => 'retained-payload', 'last_activity' => time()]);
        $user->delete();
        $this->assertDatabaseHas('sessions', ['id' => 'retained-session', 'user_id' => null, 'payload' => 'retained-payload']);
    }

    public function test_full_database_audit_passes_after_all_migrations(): void
    {
        $report = app(DatabaseIntegrityAudit::class)->inspect(DB::connection()->getPdo());
        $this->assertTrue($report['healthy'], json_encode($report['issues']));
        $this->assertSame(68, $report['summary']['tables']);
        $this->assertSame(68, $report['summary']['primary_keys']);
        $this->assertSame(123, $report['summary']['foreign_keys']);
        $this->assertSame(0, $report['summary']['orphan_constraints']);
        $path = tempnam(storage_path('framework'), 'desego-audit-');
        try {
            $this->artisan('desego:audit', ['--report' => $path])->assertExitCode(0);
            $saved = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(self::$testDatabase, $saved['database']);
            $this->assertTrue($saved['healthy']);
        } finally {
            unlink($path);
        }
    }

    public function test_database_audit_detects_orphaned_sessions_and_exits_with_failure(): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('sessions')->insert(['id' => 'orphaned-session', 'user_id' => 999999999, 'payload' => '', 'last_activity' => time()]);
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        $report = app(DatabaseIntegrityAudit::class)->inspect($pdo);
        $this->assertFalse($report['healthy']);
        $this->assertSame(1, $report['summary']['orphan_constraints']);
        $this->assertStringContainsString('sessions.fk_sessions_user', implode(';', $report['issues']));
        $this->artisan('desego:audit')->assertExitCode(1);
    }

    public function test_fresh_rollback_and_legacy_adoption_preserve_the_expected_ownership(): void
    {
        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $remaining = DB::connection()->getPdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['migrations'], $remaining);

        $legacyPaths = [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000001_create_cache_table.php',
            'database/migrations/0001_01_01_000002_create_jobs_table.php',
            'database/migrations/2026_10_25_230128_create_desego_tables.php',
            'database/migrations/2026_10_25_231826_add_user_foreign_key_to_sessions_table.php',
        ];
        $legacyPaths[] = str_replace('\\', '/', glob(database_path('migrations/*_link_customer_accounts_to_users.php'))[0]);
        $legacyPaths = array_map(fn ($path) => str_starts_with($path, 'database/') ? base_path($path) : $path, $legacyPaths);
        $this->artisan('migrate', ['--realpath' => true, '--path' => $legacyPaths, '--force' => true])->assertExitCode(0);
        $pdo = DB::connection()->getPdo();
        foreach (app(SqlDump::class)->statements(file_get_contents(database_path('desego/reference-data.sql'))) as $statement) {
            $pdo->exec($statement);
        }
        $before = VerifyDesego::inspect($pdo, $this->tables);
        $schema = $this->schemaDefinition($pdo);
        $queries = DB::connection()->pretend(function (): void {
            $migration = require database_path('migrations/'.LegacyDesegoMigration::NATIVE_MIGRATIONS[0].'.php');
            $migration->up();
        });
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(CREATE|ALTER|DROP)\s/i', $query['query']);
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(LegacyDesegoMigration::skipNativeMigration(LegacyDesegoMigration::NATIVE_MIGRATIONS[0]));
        $this->assertSame($before, VerifyDesego::inspect($pdo, $this->tables));
        $this->assertSame($schema, $this->schemaDefinition($pdo));
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);
        $this->assertSame($before, VerifyDesego::inspect($pdo, $this->tables));
        $this->assertSame($schema, $this->schemaDefinition($pdo));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertSame($before, VerifyDesego::inspect($pdo, $this->tables));
    }
}
