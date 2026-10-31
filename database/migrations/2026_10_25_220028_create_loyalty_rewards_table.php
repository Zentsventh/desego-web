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
        Schema::create('loyalty_rewards', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id')->startingValue(2);
            $table->string('reward_code', 32);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->enum('reward_type', ['discount', 'free_product'])->default('discount');
            $table->unsignedInteger('points_cost');
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('stock_limit')->nullable();
            $table->unsignedInteger('redeemed_count')->default(0);
            $table->dateTime('starts_at')->useCurrent();
            $table->dateTime('ends_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['reward_code'], 'uq_step9_loyalty_reward_code');
            $table->index(['status', 'starts_at', 'ends_at'], 'idx_step9_loyalty_rewards_state');
            $table->index(['product_id'], 'fk_step9_loyalty_reward_product');
            $table->index(['created_by'], 'fk_step9_loyalty_reward_created_by');
            $table->index(['updated_by'], 'fk_step9_loyalty_reward_updated_by');
            $table->foreign(['created_by'], 'fk_step9_loyalty_reward_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['product_id'], 'fk_step9_loyalty_reward_product')->references(['id'])->on('products')->onDelete('set null');
            $table->foreign(['updated_by'], 'fk_step9_loyalty_reward_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('loyalty_rewards');
    }
};
