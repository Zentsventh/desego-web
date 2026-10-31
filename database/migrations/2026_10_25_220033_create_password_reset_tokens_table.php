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
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->char('email_hash', 64);
            $table->char('token_hash', 64)->nullable();
            $table->char('request_ip_hash', 64);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['token_hash'], 'uq_password_reset_token_hash');
            $table->index(['user_id', 'created_at'], 'idx_password_reset_user_created');
            $table->index(['request_ip_hash', 'created_at'], 'idx_password_reset_ip_created');
            $table->index(['email_hash', 'created_at'], 'idx_password_reset_email_created');
            $table->index(['expires_at', 'used_at'], 'idx_password_reset_expiry');
            $table->foreign(['user_id'], 'fk_password_reset_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('password_reset_tokens');
    }
};
