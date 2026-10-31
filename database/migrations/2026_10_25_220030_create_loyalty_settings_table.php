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
        Schema::create('loyalty_settings', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedTinyInteger('id');
            $table->decimal('soles_per_point', 10, 2)->default(10.00);
            $table->decimal('minimum_eligible_amount', 12, 2)->default(0.00);
            $table->unsignedInteger('points_expiry_days')->default(0)->comment('0 = los puntos no vencen automáticamente en esta versión');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['id']);
            $table->index(['updated_by'], 'fk_step9_loyalty_settings_user');
            $table->foreign(['updated_by'], 'fk_step9_loyalty_settings_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('loyalty_settings');
    }
};
