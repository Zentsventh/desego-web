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
        Schema::table('order_payment_attempts', function (Blueprint $table): void {
            $table->dateTime('expires_at')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_payment_attempts', function (Blueprint $table): void {
            $table->timestamp('expires_at')->useCurrent()->change();
        });
    }
};
