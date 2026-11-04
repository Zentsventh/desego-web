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
        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id')->startingValue(49);
            $table->unsignedSmallInteger('table_number');
            $table->string('label', 40);
            $table->string('zone', 80)->default('');
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->enum('operational_status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->boolean('is_active')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['table_number'], 'uq_restaurant_tables_number');
            $table->unique(['label'], 'uq_restaurant_tables_label');
            $table->index(['is_active', 'operational_status', 'zone'], 'idx_restaurant_tables_status');
            $table->index(['created_by'], 'fk_restaurant_tables_created_by');
            $table->index(['updated_by'], 'fk_restaurant_tables_updated_by');
            $table->foreign(['created_by'], 'fk_restaurant_tables_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['updated_by'], 'fk_restaurant_tables_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('restaurant_tables');
    }
};
