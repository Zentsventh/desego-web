<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_tables', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_id');
            $table->unsignedInteger('table_id');
            $table->primary(['reservation_id', 'table_id']);
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
            $table->foreign('table_id')->references('id')->on('restaurant_tables')->restrictOnDelete();
        });
        Schema::create('reservation_table_settings', function (Blueprint $table) {
            $table->unsignedInteger('table_id')->primary();
            $table->string('join_group', 60)->nullable();
            $table->foreign('table_id')->references('id')->on('restaurant_tables')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_table_settings');
        Schema::dropIfExists('reservation_tables');
    }
};
