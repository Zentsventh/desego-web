<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_messages')) {
            return;
        }

        Schema::table('chatbot_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('chatbot_messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('is_error');
            }
            if (! Schema::hasColumn('chatbot_messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chatbot_messages')) {
            return;
        }

        Schema::table('chatbot_messages', function (Blueprint $table): void {
            foreach (['read_at', 'delivered_at'] as $column) {
                if (Schema::hasColumn('chatbot_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
