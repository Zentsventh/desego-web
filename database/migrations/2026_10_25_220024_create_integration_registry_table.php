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
        Schema::create('integration_registry', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id')->startingValue(6);
            $table->string('integration_code', 60);
            $table->string('name', 120);
            $table->enum('category', ['email', 'messaging', 'maps', 'payments', 'webhook', 'api', 'other'])->default('other');
            $table->string('description', 500)->nullable();
            $table->string('endpoint_url', 500)->nullable();
            $table->string('environment_key', 100)->nullable()->comment('Nombre de variable de entorno. Nunca guardar secretos aquí.');
            $table->boolean('is_enabled')->default(0);
            $table->enum('last_check_status', ['not_checked', 'configured', 'needs_configuration', 'invalid', 'error'])->default('not_checked');
            $table->string('last_check_message', 500)->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['integration_code'], 'uq_integration_registry_code');
            $table->index(['updated_by'], 'fk_integration_registry_updated_by');
            $table->foreign(['updated_by'], 'fk_integration_registry_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('integration_registry');
    }
};
