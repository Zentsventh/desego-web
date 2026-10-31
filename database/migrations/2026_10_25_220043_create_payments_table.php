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
        Schema::create('payments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('cash_session_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('payment_type', ['payment', 'refund'])->default('payment');
            $table->enum('method', ['Efectivo', 'Yape/Plin', 'Tarjeta', 'Transferencia']);
            $table->decimal('amount', 12, 2);
            $table->string('notes', 255)->nullable();
            $table->dateTime('created_at');
            $table->index(['cash_session_id', 'created_at'], 'idx_payments_session_date');
            $table->index(['order_id', 'payment_type'], 'idx_payments_order');
            $table->index(['method', 'created_at'], 'idx_payments_method_date');
            $table->index(['user_id'], 'fk_payments_user');
            $table->foreign(['cash_session_id'], 'fk_payments_cash_session')->references(['id'])->on('cash_sessions');
            $table->foreign(['order_id'], 'fk_payments_order')->references(['id'])->on('orders');
            $table->foreign(['user_id'], 'fk_payments_user')->references(['id'])->on('users');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('payments');
    }
};
