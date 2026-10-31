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
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('order_number', 32);
            $table->unsignedBigInteger('supplier_id');
            $table->enum('status', ['draft', 'sent', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('supplier_reference', 120)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('shipping_cost', 14, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['order_number'], 'uq_step13_purchase_order_number');
            $table->index(['supplier_id', 'order_date'], 'idx_step13_po_supplier_date');
            $table->index(['status', 'order_date'], 'idx_step13_po_status_date');
            $table->index(['created_by'], 'fk_step13_po_created_by');
            $table->index(['sent_by'], 'fk_step13_po_sent_by');
            $table->index(['cancelled_by'], 'fk_step13_po_cancelled_by');
            $table->foreign(['cancelled_by'], 'fk_step13_po_cancelled_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['created_by'], 'fk_step13_po_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['sent_by'], 'fk_step13_po_sent_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['supplier_id'], 'fk_step13_po_supplier')->references(['id'])->on('suppliers');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('purchase_orders');
    }
};
