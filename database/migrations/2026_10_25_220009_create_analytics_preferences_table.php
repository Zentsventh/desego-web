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
        Schema::create('analytics_preferences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedBigInteger('user_id');
            $table->string('metric_code', 50);
            $table->boolean('is_visible')->default(1);
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['user_id', 'metric_code']);
            $table->foreign(['user_id'], 'fk_step12_analytics_pref_user')->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('analytics_preferences');
    }
};
