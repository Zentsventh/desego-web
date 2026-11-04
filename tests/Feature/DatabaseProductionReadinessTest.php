<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Desego\DatabaseIntegrityAudit;
use App\Support\Desego\DatabaseIntegrityRules;
use App\Support\Desego\DatabaseSnapshot;
use App\Support\Desego\SqlDump;
use Closure;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Pdo\Mysql;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesRestaurantDatabase;
use Tests\TestCase;

use function App\Support\Desego\desego_backup_dump;

class DatabaseProductionReadinessTest extends TestCase
{
    use CreatesRestaurantDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_03_174142_harden_payment_integrity_and_restore_business_checks.php');
    }

    /** @return array{order_id: int, cash_session_id: int, actor_id: int} */
    private function paymentContext(): array
    {
        $user = User::factory()->create(['role_id' => DB::table('roles')->where('code', 'admin')->value('id')]);

        return [
            'order_id' => DB::table('orders')->insertGetId(['created_by' => $user->id, 'subtotal' => 25, 'total' => 25, 'created_at' => now(), 'updated_at' => now()]),
            'cash_session_id' => DB::table('cash_sessions')->insertGetId(['opened_by' => $user->id, 'opened_at' => now()]),
            'actor_id' => $user->id,
        ];
    }

    public function test_migration_recovers_checks_missing_from_an_export_without_changing_rows(): void
    {
        foreach (DatabaseIntegrityRules::RESTORED_CHECKS as $name => [$table]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
        }
        $before = DB::table('products')->orderBy('id')->get()->toJson();
        $this->migration()->up();
        $report = app(DatabaseIntegrityAudit::class)->inspect(DB::connection()->getPdo());
        $this->assertTrue($report['healthy'], implode('; ', $report['issues']));
        $this->assertSame(29, $report['summary']['check_constraints']);
        $this->assertSame($before, DB::table('products')->orderBy('id')->get()->toJson());
    }

    public function test_migration_stops_on_invalid_existing_data_before_adding_constraints(): void
    {
        $migration = $this->migration();
        $migration->down();
        $context = $this->paymentContext();
        DB::table('orders')->where('id', $context['order_id'])->update(['total' => -1]);
        try {
            try {
                $migration->up();
                $this->fail('La migracion debe rechazar importes negativos existentes.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('chk_order_amounts', $error->getMessage());
            }
            $this->assertSame('-1.00', DB::table('orders')->where('id', $context['order_id'])->value('total'));
            $this->assertSame(0, DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', self::$testDatabase)->whereIn('CONSTRAINT_NAME', array_keys(DatabaseIntegrityRules::PAYMENT_CHECKS))->count());
        } finally {
            DB::table('orders')->where('id', $context['order_id'])->update(['total' => 25]);
            $migration->up();
        }
    }

    /** @return array<string, array{string, int}> */
    public static function invalidAttempts(): array
    {
        return [
            'caja inexistente' => ['cash_session_id', 99999999],
            'usuario inexistente' => ['actor_id', 99999999],
            'importe cero' => ['amount_cents', 0],
        ];
    }

    #[DataProvider('invalidAttempts')]
    public function test_payment_attempts_reject_invalid_relations_and_amounts(string $column, int $value): void
    {
        $attempt = $this->paymentContext() + [
            'id' => (string) Str::uuid(), 'purchase_number' => '123456789012',
            'merchant_id' => 'test', 'environment' => 'sandbox', 'amount_cents' => 2500,
            'expires_at' => now()->addMinutes(10),
        ];
        $attempt[$column] = $value;
        $this->expectException(QueryException::class);
        DB::table('order_payment_attempts')->insert($attempt);
    }

    public function test_audit_detects_a_weakened_check_with_the_expected_name(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_product_price');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_product_price CHECK (price >= -100)');
        try {
            $report = app(DatabaseIntegrityAudit::class)->inspect(DB::connection()->getPdo());
            $this->assertFalse($report['healthy']);
            $this->assertContains('Definicion CHECK inesperada: products.chk_product_price', $report['issues']);
        } finally {
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_product_price');
            $this->migration()->up();
        }
    }

    public function test_audit_detects_disabled_check_enforcement(): void
    {
        DB::statement('SET SESSION check_constraint_checks=0');
        try {
            $report = app(DatabaseIntegrityAudit::class)->inspect(DB::connection()->getPdo());
            $this->assertFalse($report['healthy']);
            $this->assertContains('La conexion tiene CHECK_CONSTRAINT_CHECKS desactivado', $report['issues']);
        } finally {
            DB::statement('SET SESSION check_constraint_checks=1');
        }
    }

    public function test_export_restores_all_data_and_constraints_into_a_latin1_default_database(): void
    {
        $path = storage_path('framework/export-'.Str::uuid().'.sql');
        $restored = self::$testDatabase.'_restored';
        try {
            $this->artisan('desego:export', ['--path' => $path])->assertExitCode(0);
            $sql = file_get_contents($path);
            $this->assertStringContainsString('chk_order_attempt_amount', $sql);
            $this->assertStringContainsString('fk_order_attempt_actor', $sql);
            $originalHash = hash_file('sha256', $path);
            $this->artisan('desego:export', ['--path' => $path])->assertExitCode(1);
            $this->assertSame($originalHash, hash_file('sha256', $path));

            self::$server->exec('CREATE DATABASE '.$restored.' CHARACTER SET latin1 COLLATE latin1_swedish_ci');
            $config = config('database.connections.mysql');
            $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $restored), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach (app(SqlDump::class)->statements($sql) as $statement) {
                $pdo->exec($statement);
            }
            $audit = app(DatabaseIntegrityAudit::class);
            $before = $audit->inspect(DB::connection()->getPdo());
            $after = $audit->inspect($pdo);
            $this->assertTrue($after['healthy'], implode('; ', $after['issues']));
            $this->assertSame($before['summary'], $after['summary']);
            $this->assertSame(array_column($before['tables'], 'data', 'TABLE_NAME'), array_column($after['tables'], 'data', 'TABLE_NAME'));
            $pdo = null;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            self::$server->exec('DROP DATABASE IF EXISTS '.$restored);
        }
    }

    public function test_backup_uses_one_snapshot_while_another_connection_changes_two_tables(): void
    {
        DB::table('allergens')->where('id', 1)->update(['name' => 'Alergeno anterior']);
        DB::table('categories')->where('id', 1)->update(['name' => 'Categoria anterior']);
        $config = config('database.connections.mysql');
        $pdo = new class(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], self::$testDatabase), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO
        {
            public ?Closure $afterFirstRead = null;

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
            {
                $statement = $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
                if (str_starts_with($query, 'SELECT ') && str_contains($query, ' FROM '.chr(96).'allergens'.chr(96)) && $this->afterFirstRead !== null) {
                    $callback = $this->afterFirstRead;
                    $this->afterFirstRead = null;
                    $callback();
                }

                return $statement;
            }
        };
        $pdo->afterFirstRead = static function (): void {
            DB::transaction(static function (): void {
                DB::table('allergens')->where('id', 1)->update(['name' => 'Alergeno posterior']);
                DB::table('categories')->where('id', 1)->update(['name' => 'Categoria posterior']);
            });
        };
        $sql = desego_backup_dump($pdo, self::$testDatabase, 67108864);
        $this->assertStringContainsString('Alergeno anterior', $sql);
        $this->assertStringContainsString('Categoria anterior', $sql);
        $this->assertStringNotContainsString('Categoria posterior', $sql);
        $this->assertSame('Categoria posterior', DB::table('categories')->where('id', 1)->value('name'));
        $this->assertFalse($pdo->inTransaction());
    }

    public function test_failed_backup_releases_transaction_and_restores_connection_options(): void
    {
        $pdo = DB::connection()->getPdo();
        $buffered = $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY);
        try {
            desego_backup_dump($pdo, self::$testDatabase, 1024);
            $this->fail('El respaldo debe respetar el limite de tamano.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('limite', $error->getMessage());
        }
        $this->assertFalse($pdo->inTransaction());
        $this->assertSame($buffered, $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY));
        $this->assertNotNull($pdo->query('SELECT 1')->fetchColumn());
    }

    public function test_backup_does_not_commit_a_callers_transaction(): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->beginTransaction();
        $stream = fopen('php://temp', 'w+b');
        try {
            try {
                app(DatabaseSnapshot::class)->write($pdo, $stream);
                $this->fail('No debe reutilizar una transaccion ajena.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('transacciones activas', $error->getMessage());
            }
            $this->assertTrue($pdo->inTransaction());
        } finally {
            $pdo->rollBack();
            fclose($stream);
        }
    }
}
