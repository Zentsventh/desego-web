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
        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->enum('document_type', ['boleta', 'factura']);
            $table->string('series', 4);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['document_type']);
            $table->unique(['series'], 'uq_step11_invoice_series');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('invoice_sequences');
    }
};
