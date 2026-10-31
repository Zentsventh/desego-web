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
        Schema::create('product_ingredients', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedInteger('product_id');
            $table->unsignedInteger('ingredient_id');
            $table->decimal('quantity', 14, 3)->default(0.000)->comment('Cantidad de ingrediente utilizada por una porción; usa la unidad base del ingrediente.');
            $table->primary(['product_id', 'ingredient_id']);
            $table->index(['ingredient_id'], 'idx_product_ingredients_ingredient');
            $table->foreign(['ingredient_id'], 'fk_product_ingredients_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['product_id'], 'fk_product_ingredients_product')->references(['id'])->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('product_ingredients');
    }
};
