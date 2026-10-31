<?php

namespace App\Support\Desego;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyDesegoMigration
{
    public const NAME = '2026_10_25_230128_create_desego_tables';

    /** @var list<string> */
    public const NATIVE_MIGRATIONS = [
        '2026_10_25_220001_create_roles_table',
        '2026_10_25_220002_create_permissions_table',
        '2026_10_25_220003_create_users_table',
        '2026_10_25_220004_create_branches_table',
        '2026_10_25_220005_create_categories_table',
        '2026_10_25_220006_create_allergens_table',
        '2026_10_25_220007_create_ingredients_table',
        '2026_10_25_220008_create_products_table',
        '2026_10_25_220009_create_analytics_preferences_table',
        '2026_10_25_220010_create_api_access_tokens_table',
        '2026_10_25_220011_create_api_rate_limit_windows_table',
        '2026_10_25_220012_create_audit_logs_table',
        '2026_10_25_220013_create_backup_history_table',
        '2026_10_25_220014_create_cash_register_mutex_table',
        '2026_10_25_220015_create_cash_sessions_table',
        '2026_10_25_220016_create_cash_movements_table',
        '2026_10_25_220017_create_customers_table',
        '2026_10_25_220018_create_customer_addresses_table',
        '2026_10_25_220019_create_employees_table',
        '2026_10_25_220020_create_employee_shifts_table',
        '2026_10_25_220021_create_attendance_events_table',
        '2026_10_25_220022_create_ingredient_allergens_table',
        '2026_10_25_220023_create_integration_events_table',
        '2026_10_25_220024_create_integration_registry_table',
        '2026_10_25_220025_create_inventory_movements_table',
        '2026_10_25_220026_create_invoice_sequences_table',
        '2026_10_25_220027_create_loyalty_accounts_table',
        '2026_10_25_220028_create_loyalty_rewards_table',
        '2026_10_25_220029_create_loyalty_redemptions_table',
        '2026_10_25_220030_create_loyalty_settings_table',
        '2026_10_25_220031_create_notifications_table',
        '2026_10_25_220032_create_notification_reads_table',
        '2026_10_25_220033_create_password_reset_tokens_table',
        '2026_10_25_220034_create_product_ingredients_table',
        '2026_10_25_220035_create_promotions_table',
        '2026_10_25_220036_create_orders_table',
        '2026_10_25_220037_create_invoices_table',
        '2026_10_25_220038_create_invoice_items_table',
        '2026_10_25_220039_create_loyalty_transactions_table',
        '2026_10_25_220040_create_order_delivery_details_table',
        '2026_10_25_220041_create_order_ingredients_table',
        '2026_10_25_220042_create_order_items_table',
        '2026_10_25_220043_create_payments_table',
        '2026_10_25_220044_create_product_reviews_table',
        '2026_10_25_220045_create_promotion_redemptions_table',
        '2026_10_25_220046_create_remember_tokens_table',
        '2026_10_25_220047_create_restaurant_tables_table',
        '2026_10_25_220048_create_reservations_table',
        '2026_10_25_220049_create_role_permissions_table',
        '2026_10_25_220050_create_storefront_favorites_table',
        '2026_10_25_220051_create_suppliers_table',
        '2026_10_25_220052_create_purchase_orders_table',
        '2026_10_25_220053_create_purchase_order_items_table',
        '2026_10_25_220054_create_purchase_receipts_table',
        '2026_10_25_220055_create_ingredient_cost_history_table',
        '2026_10_25_220056_create_purchase_receipt_items_table',
        '2026_10_25_220057_create_inventory_cost_layers_table',
        '2026_10_25_220058_create_support_tickets_table',
        '2026_10_25_220059_create_support_ticket_messages_table',
        '2026_10_25_220100_create_system_settings_table',
        '2026_10_25_220101_create_user_preferences_table',
    ];

    /** Existing imports adopt the new history without recreating or dropping their tables. */
    public static function skipNativeMigration(string $migration): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('DESEGO requires MySQL or MariaDB.');
        }
        $table = config('database.migrations.table', 'migrations');
        $legacyId = DB::connection()->withoutPretending(fn () => DB::table($table)->where('migration', self::NAME)->value('id'));
        if ($legacyId === null) {
            return false;
        }
        $nativeId = DB::connection()->withoutPretending(fn () => DB::table($table)->where('migration', $migration)->value('id'));

        return $nativeId === null || (int) $legacyId < (int) $nativeId;
    }

    /** Fresh installations have already built the schema before the historical migration runs. */
    public static function nativeBaselinePrecedesLegacy(): bool
    {
        $table = config('database.migrations.table', 'migrations');
        $legacyId = DB::connection()->withoutPretending(fn () => DB::table($table)->where('migration', self::NAME)->value('id'));
        $query = DB::table($table)->whereIn('migration', self::NATIVE_MIGRATIONS);
        if ($legacyId !== null) {
            $query->where('id', '<', $legacyId);
        }

        return DB::connection()->withoutPretending(fn () => $query->count()) === count(self::NATIVE_MIGRATIONS);
    }
}
