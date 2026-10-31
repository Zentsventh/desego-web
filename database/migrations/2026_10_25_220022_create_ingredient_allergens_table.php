<?php

use App\Support\Desego\LegacyDesegoMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::create('ingredient_allergens', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedInteger('ingredient_id');
            $table->unsignedInteger('allergen_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['ingredient_id', 'allergen_id']);
            $table->index(['allergen_id'], 'fk_ingredient_allergens_allergen');
            $table->foreign(['allergen_id'], 'fk_ingredient_allergens_allergen')->references(['id'])->on('allergens')->onDelete('cascade');
            $table->foreign(['ingredient_id'], 'fk_ingredient_allergens_ingredient')->references(['id'])->on('ingredients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('ingredient_allergens');
    }
};
