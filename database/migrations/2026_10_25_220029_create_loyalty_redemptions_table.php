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
        Schema::create('loyalty_redemptions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->char('redemption_code', 12);
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('reward_id');
            $table->unsignedInteger('points_spent');
            $table->enum('status', ['issued', 'used', 'cancelled'])->default('issued');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();
            $table->dateTime('issued_at')->useCurrent();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->unique(['redemption_code'], 'uq_step9_loyalty_redemption_code');
            $table->index(['customer_id', 'issued_at'], 'idx_step9_redemption_customer');
            $table->index(['status', 'issued_at'], 'idx_step9_redemption_status');
            $table->index(['reward_id'], 'fk_step9_redemption_reward');
            $table->index(['issued_by'], 'fk_step9_redemption_issued_by');
            $table->index(['used_by'], 'fk_step9_redemption_used_by');
            $table->foreign(['customer_id'], 'fk_step9_redemption_customer')->references(['id'])->on('customers');
            $table->foreign(['issued_by'], 'fk_step9_redemption_issued_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['reward_id'], 'fk_step9_redemption_reward')->references(['id'])->on('loyalty_rewards');
            $table->foreign(['used_by'], 'fk_step9_redemption_used_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('loyalty_redemptions');
    }
};
