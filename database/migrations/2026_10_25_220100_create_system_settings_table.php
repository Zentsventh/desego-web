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
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->string('setting_key', 80);
            $table->string('setting_value', 500);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['setting_key']);
            $table->index(['updated_by'], 'fk_step12_setting_user');
            $table->foreign(['updated_by'], 'fk_step12_setting_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('system_settings');
    }
};
