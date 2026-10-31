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
        Schema::create('storefront_favorites', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->char('visitor_key', 64);
            $table->unsignedInteger('product_id');
            $table->dateTime('created_at')->useCurrent();
            $table->primary(['visitor_key', 'product_id']);
            $table->index(['product_id'], 'idx_step10_favorites_product');
            $table->foreign(['product_id'], 'fk_step10_favorite_product')->references(['id'])->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('storefront_favorites');
    }
};
