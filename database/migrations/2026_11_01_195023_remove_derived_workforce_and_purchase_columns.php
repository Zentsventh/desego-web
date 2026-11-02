<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->dropForeign('fk_attendance_shift_identity');
            $table->dropIndex('fk_attendance_shift_identity');
            $table->dropForeign('fk_step14_attendance_employee');
            $table->dropForeign('fk_step14_attendance_branch');
            $table->dropIndex('idx_step14_attendance_employee_date');
            $table->dropIndex('fk_step14_attendance_branch');
            $table->dropColumn(['employee_id', 'branch_id']);
            $table->dropForeign('fk_step14_attendance_shift');
            $table->foreign('shift_id', 'fk_step14_attendance_shift')->references('id')->on('employee_shifts');
        });
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->dropForeign('fk_receipt_order_supplier');
            $table->dropIndex('fk_receipt_order_supplier');
            $table->dropForeign('fk_step13_receipt_supplier');
            $table->dropIndex('fk_step13_receipt_supplier');
            $table->dropColumn('supplier_id');
        });
        Schema::table('purchase_receipt_items', function (Blueprint $table): void {
            $table->dropForeign('fk_receipt_item_ingredient');
            $table->dropIndex('fk_receipt_item_ingredient');
            $table->dropForeign('fk_step13_receipt_item_ingredient');
            $table->dropIndex('idx_step13_receipt_item_ingredient');
            $table->dropColumn('ingredient_id');
        });
        Schema::table('employee_shifts', fn (Blueprint $table) => $table->dropUnique('uq_shift_identity'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->dropUnique('uq_purchase_order_supplier'));
        Schema::table('purchase_order_items', fn (Blueprint $table) => $table->dropUnique('uq_purchase_item_ingredient'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_shifts', fn (Blueprint $table) => $table->unique(['id', 'employee_id', 'branch_id'], 'uq_shift_identity'));
        Schema::table('purchase_orders', fn (Blueprint $table) => $table->unique(['id', 'supplier_id'], 'uq_purchase_order_supplier'));
        Schema::table('purchase_order_items', fn (Blueprint $table) => $table->unique(['id', 'ingredient_id'], 'uq_purchase_item_ingredient'));
        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->dropForeign('fk_step14_attendance_shift');
            $table->foreign('shift_id', 'fk_step14_attendance_shift')->references('id')->on('employee_shifts')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable()->after('shift_id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('employee_id');
        });
        DB::statement('UPDATE attendance_events e JOIN employee_shifts s ON s.id=e.shift_id SET e.employee_id=s.employee_id,e.branch_id=s.branch_id');
        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->index(['employee_id', 'recorded_at'], 'idx_step14_attendance_employee_date');
            $table->foreign('employee_id', 'fk_step14_attendance_employee')->references('id')->on('employees');
            $table->foreign('branch_id', 'fk_step14_attendance_branch')->references('id')->on('branches');
            $table->foreign(['shift_id', 'employee_id', 'branch_id'], 'fk_attendance_shift_identity')->references(['id', 'employee_id', 'branch_id'])->on('employee_shifts');
        });
        Schema::table('purchase_receipts', fn (Blueprint $table) => $table->unsignedBigInteger('supplier_id')->nullable()->after('purchase_order_id'));
        DB::statement('UPDATE purchase_receipts r JOIN purchase_orders o ON o.id=r.purchase_order_id SET r.supplier_id=o.supplier_id');
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id', 'fk_step13_receipt_supplier')->references('id')->on('suppliers');
            $table->foreign(['purchase_order_id', 'supplier_id'], 'fk_receipt_order_supplier')->references(['id', 'supplier_id'])->on('purchase_orders');
        });
        Schema::table('purchase_receipt_items', fn (Blueprint $table) => $table->unsignedInteger('ingredient_id')->nullable()->after('purchase_order_item_id'));
        DB::statement('UPDATE purchase_receipt_items r JOIN purchase_order_items i ON i.id=r.purchase_order_item_id SET r.ingredient_id=i.ingredient_id');
        Schema::table('purchase_receipt_items', function (Blueprint $table): void {
            $table->unsignedInteger('ingredient_id')->nullable(false)->change();
            $table->index(['ingredient_id', 'created_at'], 'idx_step13_receipt_item_ingredient');
            $table->foreign('ingredient_id', 'fk_step13_receipt_item_ingredient')->references('id')->on('ingredients');
            $table->foreign(['purchase_order_item_id', 'ingredient_id'], 'fk_receipt_item_ingredient')->references(['id', 'ingredient_id'])->on('purchase_order_items');
        });
    }
};
