<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> */
    private const CHECKS = [
        'chk_order_item_values' => ['order_items', 'quantity > 0 AND unit_price >= 0 AND line_total >= 0'],
        'chk_product_price' => ['products', 'price >= 0'],
        'chk_payment_amount' => ['payments', 'amount > 0'],
        'chk_cash_movement_amount' => ['cash_movements', 'amount > 0'],
        'chk_promotion_values' => ['promotions', "discount_value > 0 AND (discount_type <> 'percentage' OR discount_value <= 100) AND min_subtotal >= 0 AND (max_discount IS NULL OR max_discount >= 0) AND (ends_at IS NULL OR ends_at >= starts_at)"],
        'chk_reservation_values' => ['reservations', 'guest_count > 0 AND duration_minutes > 0 AND advance_amount >= 0 AND applied_credit_amount >= 0'],
        'chk_reservation_payment_values' => ['reservation_payments', 'amount > 0 AND refund_amount >= 0 AND refund_amount <= amount'],
        'chk_reservation_hold_values' => ['reservation_holds', 'guest_count > 0 AND duration_minutes > 0 AND advance_amount >= 0'],
        'chk_table_capacity' => ['restaurant_tables', 'capacity > 0'],
        'chk_ingredient_costs' => ['ingredients', 'min_stock >= 0 AND cost_per_unit >= 0'],
        'chk_cost_layer_values' => ['inventory_cost_layers', 'quantity_received >= 0 AND quantity_remaining >= 0 AND quantity_remaining <= quantity_received AND unit_cost >= 0'],
        'chk_purchase_item_values' => ['purchase_order_items', 'ordered_quantity > 0 AND received_quantity >= 0 AND unit_cost >= 0 AND line_total >= 0'],
        'chk_receipt_item_values' => ['purchase_receipt_items', 'quantity > 0 AND unit_cost >= 0 AND line_total >= 0'],
        'chk_review_rating' => ['product_reviews', 'rating BETWEEN 1 AND 5'],
        'chk_supplier_rating' => ['suppliers', 'rating IS NULL OR rating BETWEEN 1 AND 5'],
        'chk_address_coordinates' => ['customer_addresses', '(latitude IS NULL OR latitude BETWEEN -90 AND 90) AND (longitude IS NULL OR longitude BETWEEN -180 AND 180)'],
        'chk_delivery_zone_values' => ['delivery_zones', 'center_latitude BETWEEN -90 AND 90 AND center_longitude BETWEEN -180 AND 180 AND radius_km > 0 AND delivery_fee >= 0 AND min_order_amount >= 0 AND estimated_minutes_max >= estimated_minutes_min'],
        'chk_business_day' => ['business_hours', 'day_of_week BETWEEN 1 AND 7'],
        'chk_order_member_session' => ['orders', 'dining_member_id IS NULL OR dining_session_id IS NOT NULL'],
    ];

    /** @var array<string, array{0: string, 1: list<string>, 2: string, 3: list<string>}> */
    private const RELATIONS = [
        'fk_dining_attempt_member_session' => ['dining_payment_attempts', ['dining_member_id', 'dining_session_id'], 'dining_members', ['id', 'dining_session_id']],
        'fk_dining_attempt_cash' => ['dining_payment_attempts', ['cash_session_id'], 'cash_sessions', ['id']],
        'fk_dining_attempt_actor' => ['dining_payment_attempts', ['actor_id'], 'users', ['id']],
        'fk_dining_request_handler' => ['dining_requests', ['handled_by'], 'users', ['id']],
        'fk_dining_request_member_session' => ['dining_requests', ['dining_member_id', 'dining_session_id'], 'dining_members', ['id', 'dining_session_id']],
        'fk_order_member_session' => ['orders', ['dining_member_id', 'dining_session_id'], 'dining_members', ['id', 'dining_session_id']],
        'fk_reservation_dining_session' => ['reservations', ['dining_session_id'], 'dining_sessions', ['id']],
        'fk_reservation_order' => ['reservations', ['order_id'], 'orders', ['id']],
        'fk_hold_reservation' => ['reservation_holds', ['reservation_id'], 'reservations', ['id']],
        'fk_reservation_refunded_by' => ['reservation_payments', ['refunded_by'], 'users', ['id']],
        'fk_attendance_shift_identity' => ['attendance_events', ['shift_id', 'employee_id', 'branch_id'], 'employee_shifts', ['id', 'employee_id', 'branch_id']],
        'fk_receipt_order_supplier' => ['purchase_receipts', ['purchase_order_id', 'supplier_id'], 'purchase_orders', ['id', 'supplier_id']],
        'fk_receipt_item_ingredient' => ['purchase_receipt_items', ['purchase_order_item_id', 'ingredient_id'], 'purchase_order_items', ['id', 'ingredient_id']],
    ];

    /** @var array<string, array{0: string, 1: list<string>}> */
    private const UNIQUE_KEYS = [
        'uq_dining_member_session' => ['dining_members', ['id', 'dining_session_id']],
        'uq_shift_identity' => ['employee_shifts', ['id', 'employee_id', 'branch_id']],
        'uq_purchase_order_supplier' => ['purchase_orders', ['id', 'supplier_id']],
        'uq_purchase_item_ingredient' => ['purchase_order_items', ['id', 'ingredient_id']],
        'uq_business_hours_day' => ['business_hours', ['day_of_week']],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->preflight();
        foreach (self::UNIQUE_KEYS as $name => [$tableName, $columns]) {
            if (! Schema::hasIndex($tableName, $name)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unique($columns, $name));
            }
        }
        foreach (self::RELATIONS as $name => [$tableName, $columns, $parent, $parentColumns]) {
            if (! $this->hasConstraint($tableName, $name)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->foreign($columns, $name)->references($parentColumns)->on($parent));
            }
        }
        foreach (self::CHECKS as $name => [$tableName, $expression]) {
            if (! $this->hasConstraint($tableName, $name)) {
                DB::statement("ALTER TABLE `{$tableName}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
            }
        }
        if (! Schema::hasColumn('customer_addresses', 'default_customer_id')) {
            Schema::table('customer_addresses', function (Blueprint $table): void {
                $table->unsignedBigInteger('default_customer_id')->nullable()->virtualAs("CASE WHEN is_default = 1 AND status = 'active' THEN customer_id ELSE NULL END");
            });
        }
        if (! Schema::hasIndex('customer_addresses', 'uq_customer_default_address')) {
            Schema::table('customer_addresses', fn (Blueprint $table) => $table->unique('default_customer_id', 'uq_customer_default_address'));
        }
        Schema::table('reservation_payments', function (Blueprint $table): void {
            $table->dropForeign('reservation_payments_reservation_id_foreign');
            $table->foreign('reservation_id')->references('id')->on('reservations');
        });
    }

    private function hasConstraint(string $table, string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->where('CONSTRAINT_NAME', $name)->exists();
    }

    private function preflight(): void
    {
        foreach (self::CHECKS as $name => [$table, $expression]) {
            if (DB::table($table)->whereRaw("NOT ({$expression})")->exists()) {
                throw new RuntimeException("Datos incompatibles con {$name} en {$table}. Corrige los registros antes de migrar; no se eliminarán automáticamente.");
            }
        }
        foreach (self::RELATIONS as $name => [$table, $columns, $parent, $parentColumns]) {
            $join = [];
            $notNull = [];
            foreach ($columns as $position => $column) {
                $join[] = "c.`{$column}` = p.`{$parentColumns[$position]}`";
                $notNull[] = "c.`{$column}` IS NOT NULL";
            }
            $invalid = DB::selectOne("SELECT 1 FROM `{$table}` c LEFT JOIN `{$parent}` p ON ".implode(' AND ', $join).' WHERE '.implode(' AND ', $notNull)." AND p.`{$parentColumns[0]}` IS NULL LIMIT 1");
            if ($invalid !== null) {
                throw new RuntimeException("Relación inconsistente {$name}. Corrige los registros antes de migrar.");
            }
        }
        if (DB::table('business_hours')->select('day_of_week')->groupBy('day_of_week')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen horarios duplicados para un mismo día.');
        }
        if (DB::table('customer_addresses')->where('is_default', 1)->where('status', 'active')->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen clientes con varias direcciones predeterminadas activas.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_payments', function (Blueprint $table): void {
            $table->dropForeign('reservation_payments_reservation_id_foreign');
            $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
        });
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->dropUnique('uq_customer_default_address');
            $table->dropColumn('default_customer_id');
        });
        foreach (array_reverse(self::CHECKS, true) as $name => [$tableName]) {
            DB::statement("ALTER TABLE `{$tableName}` DROP CONSTRAINT `{$name}`");
        }
        foreach (array_reverse(self::RELATIONS, true) as $name => [$tableName]) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($name));
            if (Schema::hasIndex($tableName, $name)) {
                foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
                    $covered = false;
                    foreach (Schema::getIndexes($tableName) as $index) {
                        if ($index['name'] !== $name && array_slice($index['columns'], 0, count($foreignKey['columns'])) === $foreignKey['columns']) {
                            $covered = true;
                        }
                    }
                    if (! $covered) {
                        Schema::table($tableName, fn (Blueprint $table) => $table->index($foreignKey['columns'], $foreignKey['name']));
                    }
                }
                Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($name));
            }
        }
        foreach (array_reverse(self::UNIQUE_KEYS, true) as $name => [$tableName]) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($name));
        }
    }
};
