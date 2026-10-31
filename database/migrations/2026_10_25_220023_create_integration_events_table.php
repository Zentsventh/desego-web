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
        Schema::create('integration_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('integration_code', 60);
            $table->string('event_code', 80);
            $table->enum('status', ['success', 'warning', 'error']);
            $table->string('message', 500);
            $table->json('details_json')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['integration_code', 'created_at'], 'idx_integration_events_code_date');
            $table->index(['actor_id'], 'fk_integration_events_actor');
            $table->foreign(['actor_id'], 'fk_integration_events_actor')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('integration_events');
    }
};
