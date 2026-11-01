<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chatbot_visitors')) {
            return;
        }

        Schema::create('chatbot_visitors', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('owner_hash', 64)->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('chatbot_visitors')->insertUsing(
            ['owner_hash', 'created_at'],
            DB::table('chatbot_conversations')
                ->selectRaw('owner_hash, MIN(created_at) as created_at')
                ->groupBy('owner_hash')
                ->orderByRaw('MIN(created_at), owner_hash'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_visitors');
    }
};
