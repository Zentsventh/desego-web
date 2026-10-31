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
        Schema::create('customers', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('customer_code', 24)->nullable();
            $table->string('full_name', 120);
            $table->enum('document_type', ['DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO', 'SIN_DOCUMENTO'])->default('SIN_DOCUMENTO');
            $table->string('document_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['customer_code'], 'uq_customers_code');
            $table->unique(['document_type', 'document_number'], 'uq_customers_document');
            $table->index(['full_name'], 'idx_customers_name');
            $table->index(['phone'], 'idx_customers_phone');
            $table->index(['email'], 'idx_customers_email');
            $table->index(['status'], 'idx_customers_status');
            $table->index(['created_by'], 'fk_customers_created_by');
            $table->index(['updated_by'], 'fk_customers_updated_by');
            $table->foreign(['created_by'], 'fk_customers_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['updated_by'], 'fk_customers_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('customers');
    }
};
