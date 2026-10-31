<?php

use App\Support\Desego\LegacyDesegoMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name', 100);
            $table->text('message');
            $table->boolean('is_internal')->default(0);
            $table->dateTime('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at'], 'idx_support_messages_ticket');
            $table->index(['author_id'], 'fk_support_message_author');
            $table->foreign(['author_id'], 'fk_support_message_author')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['ticket_id'], 'fk_support_message_ticket')->references(['id'])->on('support_tickets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('support_ticket_messages');
    }
};
