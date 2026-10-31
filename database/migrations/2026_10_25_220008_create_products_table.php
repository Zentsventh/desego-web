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
        Schema::create('products', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id')->startingValue(13);
            $table->unsignedInteger('category_id');
            $table->string('slug', 120);
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('emoji', 16)->default('?️');
            $table->enum('status', ['available', 'unavailable'])->default('available');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['slug'], 'uq_products_slug');
            $table->index(['category_id', 'status'], 'idx_products_category_status');
            $table->foreign(['category_id'], 'fk_products_category')->references(['id'])->on('categories');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('products');
    }
};
