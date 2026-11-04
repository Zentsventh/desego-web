<?php

namespace App\Support\Desego;

class DatabaseIntegrityRules
{
    /** @var array<string, array{0: string, 1: string}> */
    public const RESTORED_CHECKS = [
        'chk_order_item_values' => ['order_items', 'quantity > 0 and unit_price >= 0 and line_total >= 0'],
        'chk_product_price' => ['products', 'price >= 0'],
        'chk_payment_amount' => ['payments', 'amount > 0'],
        'chk_cash_movement_amount' => ['cash_movements', 'amount > 0'],
        'chk_promotion_values' => ['promotions', "discount_value > 0 and (discount_type <> 'percentage' or discount_value <= 100) and min_subtotal >= 0 and (max_discount is null or max_discount >= 0) and (ends_at is null or ends_at >= starts_at)"],
        'chk_reservation_values' => ['reservations', 'guest_count > 0 and duration_minutes > 0 and advance_amount >= 0 and applied_credit_amount >= 0'],
        'chk_reservation_payment_values' => ['reservation_payments', 'amount > 0 and refund_amount >= 0 and refund_amount <= amount'],
        'chk_reservation_hold_values' => ['reservation_holds', 'guest_count > 0 and duration_minutes > 0 and advance_amount >= 0'],
        'chk_table_capacity' => ['restaurant_tables', 'capacity > 0'],
        'chk_ingredient_costs' => ['ingredients', 'min_stock >= 0 and cost_per_unit >= 0'],
        'chk_cost_layer_values' => ['inventory_cost_layers', 'quantity_received >= 0 and quantity_remaining >= 0 and quantity_remaining <= quantity_received and unit_cost >= 0'],
        'chk_purchase_item_values' => ['purchase_order_items', 'ordered_quantity > 0 and received_quantity >= 0 and unit_cost >= 0 and line_total >= 0'],
        'chk_receipt_item_values' => ['purchase_receipt_items', 'quantity > 0 and unit_cost >= 0 and line_total >= 0'],
        'chk_review_rating' => ['product_reviews', 'rating between 1 and 5'],
        'chk_supplier_rating' => ['suppliers', 'rating is null or rating between 1 and 5'],
        'chk_address_coordinates' => ['customer_addresses', '(latitude is null or latitude between -90 and 90) and (longitude is null or longitude between -180 and 180)'],
        'chk_delivery_zone_values' => ['delivery_zones', 'center_latitude between -90 and 90 and center_longitude between -180 and 180 and radius_km > 0 and delivery_fee >= 0 and min_order_amount >= 0 and estimated_minutes_max >= estimated_minutes_min'],
        'chk_business_day' => ['business_hours', 'day_of_week between 1 and 7'],
        'chk_order_member_session' => ['orders', 'dining_member_id is null or dining_session_id is not null'],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    public const PAYMENT_CHECKS = [
        'chk_order_amounts' => ['orders', 'subtotal >= 0 and discount >= 0 and total >= 0'],
        'chk_order_attempt_amount' => ['order_payment_attempts', 'amount_cents > 0'],
        'chk_dining_attempt_amount' => ['dining_payment_attempts', 'amount_cents > 0'],
        'chk_reservation_attempt_amount' => ['reservation_payment_attempts', 'amount_cents > 0'],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    public const JSON_CHECKS = [
        'scopes_json' => ['api_access_tokens', 'json_valid(scopes_json)'],
        'polygon_coordinates' => ['delivery_zones', 'json_valid(polygon_coordinates)'],
        'details_json' => ['integration_events', 'json_valid(details_json)'],
        'details' => ['reservation_audit_logs', 'json_valid(details)'],
        'duration_rules' => ['reservation_configurations', 'json_valid(duration_rules)'],
        'raw_response' => ['reservation_payments', 'json_valid(raw_response)'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    public const PAYMENT_RELATIONS = [
        'fk_order_attempt_cash' => ['order_payment_attempts', 'cash_session_id', 'cash_sessions'],
        'fk_order_attempt_actor' => ['order_payment_attempts', 'actor_id', 'users'],
    ];

    /** @return array<string, array{0: string, 1: string}> */
    public static function checks(): array
    {
        return self::RESTORED_CHECKS + self::PAYMENT_CHECKS + self::JSON_CHECKS;
    }

    public static function normalizedExpression(string $expression): string
    {
        return strtolower(preg_replace('/\s+|`/', '', $expression));
    }
}
