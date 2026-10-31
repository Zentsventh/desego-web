<?php

namespace Tests\Unit;

use App\Support\Desego\SqlDump;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DesegoSqlDumpTest extends TestCase
{
    public function test_statement_parser_preserves_semicolons_quotes_comments_and_unicode_in_values(): void
    {
        $parser = new SqlDump;
        $sql = "-- initial comment\nCREATE TABLE `sample` (`id` int, `note` text);\n/* ignored ; */ INSERT INTO `sample` (`id`, `note`) VALUES (1, 'cafe; O\\'Brien -- # text'), (2, 'line one\nline two');";
        $dump = $parser->parse($sql);
        $this->assertSame(['sample'], $dump['tables']);
        $this->assertCount(1, $dump['data']);
        $this->assertStringContainsString("cafe; O\\'Brien -- # text", $dump['data'][0]);
        $this->assertStringContainsString("line one\nline two", $dump['data'][0]);
    }

    public function test_unknown_sql_is_rejected_before_import(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SqlDump)->parse('CREATE DATABASE other;');
    }

    public function test_unterminated_strings_are_rejected_before_import(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SqlDump)->statements("INSERT INTO `sample` VALUES ('unfinished);");
    }
}
