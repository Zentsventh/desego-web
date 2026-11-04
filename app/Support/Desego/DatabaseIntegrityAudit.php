<?php

namespace App\Support\Desego;

use App\Console\Commands\VerifyDesego;
use PDO;

class DatabaseIntegrityAudit
{
    /** @return array<string, mixed> */
    public function inspect(PDO $pdo): array
    {
        $issues = [];
        $tables = $this->rows($pdo, "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
        $columns = [];
        foreach ($this->rows($pdo, 'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLLATION_NAME, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION') as $column) {
            $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = $column;
        }
        $indexes = [];
        foreach ($this->rows($pdo, 'SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX') as $index) {
            $entry = &$indexes[$index['TABLE_NAME']][$index['INDEX_NAME']];
            $entry['unique'] = ! (bool) $index['NON_UNIQUE'];
            $entry['columns'][] = $index['COLUMN_NAME'];
            $entry['has_prefix'] = ($entry['has_prefix'] ?? false) || $index['SUB_PART'] !== null;
            unset($entry);
        }
        $foreignKeys = [];
        $referentialRules = [];
        foreach ($this->rows($pdo, 'SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()') as $rule) {
            $referentialRules[$rule['TABLE_NAME']][$rule['CONSTRAINT_NAME']] = $rule;
        }
        $sql = 'SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION';
        foreach ($this->rows($pdo, $sql) as $key) {
            $key += $referentialRules[$key['TABLE_NAME']][$key['CONSTRAINT_NAME']];
            $entry = &$foreignKeys[$key['TABLE_NAME']][$key['CONSTRAINT_NAME']];
            $entry['columns'][] = $key['COLUMN_NAME'];
            $entry['parent_table'] = $key['REFERENCED_TABLE_NAME'];
            $entry['parent_columns'][] = $key['REFERENCED_COLUMN_NAME'];
            $entry['on_delete'] = $key['DELETE_RULE'];
            $entry['on_update'] = $key['UPDATE_RULE'];
            unset($entry);
            $child = $columns[$key['TABLE_NAME']][$key['COLUMN_NAME']];
            $parent = $columns[$key['REFERENCED_TABLE_NAME']][$key['REFERENCED_COLUMN_NAME']];
            if ($this->normalizedType($child['COLUMN_TYPE']) !== $this->normalizedType($parent['COLUMN_TYPE']) || $child['COLLATION_NAME'] !== $parent['COLLATION_NAME']) {
                $issues[] = 'Tipos incompatibles: '.$key['TABLE_NAME'].'.'.$key['COLUMN_NAME'];
            }
            if (in_array('SET NULL', [$key['DELETE_RULE'], $key['UPDATE_RULE']], true) && $child['IS_NULLABLE'] !== 'YES') {
                $issues[] = 'SET NULL sobre columna obligatoria: '.$key['TABLE_NAME'].'.'.$key['COLUMN_NAME'];
            }
        }
        foreach ($foreignKeys as $table => $constraints) {
            foreach ($constraints as $name => $key) {
                $childCovered = false;
                $parentUnique = false;
                foreach ($indexes[$table] ?? [] as $index) {
                    if (! $index['has_prefix'] && array_slice($index['columns'], 0, count($key['columns'])) === $key['columns']) {
                        $childCovered = true;
                    }
                }
                foreach ($indexes[$key['parent_table']] ?? [] as $index) {
                    if ($index['unique'] && ! $index['has_prefix'] && $index['columns'] === $key['parent_columns']) {
                        $parentUnique = true;
                    }
                }
                if (! $childCovered) {
                    $issues[] = 'Relacion sin indice hijo: '.$table.'.'.$name;
                }
                if (! $parentUnique) {
                    $issues[] = 'Relacion sin clave unica padre: '.$table.'.'.$name;
                }
            }
        }
        $primaryKeys = 0;
        $uniqueKeys = 0;
        $indexCount = 0;
        $autoIncrement = 0;
        foreach ($tables as &$table) {
            $name = $table['TABLE_NAME'];
            $table['primary_key'] = $indexes[$name]['PRIMARY']['columns'] ?? [];
            $table['indexes'] = $indexes[$name] ?? [];
            $table['foreign_keys'] = $foreignKeys[$name] ?? [];
            $primaryKeys += $table['primary_key'] !== [] ? 1 : 0;
            $indexCount += count($table['indexes']);
            foreach ($table['indexes'] as $indexName => $index) {
                $uniqueKeys += $indexName !== 'PRIMARY' && $index['unique'] ? 1 : 0;
            }
            if ($table['primary_key'] === []) {
                $issues[] = 'Tabla sin clave primaria: '.$name;
            }
            if ($table['ENGINE'] !== 'InnoDB') {
                $issues[] = 'Motor sin integridad InnoDB: '.$name;
            }
            if (! str_starts_with((string) $table['TABLE_COLLATION'], 'utf8mb4_')) {
                $issues[] = 'Tabla sin utf8mb4: '.$name;
            }
            foreach ($columns[$name] as $column) {
                if (str_contains($column['EXTRA'], 'auto_increment')) {
                    $autoIncrement++;
                    $maximum = (int) $pdo->query('SELECT COALESCE(MAX('.$this->identifier($column['COLUMN_NAME']).'), 0) FROM '.$this->identifier($name))->fetchColumn();
                    if ($table['AUTO_INCREMENT'] === null || (int) $table['AUTO_INCREMENT'] <= $maximum) {
                        $issues[] = 'Secuencia AUTO_INCREMENT invalida: '.$name;
                    }
                }
            }
        }
        unset($table);
        $normalized = $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration='2026_11_01_195023_remove_derived_workforce_and_purchase_columns'")->fetchColumn() > 0;
        $retired = $normalized ? [
            'attendance_events' => ['idx_step14_attendance_employee_date', 'fk_step14_attendance_branch', 'fk_step14_attendance_employee'],
            'purchase_receipts' => ['fk_step13_receipt_supplier'],
            'purchase_receipt_items' => ['idx_step13_receipt_item_ingredient', 'fk_step13_receipt_item_ingredient'],
        ] : [];
        if ($normalized) {
            foreach (['reservation_hold_tables', 'dining_payment_attempt_orders', 'order_item_options'] as $table) {
                if (! isset($columns[$table])) {
                    $issues[] = 'Falta tabla normalizada: '.$table;
                }
            }
            foreach (['orders' => 'fk_order_member_session', 'dining_payment_attempts' => 'fk_dining_attempt_member_session', 'dining_requests' => 'fk_dining_request_member_session'] as $table => $constraint) {
                if (! isset($foreignKeys[$table][$constraint])) {
                    $issues[] = 'Falta relacion normalizada: '.$table.'.'.$constraint;
                }
            }
        }
        $expected = (new SqlDump)->parse(file_get_contents(database_path('desego/schema.sql')));
        foreach ($expected['tables'] as $name) {
            if (! isset($columns[$name])) {
                $issues[] = 'Falta tabla del negocio: '.$name;
            }
        }
        foreach ($expected['schema'] as $statement) {
            if (! preg_match('/^ALTER TABLE `([^`]+)`/i', $statement, $match)) {
                continue;
            }
            $name = $match[1];
            preg_match_all('/ADD (?:UNIQUE )?KEY `([^`]+)`/i', $statement, $matches);
            foreach ($matches[1] as $indexName) {
                if (! isset($indexes[$name][$indexName]) && ! in_array($indexName, $retired[$name] ?? [], true)) {
                    $issues[] = 'Falta indice original: '.$name.'.'.$indexName;
                }
            }
            preg_match_all('/ADD CONSTRAINT `([^`]+)`/i', $statement, $matches);
            foreach ($matches[1] as $constraintName) {
                if (! isset($foreignKeys[$name][$constraintName]) && ! in_array($constraintName, $retired[$name] ?? [], true)) {
                    $issues[] = 'Falta relacion original: '.$name.'.'.$constraintName;
                }
            }
        }
        if (! isset($foreignKeys['sessions']['fk_sessions_user'])) {
            $issues[] = 'Falta relacion sessions.user_id -> users.id';
        }
        $names = array_column($tables, 'TABLE_NAME');
        $data = VerifyDesego::inspect($pdo, $names);
        foreach ($data['orphans'] as $name => $count) {
            $issues[] = 'Registros huerfanos: '.$name.' ('.$count.')';
        }
        foreach ($tables as &$table) {
            $table['data'] = $data['tables'][$table['TABLE_NAME']];
        }
        unset($table);
        $foreignKeyChecks = (int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn();
        if ($foreignKeyChecks !== 1) {
            $issues[] = 'La conexion tiene FOREIGN_KEY_CHECKS desactivado';
        }
        $checks = [];
        foreach ($this->rows($pdo, 'SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()') as $check) {
            $checks[$check['TABLE_NAME']][$check['CONSTRAINT_NAME']] = $check['CHECK_CLAUSE'];
        }
        foreach (DatabaseIntegrityRules::checks() as $name => [$table, $expression]) {
            $actual = $checks[$table][$name] ?? null;
            if ($actual === null) {
                $issues[] = 'Falta restriccion CHECK: '.$table.'.'.$name;
            } elseif (DatabaseIntegrityRules::normalizedExpression($actual) !== DatabaseIntegrityRules::normalizedExpression($expression)) {
                $issues[] = 'Definicion CHECK inesperada: '.$table.'.'.$name;
            }
            if (isset($columns[$table]) && $pdo->query('SELECT 1 FROM '.$this->identifier($table).' WHERE NOT ('.$expression.') LIMIT 1')->fetchColumn() !== false) {
                $issues[] = 'Datos incompatibles con CHECK: '.$table.'.'.$name;
            }
        }
        foreach (DatabaseIntegrityRules::PAYMENT_RELATIONS as $name => [$table, $column, $parent]) {
            $key = $foreignKeys[$table][$name] ?? null;
            if ($key === null || $key['columns'] !== [$column] || $key['parent_table'] !== $parent || $key['parent_columns'] !== ['id'] || ! in_array($key['on_delete'], ['RESTRICT', 'NO ACTION'], true)) {
                $issues[] = 'Relacion de pago ausente o incompatible: '.$table.'.'.$name;
            }
        }
        $checkEnforcement = $pdo->query("SHOW SESSION VARIABLES LIKE 'check_constraint_checks'")->fetch(PDO::FETCH_ASSOC);
        if ($checkEnforcement !== false && ! in_array(strtoupper((string) $checkEnforcement['Value']), ['ON', '1'], true)) {
            $issues[] = 'La conexion tiene CHECK_CONSTRAINT_CHECKS desactivado';
        }
        $checkConstraints = array_sum(array_map('count', $checks));

        return [
            'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
            'audited_at_utc' => gmdate(DATE_ATOM),
            'healthy' => $issues === [],
            'summary' => ['tables' => count($tables), 'business_tables' => count($expected['tables']), 'primary_keys' => $primaryKeys, 'unique_keys' => $uniqueKeys, 'indexes' => $indexCount, 'foreign_keys' => $data['foreign_keys'], 'auto_increment_columns' => $autoIncrement, 'check_constraints' => $checkConstraints, 'rows' => array_sum(array_column($data['tables'], 'rows')), 'orphan_constraints' => count($data['orphans'])],
            'foreign_key_checks' => $foreignKeyChecks,
            'issues' => $issues,
            'tables' => $tables,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rows(PDO $pdo, string $sql): array
    {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizedType(string $type): string
    {
        return preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', strtolower($type));
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
