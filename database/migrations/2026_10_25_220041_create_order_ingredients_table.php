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
        Schema::create('order_ingredients', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('ingredient_id');
            $table->decimal('consumed_quantity', 14, 3);
            $table->string('unit', 12);
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->dateTime('restored_at')->nullable();
            $table->primary(['order_id', 'ingredient_id']);
            $table->index(['ingredient_id'], 'fk_order_ingredients_ingredient');
            $table->foreign(['ingredient_id'], 'fk_order_ingredients_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['order_id'], 'fk_order_ingredients_order')->references(['id'])->on('orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('order_ingredients');
    }
};
