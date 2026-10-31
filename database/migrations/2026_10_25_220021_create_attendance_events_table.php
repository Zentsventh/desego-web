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
        Schema::create('attendance_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('shift_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('branch_id');
            $table->enum('event_type', ['clock_in', 'clock_out', 'manual_adjustment']);
            $table->dateTime('recorded_at');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->enum('source', ['web', 'admin'])->default('web');
            $table->string('notes', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['employee_id', 'recorded_at'], 'idx_step14_attendance_employee_date');
            $table->index(['shift_id', 'recorded_at'], 'idx_step14_attendance_shift');
            $table->index(['branch_id'], 'fk_step14_attendance_branch');
            $table->index(['recorded_by'], 'fk_step14_attendance_user');
            $table->foreign(['branch_id'], 'fk_step14_attendance_branch')->references(['id'])->on('branches');
            $table->foreign(['employee_id'], 'fk_step14_attendance_employee')->references(['id'])->on('employees');
            $table->foreign(['shift_id'], 'fk_step14_attendance_shift')->references(['id'])->on('employee_shifts')->onDelete('cascade');
            $table->foreign(['recorded_by'], 'fk_step14_attendance_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('attendance_events');
    }
};
