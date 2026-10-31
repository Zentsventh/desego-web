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
        Schema::create('orders', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('order_number', 24)->nullable();
            $table->string('customer_name', 100)->default('Consumidor final');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->enum('order_type', ['Mesa', 'Delivery', 'Para llevar'])->default('Mesa');
            $table->enum('order_channel', ['admin', 'web'])->default('admin');
            $table->string('web_checkout_key', 64)->nullable();
            $table->string('table_label', 40)->nullable();
            $table->string('notes', 500)->nullable();
            $table->enum('status', ['Pendiente', 'Preparando', 'Listo', 'En reparto', 'Entregado', 'Cancelado'])->default('Pendiente');
            $table->enum('payment_status', ['Pendiente', 'Pagado', 'Reembolsado'])->default('Pendiente');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_preference', 30)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->string('promotion_code', 40)->nullable();
            $table->decimal('total', 12, 2)->default(0.00);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('cash_session_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('updated_at');
            $table->unique(['order_number'], 'uq_orders_number');
            $table->unique(['web_checkout_key'], 'uq_orders_web_checkout_key');
            $table->index(['status', 'created_at'], 'idx_orders_status_date');
            $table->index(['payment_status', 'created_at'], 'idx_orders_payment_date');
            $table->index(['customer_name'], 'idx_orders_customer');
            $table->index(['created_by'], 'fk_orders_created_by');
            $table->index(['cash_session_id'], 'fk_orders_cash_session');
            $table->index(['customer_id'], 'idx_orders_customer_id');
            $table->index(['order_channel', 'created_at'], 'idx_orders_channel_date');
            $table->index(['promotion_id', 'created_at'], 'idx_orders_promotion');
            $table->foreign(['cash_session_id'], 'fk_orders_cash_session')->references(['id'])->on('cash_sessions')->onDelete('set null');
            $table->foreign(['created_by'], 'fk_orders_created_by')->references(['id'])->on('users');
            $table->foreign(['customer_id'], 'fk_orders_customer')->references(['id'])->on('customers')->onDelete('set null');
            $table->foreign(['promotion_id'], 'fk_orders_promotion')->references(['id'])->on('promotions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('orders');
    }
};
