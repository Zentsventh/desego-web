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
        Schema::create('promotions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->enum('promotion_type', ['coupon', 'automatic'])->default('coupon');
            $table->string('code', 40)->nullable();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->decimal('min_subtotal', 10, 2)->default(0.00);
            $table->dateTime('starts_at')->useCurrent();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['code'], 'uq_promotions_code');
            $table->index(['status', 'starts_at', 'ends_at'], 'idx_promotions_status_dates');
            $table->index(['promotion_type', 'status'], 'idx_promotions_type_status');
            $table->index(['created_by'], 'fk_promotions_created_by');
            $table->index(['updated_by'], 'fk_promotions_updated_by');
            $table->foreign(['created_by'], 'fk_promotions_created_by')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['updated_by'], 'fk_promotions_updated_by')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('promotions');
    }
};
