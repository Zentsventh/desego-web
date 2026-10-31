<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasOrphans = DB::table('sessions')->leftJoin('users', 'users.id', '=', 'sessions.user_id')
            ->whereNotNull('sessions.user_id')->whereNull('users.id')->exists();
        if ($hasOrphans) {
            throw new RuntimeException('Existen sesiones con usuarios inexistentes. Corrige esas referencias antes de migrar.');
        }
        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id', 'fk_sessions_user')->references('id')->on('users')->nullOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign('fk_sessions_user');
        });
    }
};
