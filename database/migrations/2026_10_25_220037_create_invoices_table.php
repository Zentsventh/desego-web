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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->enum('document_type', ['boleta', 'factura']);
            $table->string('series', 4);
            $table->unsignedBigInteger('sequential_number');
            $table->string('document_number', 24);
            $table->string('buyer_name', 160);
            $table->enum('buyer_document_type', ['DNI', 'RUC', 'CE', 'Pasaporte', 'Sin documento'])->default('Sin documento');
            $table->string('buyer_document', 20)->nullable();
            $table->string('buyer_address', 255)->nullable();
            $table->char('currency', 3)->default('PEN');
            $table->decimal('order_subtotal', 12, 2)->default(0.00);
            $table->decimal('order_discount', 12, 2)->default(0.00);
            $table->decimal('taxable_amount', 12, 2)->default(0.00);
            $table->decimal('igv_rate', 5, 2)->default(18.00);
            $table->decimal('igv_amount', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2)->default(0.00);
            $table->enum('status', ['issued', 'voided'])->default('issued');
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('issued_by');
            $table->dateTime('issued_at');
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['order_id'], 'uq_step11_invoice_order');
            $table->unique(['document_type', 'series', 'sequential_number'], 'uq_step11_invoice_number');
            $table->unique(['document_number'], 'uq_step11_invoice_document');
            $table->index(['issued_at', 'status'], 'idx_step11_invoice_date');
            $table->index(['buyer_document', 'buyer_name'], 'idx_step11_invoice_buyer');
            $table->index(['issued_by'], 'fk_step11_invoice_issuer');
            $table->index(['voided_by'], 'fk_step11_invoice_voider');
            $table->foreign(['issued_by'], 'fk_step11_invoice_issuer')->references(['id'])->on('users');
            $table->foreign(['order_id'], 'fk_step11_invoice_order')->references(['id'])->on('orders');
            $table->foreign(['voided_by'], 'fk_step11_invoice_voider')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('invoices');
    }
};
