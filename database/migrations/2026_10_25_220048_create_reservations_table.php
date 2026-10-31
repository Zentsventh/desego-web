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
        Schema::create('reservations', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('reservation_code', 32)->nullable();
            $table->string('customer_name', 100);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_email', 190)->nullable();
            $table->unsignedInteger('table_id');
            $table->unsignedTinyInteger('guest_count');
            $table->dateTime('reservation_at');
            $table->unsignedSmallInteger('duration_minutes')->default(120);
            $table->enum('status', ['Pendiente', 'Confirmada', 'Sentada', 'Completada', 'Cancelada', 'No asistió'])->default('Confirmada');
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['reservation_code'], 'uq_reservations_code');
            $table->index(['table_id', 'reservation_at', 'status'], 'idx_reservations_table_time');
            $table->index(['reservation_at', 'status'], 'idx_reservations_time_status');
            $table->index(['customer_phone'], 'idx_reservations_customer_phone');
            $table->index(['created_by'], 'fk_reservations_created_by');
            $table->index(['updated_by'], 'fk_reservations_updated_by');
            $table->index(['customer_id'], 'idx_reservations_customer_id');
            $table->foreign(['created_by'], 'fk_reservations_created_by')->references(['id'])->on('users');
            $table->foreign(['customer_id'], 'fk_reservations_customer')->references(['id'])->on('customers')->onDelete('set null');
            $table->foreign(['table_id'], 'fk_reservations_table')->references(['id'])->on('restaurant_tables');
            $table->foreign(['updated_by'], 'fk_reservations_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('reservations');
    }
};
