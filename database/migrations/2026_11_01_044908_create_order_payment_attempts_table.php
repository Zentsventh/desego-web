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
        Schema::create('order_payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('purchase_number', 12)->unique();
            $table->string('merchant_id', 30);
            $table->string('environment', 16);
            $table->string('status', 24)->default('creating');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('cash_session_id');
            $table->unsignedBigInteger('actor_id');
            $table->text('session_key')->nullable();
            $table->string('transaction_hash', 64)->nullable()->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_payment_attempts');
    }
};
