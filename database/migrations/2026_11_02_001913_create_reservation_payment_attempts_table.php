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
        Schema::create('reservation_payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('hold_id');
            $table->string('purchase_number', 12)->unique();
            $table->string('merchant_id', 30);
            $table->string('environment', 16);
            $table->string('status', 24)->default('creating');
            $table->unsignedBigInteger('amount_cents');
            $table->text('session_key')->nullable();
            $table->string('transaction_hash', 64)->nullable()->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();
            $table->foreign('hold_id')->references('id')->on('reservation_holds')->cascadeOnDelete();
            $table->index(['hold_id', 'status'], 'idx_reservation_payment_attempt_hold_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_payment_attempts');
    }
};
