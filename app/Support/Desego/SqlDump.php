<?php

namespace App\Support\Desego;

use InvalidArgumentException;

class SqlDump
{
    /** @return list<string> */
    public function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }

                continue;
            }
            if (($char === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? ' ')) || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
            } elseif ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new InvalidArgumentException('Comentario SQL sin cerrar.');
                }
                $i = $end + 1;
                $buffer .= ' ';
            } elseif (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $buffer .= $char;
            } elseif ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if ($quote !== null) {
            throw new InvalidArgumentException('Cadena SQL sin cerrar.');
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    /** @return array{schema: list<string>, data: list<string>, tables: list<string>} */
    public function parse(string $sql): array
    {
        $schema = [];
        $data = [];
        $tables = [];
        foreach ($this->statements($sql) as $statement) {
            if (preg_match('/^CREATE TABLE `([a-zA-Z0-9_]+)`\s*\(/i', $statement, $matches)) {
                $schema[] = $statement;
                $tables[] = $matches[1];
            } elseif (preg_match('/^ALTER TABLE `[a-zA-Z0-9_]+`\s+/i', $statement)) {
                $schema[] = $statement;
            } elseif (preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)`\s*\(/i', $statement, $matches)) {
                if (! in_array($matches[1], $tables, true)) {
                    throw new InvalidArgumentException('INSERT sin tabla declarada: '.$matches[1]);
                }
                $data[] = $statement;
            } elseif (! preg_match('/^(SET\s|START TRANSACTION$|COMMIT$)/i', $statement)) {
                throw new InvalidArgumentException('Sentencia SQL no admitida en el respaldo.');
            }
        }
        if ($tables === []) {
            throw new InvalidArgumentException('El respaldo no contiene tablas.');
        }

        return compact('schema', 'data', 'tables');
    }
}
