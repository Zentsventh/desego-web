<?php

namespace App\Support\Desego;

use PDO;
use Pdo\Mysql;
use RuntimeException;
use Throwable;

class DatabaseSnapshot
{
    /**
     * @param  resource  $stream
     * @return array{tables: int, rows: int, bytes: int}
     */
    public function write(PDO $pdo, mixed $stream, ?int $maxBytes = null): array
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new RuntimeException('El destino del respaldo debe ser un stream abierto.');
        }
        if ($pdo->inTransaction()) {
            throw new RuntimeException('El respaldo requiere una conexion sin transacciones activas.');
        }

        $bytes = 0;
        $rows = 0;
        $buffered = $pdo->getAttribute(Mysql::ATTR_USE_BUFFERED_QUERY);
        $append = static function (string $sql) use ($stream, $maxBytes, &$bytes): void {
            $length = strlen($sql);
            if ($maxBytes !== null && $bytes + $length > $maxBytes) {
                throw new RuntimeException('El backup supera el limite de tamano configurado. Usa desego:export para exportaciones grandes.');
            }
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($stream, substr($sql, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('No se pudo escribir el respaldo completo.');
                }
                $offset += $written;
            }
            $bytes += $length;
        };

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        try {
            $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
            $definitions = [];
            foreach ($tables as $table) {
                if ($table['ENGINE'] !== 'InnoDB') {
                    throw new RuntimeException('No se puede garantizar un respaldo consistente de '.$table['TABLE_NAME'].': requiere InnoDB.');
                }
                $identifier = desego_sql_identifier($table['TABLE_NAME']);
                $columns = $pdo->query('SHOW COLUMNS FROM '.$identifier)->fetchAll(PDO::FETCH_ASSOC);
                $columns = array_values(array_filter($columns, static fn (array $column): bool => ! preg_match('/\b(?:VIRTUAL|STORED) GENERATED\b/i', (string) $column['Extra'])));
                $create = $pdo->query('SHOW CREATE TABLE '.$identifier)->fetch(PDO::FETCH_NUM);
                $definitions[] = ['identifier' => $identifier, 'columns' => array_column($columns, 'Field'), 'create' => $create[1]];
            }

            $append('SET @DESEGO_BACKUP_TIMESTAMP = '.$pdo->quote(gmdate('Y-m-d H:i:s')).";\nSET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            foreach (array_reverse($definitions) as $table) {
                $append('DROP TABLE IF EXISTS '.$table['identifier'].";\n");
            }
            foreach ($definitions as $table) {
                $append($table['create'].";\n\n");
            }

            $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, false);
            foreach ($definitions as $table) {
                $columns = implode(', ', array_map(desego_sql_identifier(...), $table['columns']));
                $cursor = $pdo->query('SELECT '.$columns.' FROM '.$table['identifier']);
                try {
                    while ($row = $cursor->fetch(PDO::FETCH_NUM)) {
                        $values = array_map(static fn (mixed $value): string => desego_sql_value($pdo, $value), $row);
                        $append('INSERT INTO '.$table['identifier'].' ('.$columns.') VALUES ('.implode(', ', $values).");\n");
                        $rows++;
                    }
                } finally {
                    $cursor->closeCursor();
                }
            }
            $append("SET FOREIGN_KEY_CHECKS=1;\n");
            $pdo->commit();

            return ['tables' => count($definitions), 'rows' => $rows, 'bytes' => $bytes];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        } finally {
            $pdo->setAttribute(Mysql::ATTR_USE_BUFFERED_QUERY, $buffered);
        }
    }
}
