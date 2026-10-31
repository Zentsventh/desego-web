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
        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('movement_type', ['income', 'expense']);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 120)->nullable();
            $table->string('notes', 500);
            $table->dateTime('created_at');
            $table->index(['session_id', 'created_at'], 'idx_cash_movements_session_date');
            $table->index(['user_id'], 'fk_cash_movements_user');
            $table->foreign(['session_id'], 'fk_cash_movements_session')->references(['id'])->on('cash_sessions');
            $table->foreign(['user_id'], 'fk_cash_movements_user')->references(['id'])->on('users');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('cash_movements');
    }
};
