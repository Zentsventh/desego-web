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
        Schema::create('promotion_redemptions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('promotion_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->char('customer_key', 64)->nullable();
            $table->string('code_snapshot', 40)->nullable();
            $table->decimal('discount_amount', 12, 2);
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['order_id'], 'uq_promotion_redemptions_order');
            $table->index(['promotion_id', 'customer_key'], 'idx_promotion_redemptions_promo_customer');
            $table->index(['customer_id', 'created_at'], 'idx_promotion_redemptions_customer');
            $table->foreign(['customer_id'], 'fk_promotion_redemptions_customer')->references(['id'])->on('customers')->onDelete('set null');
            $table->foreign(['order_id'], 'fk_promotion_redemptions_order')->references(['id'])->on('orders')->onDelete('cascade');
            $table->foreign(['promotion_id'], 'fk_promotion_redemptions_promotion')->references(['id'])->on('promotions');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('promotion_redemptions');
    }
};
