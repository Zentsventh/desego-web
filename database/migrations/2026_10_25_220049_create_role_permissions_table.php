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
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['role_id', 'permission_id']);
            $table->index(['permission_id'], 'fk_role_permissions_permission');
            $table->foreign(['permission_id'], 'fk_role_permissions_permission')->references(['id'])->on('permissions')->onDelete('cascade');
            $table->foreign(['role_id'], 'fk_role_permissions_role')->references(['id'])->on('roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (LegacyDesegoMigration::skipNativeMigration(pathinfo(__FILE__, PATHINFO_FILENAME))) {
            return;
        }
        Schema::dropIfExists('role_permissions');
    }
};
