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
        Schema::create('employee_shifts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('branch_id');
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['shift_date', 'status'], 'idx_step14_shift_date_status');
            $table->index(['employee_id', 'shift_date', 'status'], 'idx_step14_shift_employee_date');
            $table->index(['branch_id', 'shift_date'], 'idx_step14_shift_branch_date');
            $table->index(['created_by'], 'fk_step14_shift_created_by');
            $table->index(['updated_by'], 'fk_step14_shift_updated_by');
            $table->foreign(['branch_id'], 'fk_step14_shift_branch')->references(['id'])->on('branches');
            $table->foreign(['created_by'], 'fk_step14_shift_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['employee_id'], 'fk_step14_shift_employee')->references(['id'])->on('employees');
            $table->foreign(['updated_by'], 'fk_step14_shift_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('employee_shifts');
    }
};
