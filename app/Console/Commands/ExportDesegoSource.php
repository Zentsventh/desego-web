<?php

namespace App\Console\Commands;

use App\Support\Desego\SqlDump;
use Illuminate\Console\Command;
use PDO;
use RuntimeException;
use Throwable;

class ExportDesegoSource extends Command
{
    protected $signature = 'desego:export-source {source=desego} {--path= : Archivo privado de salida}';

    protected $description = 'Exporta los datos actuales del origen en una transaccion de solo lectura';

    public function handle(SqlDump $parser): int
    {
        try {
            $source = $this->argument('source');
            if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $source)) {
                throw new RuntimeException('Nombre de origen no valido.');
            }
            $config = config('database.connections.'.config('database.default'));
            $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $source), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("SET time_zone = '+00:00'");
            $schema = file_get_contents(database_path('desego/schema.sql'));
            $dump = $parser->parse($schema);
            $actual = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            $expected = $dump['tables'];
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                throw new RuntimeException('Las tablas del origen difieren del esquema migrado.');
            }
            $path = $this->option('path') ?: storage_path('app/private/desego/live-source.sql');
            if (file_exists($path)) {
                throw new RuntimeException('El archivo de salida ya existe; elige otra ruta.');
            }
            $sql = "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\n".$schema;
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('SET TRANSACTION READ ONLY');
            $pdo->beginTransaction();
            $rows = 0;
            foreach ($dump['tables'] as $table) {
                $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
                $fields = array_column($columns, 'Field');
                $select = $pdo->query('SELECT * FROM `'.$table.'`');
                while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
                    $values = array_map(static fn ($field) => $row[$field] === null ? 'NULL' : $pdo->quote((string) $row[$field]), $fields);
                    $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`, `', $fields).'`) VALUES ('.implode(', ', $values).");\n";
                    $rows++;
                }
            }
            $pdo->commit();
            if (file_put_contents($path, $sql, LOCK_EX) !== strlen($sql)) {
                throw new RuntimeException('No se pudo guardar el respaldo.');
            }
            $this->info('Snapshot de solo lectura: '.count($dump['tables']).' tablas y '.$rows.' registros. Archivo: '.$path);

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
