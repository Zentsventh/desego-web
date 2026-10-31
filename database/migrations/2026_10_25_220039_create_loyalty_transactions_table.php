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
        Schema::create('loyalty_transactions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('reward_id')->nullable();
            $table->unsignedBigInteger('redemption_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('transaction_type', ['earn', 'redeem', 'adjustment', 'order_refund', 'reward_cancel']);
            $table->integer('points_delta');
            $table->integer('balance_after');
            $table->string('description', 255);
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['order_id', 'transaction_type'], 'uq_step9_loyalty_order_type');
            $table->index(['customer_id', 'created_at'], 'idx_step9_loyalty_customer_date');
            $table->index(['transaction_type', 'created_at'], 'idx_step9_loyalty_type_date');
            $table->index(['reward_id'], 'fk_step9_loyalty_tx_reward');
            $table->index(['redemption_id'], 'fk_step9_loyalty_tx_redemption');
            $table->index(['user_id'], 'fk_step9_loyalty_tx_user');
            $table->foreign(['customer_id'], 'fk_step9_loyalty_tx_customer')->references(['id'])->on('customers')->onDelete('cascade');
            $table->foreign(['order_id'], 'fk_step9_loyalty_tx_order')->references(['id'])->on('orders')->onDelete('set null');
            $table->foreign(['redemption_id'], 'fk_step9_loyalty_tx_redemption')->references(['id'])->on('loyalty_redemptions')->onDelete('set null');
            $table->foreign(['reward_id'], 'fk_step9_loyalty_tx_reward')->references(['id'])->on('loyalty_rewards')->onDelete('set null');
            $table->foreign(['user_id'], 'fk_step9_loyalty_tx_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('loyalty_transactions');
    }
};
