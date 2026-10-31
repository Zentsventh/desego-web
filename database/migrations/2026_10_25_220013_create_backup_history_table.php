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
        Schema::create('backup_history', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('file_name', 180)->nullable();
            $table->string('secondary_file_name', 180)->nullable();
            $table->enum('backup_kind', ['manual', 'automatic', 'pre_restore'])->default('manual');
            $table->enum('status', ['completed', 'failed', 'deleted'])->default('completed');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('sha256_hash', 64)->nullable();
            $table->boolean('is_encrypted')->default(1);
            $table->enum('verification_status', ['pending', 'valid', 'invalid', 'deleted', 'not_available'])->default('pending');
            $table->dateTime('verified_at')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['created_at', 'status'], 'idx_step12_backup_date');
            $table->index(['status', 'backup_kind'], 'idx_step12_backup_status');
            $table->index(['created_by'], 'fk_step12_backup_user');
            $table->foreign(['created_by'], 'fk_step12_backup_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('backup_history');
    }
};
