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
        Schema::create('order_delivery_details', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_address_id')->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('zone', 100)->nullable();
            $table->unsignedBigInteger('courier_user_id')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->enum('status', ['Pendiente', 'Asignado', 'En reparto', 'Entregado', 'Incidencia'])->default('Pendiente');
            $table->dateTime('estimated_delivery_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->string('incident_notes', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['order_id'], 'uq_order_delivery_order');
            $table->index(['status', 'updated_at'], 'idx_delivery_status_updated');
            $table->index(['courier_user_id', 'status'], 'idx_delivery_courier_status');
            $table->index(['customer_address_id'], 'idx_order_delivery_customer_address');
            $table->foreign(['courier_user_id'], 'fk_delivery_courier')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['order_id'], 'fk_delivery_order')->references(['id'])->on('orders')->onDelete('cascade');
            $table->foreign(['customer_address_id'], 'fk_order_delivery_customer_address')->references(['id'])->on('customer_addresses')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('order_delivery_details');
    }
};
