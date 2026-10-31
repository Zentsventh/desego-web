<?php

namespace App\Console\Commands;

use App\Support\Desego\SqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class ImportDesego extends Command
{
    protected $signature = 'desego:import {path? : Respaldo SQL de phpMyAdmin} {--database-name= : Base nueva de destino}';

    protected $description = 'Migra el esquema y todos los datos DESEGO a una base vacía, sin modificar el origen';

    public function handle(SqlDump $parser): int
    {
        try {
            $path = $this->argument('path') ?: storage_path('app/private/desego/source.sql');
            if (! is_file($path) || ! is_readable($path)) {
                throw new RuntimeException('No se puede leer el respaldo SQL.');
            }
            $sql = file_get_contents($path);
            $dump = $parser->parse($sql);
            $schema = implode(";\n\n", $dump['schema']).";\n";
            if (hash('sha256', $schema) !== hash_file('sha256', database_path('desego/schema.sql'))) {
                throw new RuntimeException('El esquema del SQL difiere de la migración incluida. No se importó ningún dato.');
            }
            $connection = config('database.default');
            $config = config('database.connections.'.$connection);
            if (! in_array($config['driver'], ['mysql', 'mariadb'], true)) {
                throw new RuntimeException('Configura DB_CONNECTION=mysql o mariadb.');
            }
            $name = $this->option('database-name') ?: $config['database'];
            if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $name) || strtolower($name) === 'desego') {
                throw new RuntimeException('Elige una base nueva distinta de desego; el origen est? protegido.');
            }
            $server = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $check = $server->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
            $check->execute([$name]);
            if ((int) $check->fetchColumn() !== 0) {
                throw new RuntimeException('La base de destino ya contiene tablas. No se sobrescribe ni duplica información.');
            }
            $server->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            config(['database.connections.'.$connection.'.database' => $name]);
            DB::purge($connection);
            $this->info('Destino: '.$name.'. El origen no se modifica.');
            if ($this->call('migrate', ['--database' => $connection, '--force' => true]) !== self::SUCCESS) {
                return self::FAILURE;
            }
            $pdo = DB::connection()->getPdo();
            $pdo->exec("SET time_zone = '+00:00'");
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_AUTO_VALUE_ON_ZERO,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $expected = array_fill_keys($dump['tables'], 0);
            try {
                $pdo->beginTransaction();
                foreach ($dump['data'] as $statement) {
                    preg_match('/^INSERT INTO `([^`]+)`/i', $statement, $matches);
                    $expected[$matches[1]] += $pdo->exec($statement);
                }
                foreach ($expected as $table => $count) {
                    if ((int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn() !== $count) {
                        throw new RuntimeException('El recuento de filas no coincide: '.$table);
                    }
                }
                $report = VerifyDesego::inspect($pdo, $dump['tables']);
                if ($report['orphans'] !== []) {
                    throw new RuntimeException('Se detectaron relaciones huérfanas. Se revierte la importación de datos.');
                }
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
            $report['database'] = $name;
            $report['source_sha256'] = hash('sha256', $sql);
            $report['schema_sha256'] = hash('sha256', $schema);
            $report['imported_at_utc'] = gmdate(DATE_ATOM);
            $directory = storage_path('app/private/desego');
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($directory.'/migration-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $this->info(count($report['tables']).' tablas y '.array_sum(array_column($report['tables'], 'rows')).' filas importadas. '.$report['foreign_keys'].' relaciones verificadas, sin registros huérfanos.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
