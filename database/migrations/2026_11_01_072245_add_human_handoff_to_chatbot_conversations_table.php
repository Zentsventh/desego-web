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
            if (! Schema::hasColumn('chatbot_conversations', 'support_status')) {
                $table->string('support_status', 20)->default('ai')->after('archived_at');
            }
            if (! Schema::hasColumn('chatbot_conversations', 'handoff_reason')) {
                $table->string('handoff_reason', 40)->nullable()->after('support_status');
            }
            if (! Schema::hasColumn('chatbot_conversations', 'assigned_staff_id')) {
                $table->unsignedBigInteger('assigned_staff_id')->nullable()->after('handoff_reason');
                $table->foreign('assigned_staff_id', 'fk_chatbot_conversations_staff')
                    ->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('chatbot_conversations', 'interaction_started_at')) {
                $table->timestamp('interaction_started_at')->nullable()->after('assigned_staff_id');
            }
            if (! Schema::hasColumn('chatbot_conversations', 'last_customer_message_at')) {
                $table->timestamp('last_customer_message_at')->nullable()->after('interaction_started_at');
            }
            $table->index(['support_status', 'archived_at', 'updated_at'], 'idx_chatbot_support_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chatbot_conversations')) {
            return;
        }

        Schema::table('chatbot_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_chatbot_support_status');
            if (Schema::hasColumn('chatbot_conversations', 'assigned_staff_id')) {
                $table->dropForeign('fk_chatbot_conversations_staff');
                $table->dropColumn('assigned_staff_id');
            }
            foreach (['last_customer_message_at', 'interaction_started_at', 'handoff_reason', 'support_status'] as $column) {
                if (Schema::hasColumn('chatbot_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
