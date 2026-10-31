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
        Schema::create('employees', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('employee_code', 30)->nullable();
            $table->string('full_name', 150);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('job_title', 100);
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'temporary', 'contractor'])->default('full_time');
            $table->date('hire_date')->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('notes', 1000)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['employee_code'], 'uq_step14_employee_code');
            $table->unique(['user_id'], 'uq_step14_employee_user');
            $table->index(['status', 'branch_id'], 'idx_step14_employee_status_branch');
            $table->index(['full_name'], 'idx_step14_employee_name');
            $table->index(['branch_id'], 'fk_step14_employee_branch');
            $table->index(['created_by'], 'fk_step14_employee_created_by');
            $table->index(['updated_by'], 'fk_step14_employee_updated_by');
            $table->foreign(['branch_id'], 'fk_step14_employee_branch')->references(['id'])->on('branches')->onDelete('set null');
            $table->foreign(['created_by'], 'fk_step14_employee_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['updated_by'], 'fk_step14_employee_updated_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['user_id'], 'fk_step14_employee_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('employees');
    }
};
