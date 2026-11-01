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
        if (! Schema::hasTable('user_preferences')) {
            return;
        }

        Schema::table('user_preferences', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_preferences', 'account_order_emails')) {
                $table->boolean('account_order_emails')->nullable();
            }
            if (! Schema::hasColumn('user_preferences', 'account_promo_emails')) {
                $table->boolean('account_promo_emails')->nullable();
            }
            if (! Schema::hasColumn('user_preferences', 'account_preferred_payment')) {
                $table->string('account_preferred_payment', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('user_preferences')) {
            return;
        }

        Schema::table('user_preferences', function (Blueprint $table): void {
            foreach (['account_order_emails', 'account_promo_emails', 'account_preferred_payment'] as $column) {
                if (Schema::hasColumn('user_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
