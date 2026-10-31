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
        Schema::create('ingredients', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id')->startingValue(33);
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('unit', 12)->default('g');
            $table->decimal('stock_quantity', 14, 3)->default(0.000);
            $table->decimal('min_stock', 14, 3)->default(0.000);
            $table->decimal('cost_per_unit', 14, 4)->default(0.0000);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['name'], 'uq_ingredients_name');
            $table->index(['status', 'stock_quantity', 'min_stock'], 'idx_ingredients_status_stock');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('ingredients');
    }
};
