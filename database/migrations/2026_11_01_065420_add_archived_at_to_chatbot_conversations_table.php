<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_conversations')) {
            return;
        }

        Schema::table('chatbot_conversations', function (Blueprint $table): void {
            if (! Schema::hasColumn('chatbot_conversations', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('chatbot_conversations', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('user_id');
                $table->foreign('customer_id', 'fk_chatbot_conversations_customer')
                    ->references('id')->on('customers')->nullOnDelete();
                $table->index(['customer_id', 'archived_at', 'updated_at'], 'idx_chatbot_customer_archived');
            }
            $table->index(['owner_hash', 'archived_at', 'updated_at'], 'idx_chatbot_owner_archived');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chatbot_conversations')) {
            return;
        }

        Schema::table('chatbot_conversations', function (Blueprint $table): void {
            $table->dropIndex('idx_chatbot_owner_archived');
            if (Schema::hasColumn('chatbot_conversations', 'customer_id')) {
                $table->dropForeign('fk_chatbot_conversations_customer');
                $table->dropIndex('idx_chatbot_customer_archived');
                $table->dropColumn('customer_id');
            }
            if (Schema::hasColumn('chatbot_conversations', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
