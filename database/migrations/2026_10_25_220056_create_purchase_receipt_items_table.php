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
        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('receipt_id');
            $table->unsignedBigInteger('purchase_order_item_id');
            $table->unsignedInteger('ingredient_id');
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->decimal('line_total', 14, 2);
            $table->decimal('stock_before', 14, 3);
            $table->decimal('stock_after', 14, 3);
            $table->dateTime('created_at')->useCurrent();
            $table->index(['receipt_id'], 'idx_step13_receipt_item_receipt');
            $table->index(['ingredient_id', 'created_at'], 'idx_step13_receipt_item_ingredient');
            $table->index(['purchase_order_item_id'], 'fk_step13_receipt_item_order_item');
            $table->foreign(['ingredient_id'], 'fk_step13_receipt_item_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['purchase_order_item_id'], 'fk_step13_receipt_item_order_item')->references(['id'])->on('purchase_order_items');
            $table->foreign(['receipt_id'], 'fk_step13_receipt_item_receipt')->references(['id'])->on('purchase_receipts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('purchase_receipt_items');
    }
};
