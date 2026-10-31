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
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->string('tax_id', 20)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->decimal('rating', 2, 1)->nullable()->comment('Valor manual opcional de 1 a 5 estrellas');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['tax_id'], 'uq_suppliers_tax_id');
            $table->index(['status', 'name'], 'idx_suppliers_status_name');
            $table->index(['email'], 'idx_suppliers_email');
            $table->index(['created_by'], 'fk_step13_supplier_created_by');
            $table->index(['updated_by'], 'fk_step13_supplier_updated_by');
            $table->foreign(['created_by'], 'fk_step13_supplier_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['updated_by'], 'fk_step13_supplier_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('suppliers');
    }
};
