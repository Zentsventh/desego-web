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
        Schema::create('api_access_tokens', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('token_prefix', 20);
            $table->char('token_hash', 64);
            $table->json('scopes_json');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('revoked_at')->nullable();
            $table->unique(['token_hash'], 'uq_api_access_token_hash');
            $table->index(['status', 'expires_at'], 'idx_api_tokens_status_expiry');
            $table->index(['created_by'], 'fk_api_tokens_creator');
            $table->foreign(['created_by'], 'fk_api_tokens_creator')->references(['id'])->on('users');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('api_access_tokens');
    }
};
