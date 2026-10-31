<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->string('dining_qr_token', 64)->nullable()->unique();
        });
        Schema::create('dining_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedInteger('table_id');
            $table->unsignedInteger('active_table_id')->nullable()->unique();
            $table->string('status', 24)->default('open');
            $table->string('join_code_hash');
            $table->text('join_code_encrypted');
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();
            $table->foreign('table_id')->references('id')->on('restaurant_tables');
            $table->foreign('active_table_id')->references('id')->on('restaurant_tables');
        });
        Schema::create('dining_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dining_session_id');
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
            $table->foreign('dining_session_id')->references('id')->on('dining_sessions')->cascadeOnDelete();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('dining_session_id')->nullable()->index();
            $table->uuid('dining_member_id')->nullable();
            $table->foreign('dining_session_id')->references('id')->on('dining_sessions');
            $table->foreign('dining_member_id')->references('id')->on('dining_members');
        });
        Schema::create('dining_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('dining_session_id');
            $table->uuid('dining_member_id');
            $table->string('kind', 24);
            $table->string('status', 24)->default('pending');
            $table->string('notes', 300)->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->foreign('dining_session_id')->references('id')->on('dining_sessions')->cascadeOnDelete();
            $table->foreign('dining_member_id')->references('id')->on('dining_members');
            $table->index(['dining_session_id', 'status']);
        });
        Schema::create('dining_payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dining_session_id')->index();
            $table->uuid('dining_member_id');
            $table->string('purchase_number', 12)->unique();
            $table->string('status', 24)->default('creating');
            $table->unsignedInteger('amount_cents');
            $table->json('order_ids');
            $table->text('checkout_url')->nullable();
            $table->text('session_key')->nullable();
            $table->string('transaction_hash', 64)->nullable()->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->unsignedBigInteger('cash_session_id');
            $table->unsignedBigInteger('actor_id');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->foreign('dining_session_id')->references('id')->on('dining_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_payment_attempts');
        Schema::dropIfExists('dining_requests');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['dining_session_id']);
            $table->dropForeign(['dining_member_id']);
            $table->dropColumn(['dining_session_id', 'dining_member_id']);
        });
        Schema::dropIfExists('dining_members');
        Schema::dropIfExists('dining_sessions');
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->dropUnique(['dining_qr_token']);
            $table->dropColumn('dining_qr_token');
        });
    }
};
