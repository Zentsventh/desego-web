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
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id')->startingValue(32);
            $table->unsignedInteger('ingredient_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('movement_type', ['initial', 'entry', 'exit', 'adjustment', 'waste', 'sale']);
            $table->decimal('quantity_delta', 14, 3);
            $table->decimal('previous_quantity', 14, 3);
            $table->decimal('new_quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4)->default(0.0000);
            $table->string('reference', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->dateTime('created_at');
            $table->index(['ingredient_id', 'created_at'], 'idx_inventory_ingredient_date');
            $table->index(['movement_type', 'created_at'], 'idx_inventory_type_date');
            $table->index(['user_id'], 'fk_inventory_user');
            $table->foreign(['ingredient_id'], 'fk_inventory_ingredient')->references(['id'])->on('ingredients');
            $table->foreign(['user_id'], 'fk_inventory_user')->references(['id'])->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('inventory_movements');
    }
};
