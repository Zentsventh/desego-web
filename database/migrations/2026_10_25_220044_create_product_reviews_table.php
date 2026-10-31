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
        Schema::create('product_reviews', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('reviewer_name', 120);
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->string('comment', 1000);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('moderated_by')->nullable();
            $table->dateTime('moderated_at')->nullable();
            $table->string('moderator_note', 500)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['order_id', 'product_id'], 'uq_step10_review_order_product');
            $table->index(['product_id', 'status', 'created_at'], 'idx_step10_reviews_product_status');
            $table->index(['customer_id', 'created_at'], 'idx_step10_reviews_customer');
            $table->index(['status', 'created_at'], 'idx_step10_reviews_status_date');
            $table->index(['moderated_by'], 'fk_step10_review_moderator');
            $table->foreign(['customer_id'], 'fk_step10_review_customer')->references(['id'])->on('customers')->onDelete('cascade');
            $table->foreign(['moderated_by'], 'fk_step10_review_moderator')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['order_id'], 'fk_step10_review_order')->references(['id'])->on('orders')->onDelete('cascade');
            $table->foreign(['product_id'], 'fk_step10_review_product')->references(['id'])->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('product_reviews');
    }
};
