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
        Schema::create('api_rate_limit_windows', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedBigInteger('token_id');
            $table->dateTime('window_start');
            $table->unsignedInteger('request_count')->default(1);
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['token_id', 'window_start']);
            $table->index(['window_start'], 'idx_api_rate_window_start');
            $table->foreign(['token_id'], 'fk_api_rate_limit_token')->references(['id'])->on('api_access_tokens')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('api_rate_limit_windows');
    }
};
