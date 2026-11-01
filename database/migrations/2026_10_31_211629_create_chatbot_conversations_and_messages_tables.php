<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('owner_hash', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title', 120);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('updated_at');
        });
        Schema::create('chatbot_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('conversation_id');
            $table->string('role', 16);
            $table->text('content');
            $table->string('source', 24)->nullable();
            $table->string('model', 100)->nullable();
            $table->boolean('is_error')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('conversation_id')->references('id')->on('chatbot_conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
        Schema::dropIfExists('chatbot_conversations');
    }
};
