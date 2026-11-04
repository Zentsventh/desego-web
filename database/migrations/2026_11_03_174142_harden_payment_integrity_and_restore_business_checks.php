<?php

use App\Support\Desego\DatabaseIntegrityRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->preflight();
        foreach (DatabaseIntegrityRules::checks() as $name => [$table, $expression]) {
            if (! $this->hasConstraint($table, $name)) {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
            }
        }
        foreach (DatabaseIntegrityRules::PAYMENT_RELATIONS as $name => [$table, $column, $parent]) {
            if (! $this->hasConstraint($table, $name)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($column, $name)->references('id')->on($parent)->restrictOnDelete());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DatabaseIntegrityRules::PAYMENT_RELATIONS as $name => [$table]) {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropForeign($name);
                $blueprint->dropIndex($name);
            });
        }
        foreach (DatabaseIntegrityRules::PAYMENT_CHECKS as $name => [$table]) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$name}`");
        }
    }

    private function preflight(): void
    {
        foreach (DatabaseIntegrityRules::checks() as $name => [$table, $expression]) {
            if (DB::table($table)->whereRaw("NOT ({$expression})")->exists()) {
                throw new RuntimeException("Datos incompatibles con {$name} en {$table}. Corrige los registros antes de migrar; no se eliminaran automaticamente.");
            }
        }
        foreach (DatabaseIntegrityRules::PAYMENT_RELATIONS as $name => [$table, $column, $parent]) {
            if (DB::table("{$table} as child")->leftJoin("{$parent} as parent", "child.{$column}", '=', 'parent.id')->whereNull('parent.id')->exists()) {
                throw new RuntimeException("Relacion inconsistente {$name}. Corrige los registros antes de migrar.");
            }
        }
    }

    private function hasConstraint(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $name)->exists();
    }
};
