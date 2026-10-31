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
        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('receipt_number', 32);
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('supplier_id');
            $table->string('supplier_invoice', 120)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->dateTime('received_at')->useCurrent();
            $table->unique(['receipt_number'], 'uq_step13_receipt_number');
            $table->index(['purchase_order_id', 'received_at'], 'idx_step13_receipt_order');
            $table->index(['supplier_id'], 'fk_step13_receipt_supplier');
            $table->index(['received_by'], 'fk_step13_receipt_user');
            $table->foreign(['purchase_order_id'], 'fk_step13_receipt_order')->references(['id'])->on('purchase_orders');
            $table->foreign(['supplier_id'], 'fk_step13_receipt_supplier')->references(['id'])->on('suppliers');
            $table->foreign(['received_by'], 'fk_step13_receipt_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('purchase_receipts');
    }
};
