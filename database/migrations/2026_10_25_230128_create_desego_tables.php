<?php

use App\Support\Desego\LegacyDesegoMigration;
use App\Support\Desego\SqlDump;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (LegacyDesegoMigration::nativeBaselinePrecedesLegacy()) {
            return;
        }
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('DESEGO requiere MySQL/MariaDB para preservar el esquema original.');
        }
        $dump = app(SqlDump::class)->parse(file_get_contents(database_path('desego/schema.sql')));
        foreach ($dump['tables'] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('Ya existe la tabla '.$table.'. Utiliza una base de datos nueva.');
            }
        }
        foreach ($dump['schema'] as $statement) {
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::nativeBaselinePrecedesLegacy()) {
            return;
        }
        $dump = app(SqlDump::class)->parse(file_get_contents(database_path('desego/schema.sql')));
        Schema::withoutForeignKeyConstraints(function () use ($dump): void {
            foreach (array_reverse($dump['tables']) as $table) {
                Schema::dropIfExists($table);
            }
        });
    }
};
