<?php

namespace Tests\Concerns;

use App\Support\Desego\SqlDump;
use Illuminate\Support\Facades\DB;
use PDO;

trait CreatesRestaurantDatabase
{
    protected static ?PDO $server = null;

    protected static ?string $testDatabase = null;

    protected array $tables;

    protected function setUp(): void
    {
        parent::setUp();
        $config = config('database.connections.mysql');
        if (self::$testDatabase === null) {
            self::$server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            self::$testDatabase = 'desego_test_'.bin2hex(random_bytes(6));
            self::$server->exec('CREATE DATABASE `'.self::$testDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => self::$testDatabase]);
        DB::purge();
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        $pdo = DB::connection('mysql')->getPdo();
        $parser = app(SqlDump::class);
        $this->tables = $parser->parse(file_get_contents(database_path('desego/schema.sql')))['tables'];
        if (! DB::getSchemaBuilder()->hasTable('users')) {
            $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $pdo->beginTransaction();
            foreach (array_diff($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), ['migrations']) as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $pdo->exec('DELETE FROM `'.$table.'`');
                }
            }
            foreach ($parser->statements(file_get_contents(database_path('desego/reference-data.sql'))) as $statement) {
                $pdo->exec($statement);
            }
            $pdo->commit();
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    protected function tearDown(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null && preg_match('/^desego_test_[a-f0-9]{12}$/', self::$testDatabase ?? '')) {
            self::$server->exec('DROP DATABASE `'.self::$testDatabase.'`');
        }
        self::$server = null;
        self::$testDatabase = null;
        parent::tearDownAfterClass();
    }
}
