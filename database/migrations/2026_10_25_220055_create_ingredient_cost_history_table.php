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
        Schema::create('ingredient_cost_history', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedInteger('ingredient_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('previous_unit_cost', 14, 4);
            $table->decimal('new_unit_cost', 14, 4);
            $table->decimal('quantity_received', 14, 3)->default(0.000);
            $table->enum('costing_method', ['FIFO', 'LIFO', 'AVG'])->default('FIFO');
            $table->string('reason', 255);
            $table->dateTime('created_at')->useCurrent();
            $table->index(['ingredient_id', 'created_at'], 'idx_step13_cost_history_ingredient_date');
            $table->index(['supplier_id', 'created_at'], 'idx_step13_cost_history_supplier_date');
            $table->index(['purchase_order_id'], 'fk_step13_cost_hist_order');
            $table->index(['receipt_id'], 'fk_step13_cost_hist_receipt');
            $table->index(['user_id'], 'fk_step13_cost_hist_user');
            $table->foreign(['ingredient_id'], 'fk_step13_cost_hist_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['purchase_order_id'], 'fk_step13_cost_hist_order')->references(['id'])->on('purchase_orders')->onDelete('set null');
            $table->foreign(['receipt_id'], 'fk_step13_cost_hist_receipt')->references(['id'])->on('purchase_receipts')->onDelete('set null');
            $table->foreign(['supplier_id'], 'fk_step13_cost_hist_supplier')->references(['id'])->on('suppliers')->onDelete('set null');
            $table->foreign(['user_id'], 'fk_step13_cost_hist_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('ingredient_cost_history');
    }
};
