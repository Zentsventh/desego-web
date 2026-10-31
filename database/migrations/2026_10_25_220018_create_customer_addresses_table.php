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
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->string('label', 60)->default('Casa');
            $table->string('recipient_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address_line', 255);
            $table->string('reference', 255)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['customer_id', 'status', 'is_default'], 'idx_customer_addresses_customer');
            $table->index(['district'], 'idx_customer_addresses_district');
            $table->index(['created_by'], 'fk_customer_addresses_created_by');
            $table->index(['updated_by'], 'fk_customer_addresses_updated_by');
            $table->foreign(['created_by'], 'fk_customer_addresses_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['customer_id'], 'fk_customer_addresses_customer')->references(['id'])->on('customers')->onDelete('cascade');
            $table->foreign(['updated_by'], 'fk_customer_addresses_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('customer_addresses');
    }
};
