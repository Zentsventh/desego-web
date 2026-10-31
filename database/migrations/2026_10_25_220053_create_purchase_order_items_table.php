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
        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedInteger('ingredient_id');
            $table->string('ingredient_name_snapshot', 100);
            $table->string('unit', 12);
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0.000);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->string('notes', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['purchase_order_id'], 'idx_step13_poi_order');
            $table->index(['ingredient_id'], 'idx_step13_poi_ingredient');
            $table->foreign(['ingredient_id'], 'fk_step13_poi_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['purchase_order_id'], 'fk_step13_poi_order')->references(['id'])->on('purchase_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('purchase_order_items');
    }
};
