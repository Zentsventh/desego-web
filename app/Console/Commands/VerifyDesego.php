<?php

namespace App\Console\Commands;

use App\Support\Desego\SqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class VerifyDesego extends Command
{
    protected $signature = 'desego:verify {--baseline : Comparar filas y hashes con la importacion inicial} {--source= : Comparar con una base origen en modo solo lectura}';

    protected $description = 'Verifica las 61 tablas DESEGO, sus relaciones y opcionalmente los datos originales';

    public function handle(SqlDump $parser): int
    {
        try {
            $dump = $parser->parse(file_get_contents(database_path('desego/schema.sql')));
            $report = self::inspect(DB::connection()->getPdo(), $dump['tables']);
            if ($report['orphans'] !== []) {
                throw new RuntimeException('Relaciones huérfanas: '.json_encode($report['orphans']));
            }
            if ($this->option('baseline')) {
                $path = storage_path('app/private/desego/migration-report.json');
                if (! is_file($path)) {
                    throw new RuntimeException('No existe el informe de importación.');
                }
                $baseline = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                if ($baseline['tables'] !== $report['tables'] || $baseline['foreign_keys'] !== $report['foreign_keys']) {
                    throw new RuntimeException('Los datos han cambiado desde la importación inicial.');
                }
            }
            if ($source = $this->option('source')) {
                if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $source)) {
                    throw new RuntimeException('Nombre de origen no valido.');
                }
                $config = config('database.connections.'.config('database.default'));
                $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $source), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("SET time_zone = '+00:00'");
                $pdo->exec('SET TRANSACTION READ ONLY');
                $pdo->beginTransaction();
                $original = self::inspect($pdo, $dump['tables']);
                $pdo->commit();
                if ($original !== $report) {
                    throw new RuntimeException('El contenido difiere de la base origen.');
                }
                $this->info('Datos identicos al origen: '.$source.' (comparacion SHA-256 de todas las filas).');
            }
            $this->table(['Tabla', 'Filas'], array_map(static fn ($table, $details) => [$table, $details['rows']], array_keys($report['tables']), $report['tables']));
            $this->info(count($report['tables']).' tablas; '.array_sum(array_column($report['tables'], 'rows')).' filas; '.$report['foreign_keys'].' relaciones íntegras.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }

    /** @param list<string> $tables
     * @return array{tables: array<string, array{rows: int, sha256: string}>, foreign_keys: int, orphans: array<string, int>}
     */
    public static function inspect(PDO $pdo, array $tables): array
    {
        $report = ['tables' => [], 'foreign_keys' => 0, 'orphans' => []];
        foreach ($tables as $table) {
            $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $keys = array_column(array_filter($columns, static fn ($column) => $column['Key'] === 'PRI'), 'Field');
            if ($keys === []) {
                $keys = array_column($columns, 'Field');
            }
            $order = implode(', ', array_map(static fn ($column) => '`'.$column.'`', $keys));
            $cursor = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY '.$order);
            $hash = hash_init('sha256');
            $count = 0;
            while ($row = $cursor->fetch(PDO::FETCH_NUM)) {
                $row = array_map(static fn ($value) => $value === null ? null : (string) $value, $row);
                hash_update($hash, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $count++;
            }
            $report['tables'][$table] = ['rows' => $count, 'sha256' => hash_final($hash)];
        }
        $foreignKeys = $pdo->query('SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
        $constraints = [];
        foreach ($foreignKeys as $key) {
            if (in_array($key['TABLE_NAME'], $tables, true)) {
                $constraints[$key['TABLE_NAME'].'.'.$key['CONSTRAINT_NAME']][] = $key;
            }
        }
        foreach ($constraints as $name => $keys) {
            $first = $keys[0];
            $conditions = [];
            $notNull = [];
            foreach ($keys as $key) {
                $conditions[] = 'c.`'.$key['COLUMN_NAME'].'` = p.`'.$key['REFERENCED_COLUMN_NAME'].'`';
                $notNull[] = 'c.`'.$key['COLUMN_NAME'].'` IS NOT NULL';
            }
            $query = 'SELECT COUNT(*) FROM `'.$first['TABLE_NAME'].'` c WHERE '.implode(' AND ', $notNull).' AND NOT EXISTS (SELECT 1 FROM `'.$first['REFERENCED_TABLE_NAME'].'` p WHERE '.implode(' AND ', $conditions).')';
            $count = (int) $pdo->query($query)->fetchColumn();
            if ($count > 0) {
                $report['orphans'][$name] = $count;
            }
            $report['foreign_keys']++;
        }

        return $report;
    }
}
