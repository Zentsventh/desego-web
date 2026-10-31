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
        Schema::create('inventory_cost_layers', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id')->startingValue(32);
            $table->unsignedInteger('ingredient_id');
            $table->enum('layer_type', ['opening', 'purchase', 'adjustment'])->default('purchase');
            $table->unsignedBigInteger('receipt_item_id')->nullable();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_remaining', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->string('notes', 255)->nullable();
            $table->dateTime('received_at')->useCurrent();
            $table->unique(['receipt_item_id'], 'uq_step13_cost_layer_receipt_item');
            $table->index(['ingredient_id', 'received_at', 'id'], 'idx_step13_cost_layer_fifo');
            $table->index(['ingredient_id', 'quantity_remaining'], 'idx_step13_cost_layer_remaining');
            $table->foreign(['ingredient_id'], 'fk_step13_cost_layer_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['receipt_item_id'], 'fk_step13_cost_layer_receipt_item')->references(['id'])->on('purchase_receipt_items')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('inventory_cost_layers');
    }
};
