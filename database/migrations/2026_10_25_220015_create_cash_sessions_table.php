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
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->decimal('opening_amount', 12, 2)->default(0.00);
            $table->decimal('expected_closing', 12, 2)->nullable();
            $table->decimal('counted_closing', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->string('opening_notes', 500)->nullable();
            $table->string('closing_notes', 500)->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->index(['status', 'opened_at'], 'idx_cash_sessions_status');
            $table->index(['opened_at'], 'idx_cash_sessions_date');
            $table->index(['opened_by'], 'fk_cash_sessions_opened_by');
            $table->index(['closed_by'], 'fk_cash_sessions_closed_by');
            $table->foreign(['closed_by'], 'fk_cash_sessions_closed_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['opened_by'], 'fk_cash_sessions_opened_by')->references(['id'])->on('users');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('cash_sessions');
    }
};
