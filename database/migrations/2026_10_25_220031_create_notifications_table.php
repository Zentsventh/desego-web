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
        Schema::create('notifications', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('recipient_user_id')->nullable()->comment('NULL significa aviso para todos los usuarios activos');
            $table->string('title', 150);
            $table->string('message', 1000);
            $table->enum('category', ['info', 'success', 'warning', 'error'])->default('info');
            $table->string('target_view', 40)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->index(['recipient_user_id', 'created_at'], 'idx_notifications_recipient_created');
            $table->index(['created_at'], 'idx_notifications_created');
            $table->index(['created_by'], 'fk_notifications_creator');
            $table->foreign(['created_by'], 'fk_notifications_creator')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['recipient_user_id'], 'fk_notifications_recipient')->references(['id'])->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('notifications');
    }
};
