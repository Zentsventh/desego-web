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
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->bigIncrements('id');
            $table->string('ticket_number', 24)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('subject', 180);
            $table->enum('category', ['technical', 'orders', 'inventory', 'billing', 'access', 'other'])->default('other');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])->default('open');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('resolved_at')->nullable();
            $table->unique(['ticket_number'], 'uq_support_ticket_number');
            $table->index(['status', 'priority', 'updated_at'], 'idx_support_ticket_status_priority');
            $table->index(['created_by', 'updated_at'], 'idx_support_ticket_owner');
            $table->index(['assigned_to'], 'fk_support_ticket_assignee');
            $table->foreign(['assigned_to'], 'fk_support_ticket_assignee')->references(['id'])->on('users')->onDelete('set null');
            $table->foreign(['created_by'], 'fk_support_ticket_creator')->references(['id'])->on('users');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('support_tickets');
    }
};
