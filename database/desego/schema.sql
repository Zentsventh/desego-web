CREATE TABLE `allergens` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `analytics_preferences` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `metric_code` varchar(50) NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `token_prefix` varchar(20) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `scopes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`scopes_json`)),
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_rate_limit_windows` (
  `token_id` bigint(20) UNSIGNED NOT NULL,
  `window_start` datetime NOT NULL,
  `request_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shift_id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `event_type` enum('clock_in','clock_out','manual_adjustment') NOT NULL,
  `recorded_at` datetime NOT NULL,
  `recorded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `source` enum('web','admin') NOT NULL DEFAULT 'web',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_code` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `backup_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `file_name` varchar(180) DEFAULT NULL,
  `secondary_file_name` varchar(180) DEFAULT NULL,
  `backup_kind` enum('manual','automatic','pre_restore') NOT NULL DEFAULT 'manual',
  `status` enum('completed','failed','deleted') NOT NULL DEFAULT 'completed',
  `size_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `sha256_hash` char(64) DEFAULT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 1,
  `verification_status` enum('pending','valid','invalid','deleted','not_available') NOT NULL DEFAULT 'pending',
  `verified_at` datetime DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `branches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `movement_type` enum('income','expense') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(120) DEFAULT NULL,
  `notes` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_register_mutex` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cash_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `opened_by` bigint(20) UNSIGNED NOT NULL,
  `closed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `opening_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expected_closing` decimal(12,2) DEFAULT NULL,
  `counted_closing` decimal(12,2) DEFAULT NULL,
  `difference_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `opening_notes` varchar(500) DEFAULT NULL,
  `closing_notes` varchar(500) DEFAULT NULL,
  `opened_at` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_code` varchar(24) DEFAULT NULL,
  `full_name` varchar(120) NOT NULL,
  `document_type` enum('DNI','RUC','CE','PASAPORTE','OTRO','SIN_DOCUMENTO') NOT NULL DEFAULT 'SIN_DOCUMENTO',
  `document_number` varchar(30) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `label` varchar(60) NOT NULL DEFAULT 'Casa',
  `recipient_name` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address_line` varchar(255) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_code` varchar(30) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `branch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `job_title` varchar(100) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `employment_type` enum('full_time','part_time','temporary','contractor') NOT NULL DEFAULT 'full_time',
  `hire_date` date DEFAULT NULL,
  `hourly_rate` decimal(12,2) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employee_shifts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `employee_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `actual_start_at` datetime DEFAULT NULL,
  `actual_end_at` datetime DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingredients` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `unit` varchar(12) NOT NULL DEFAULT 'g',
  `stock_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `min_stock` decimal(14,3) NOT NULL DEFAULT 0.000,
  `cost_per_unit` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingredient_allergens` (
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `allergen_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingredient_cost_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `receipt_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `previous_unit_cost` decimal(14,4) NOT NULL,
  `new_unit_cost` decimal(14,4) NOT NULL,
  `quantity_received` decimal(14,3) NOT NULL DEFAULT 0.000,
  `costing_method` enum('FIFO','LIFO','AVG') NOT NULL DEFAULT 'FIFO',
  `reason` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `integration_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `integration_code` varchar(60) NOT NULL,
  `event_code` varchar(80) NOT NULL,
  `status` enum('success','warning','error') NOT NULL,
  `message` varchar(500) NOT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `integration_registry` (
  `id` int(10) UNSIGNED NOT NULL,
  `integration_code` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `category` enum('email','messaging','maps','payments','webhook','api','other') NOT NULL DEFAULT 'other',
  `description` varchar(500) DEFAULT NULL,
  `endpoint_url` varchar(500) DEFAULT NULL,
  `environment_key` varchar(100) DEFAULT NULL COMMENT 'Nombre de variable de entorno. Nunca guardar secretos aquí.',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_check_status` enum('not_checked','configured','needs_configuration','invalid','error') NOT NULL DEFAULT 'not_checked',
  `last_check_message` varchar(500) DEFAULT NULL,
  `last_checked_at` datetime DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_cost_layers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `layer_type` enum('opening','purchase','adjustment') NOT NULL DEFAULT 'purchase',
  `receipt_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity_received` decimal(14,3) NOT NULL,
  `quantity_remaining` decimal(14,3) NOT NULL,
  `unit_cost` decimal(14,4) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `movement_type` enum('initial','entry','exit','adjustment','waste','sale') NOT NULL,
  `quantity_delta` decimal(14,3) NOT NULL,
  `previous_quantity` decimal(14,3) NOT NULL,
  `new_quantity` decimal(14,3) NOT NULL,
  `unit_cost` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `reference` varchar(120) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` enum('boleta','factura') NOT NULL,
  `series` varchar(4) NOT NULL,
  `sequential_number` bigint(20) UNSIGNED NOT NULL,
  `document_number` varchar(24) NOT NULL,
  `buyer_name` varchar(160) NOT NULL,
  `buyer_document_type` enum('DNI','RUC','CE','Pasaporte','Sin documento') NOT NULL DEFAULT 'Sin documento',
  `buyer_document` varchar(20) DEFAULT NULL,
  `buyer_address` varchar(255) DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'PEN',
  `order_subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `order_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `igv_rate` decimal(5,2) NOT NULL DEFAULT 18.00,
  `igv_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('issued','voided') NOT NULL DEFAULT 'issued',
  `notes` varchar(500) DEFAULT NULL,
  `issued_by` bigint(20) UNSIGNED NOT NULL,
  `issued_at` datetime NOT NULL,
  `voided_by` bigint(20) UNSIGNED DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(120) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invoice_sequences` (
  `document_type` enum('boleta','factura') NOT NULL,
  `series` varchar(4) NOT NULL,
  `current_number` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_accounts` (
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `points_balance` int(11) NOT NULL DEFAULT 0,
  `lifetime_earned` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `lifetime_redeemed` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `redemption_code` char(12) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `reward_id` bigint(20) UNSIGNED NOT NULL,
  `points_spent` int(10) UNSIGNED NOT NULL,
  `status` enum('issued','used','cancelled') NOT NULL DEFAULT 'issued',
  `issued_by` bigint(20) UNSIGNED DEFAULT NULL,
  `used_by` bigint(20) UNSIGNED DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_rewards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reward_code` varchar(32) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reward_type` enum('discount','free_product') NOT NULL DEFAULT 'discount',
  `points_cost` int(10) UNSIGNED NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `stock_limit` int(10) UNSIGNED DEFAULT NULL,
  `redeemed_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `soles_per_point` decimal(10,2) NOT NULL DEFAULT 10.00,
  `minimum_eligible_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `points_expiry_days` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = los puntos no vencen automáticamente en esta versión',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reward_id` bigint(20) UNSIGNED DEFAULT NULL,
  `redemption_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `transaction_type` enum('earn','redeem','adjustment','order_refund','reward_cancel') NOT NULL,
  `points_delta` int(11) NOT NULL,
  `balance_after` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL significa aviso para todos los usuarios activos',
  `title` varchar(150) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `category` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `target_view` varchar(40) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_reads` (
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(24) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL DEFAULT 'Consumidor final',
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_type` enum('Mesa','Delivery','Para llevar') NOT NULL DEFAULT 'Mesa',
  `order_channel` enum('admin','web') NOT NULL DEFAULT 'admin',
  `web_checkout_key` varchar(64) DEFAULT NULL,
  `table_label` varchar(40) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` enum('Pendiente','Preparando','Listo','En reparto','Entregado','Cancelado') NOT NULL DEFAULT 'Pendiente',
  `payment_status` enum('Pendiente','Pagado','Reembolsado') NOT NULL DEFAULT 'Pendiente',
  `payment_method` varchar(30) DEFAULT NULL,
  `payment_preference` varchar(30) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `promotion_id` bigint(20) UNSIGNED DEFAULT NULL,
  `promotion_code` varchar(40) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `cash_session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_delivery_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `customer_address_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recipient_phone` varchar(30) DEFAULT NULL,
  `address_line` varchar(255) DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `courier_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pendiente','Asignado','En reparto','Entregado','Incidencia') NOT NULL DEFAULT 'Pendiente',
  `estimated_delivery_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `incident_notes` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_ingredients` (
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `consumed_quantity` decimal(14,3) NOT NULL,
  `unit` varchar(12) NOT NULL,
  `unit_cost` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `restored_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email_hash` char(64) NOT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `request_ip_hash` char(64) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `cash_session_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `payment_type` enum('payment','refund') NOT NULL DEFAULT 'payment',
  `method` enum('Efectivo','Yape/Plin','Tarjeta','Transferencia') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(120) NOT NULL,
  `module` varchar(60) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(120) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `emoji` varchar(16) NOT NULL DEFAULT '?️',
  `status` enum('available','unavailable') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_ingredients` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(14,3) NOT NULL DEFAULT 0.000 COMMENT 'Cantidad de ingrediente utilizada por una porción; usa la unidad base del ingrediente.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `reviewer_name` varchar(120) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `comment` varchar(1000) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `moderated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `moderator_note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);

CREATE TABLE `promotions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `promotion_type` enum('coupon','automatic') NOT NULL DEFAULT 'coupon',
  `code` varchar(40) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `min_subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime DEFAULT NULL,
  `usage_limit` int(10) UNSIGNED DEFAULT NULL,
  `usage_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `per_customer_limit` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `promotion_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `promotion_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_key` char(64) DEFAULT NULL,
  `code_snapshot` varchar(40) DEFAULT NULL,
  `discount_amount` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(32) NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('draft','sent','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `supplier_reference` varchar(120) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `sent_by` bigint(20) UNSIGNED DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `cancelled_by` bigint(20) UNSIGNED DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `ingredient_name_snapshot` varchar(100) NOT NULL,
  `unit` varchar(12) NOT NULL,
  `ordered_quantity` decimal(14,3) NOT NULL,
  `received_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(14,4) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_number` varchar(32) NOT NULL,
  `purchase_order_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_invoice` varchar(120) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_receipt_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `receipt_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_order_item_id` bigint(20) UNSIGNED NOT NULL,
  `ingredient_id` int(10) UNSIGNED NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit_cost` decimal(14,4) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  `stock_before` decimal(14,3) NOT NULL,
  `stock_after` decimal(14,3) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remember_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `selector` char(24) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reservations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reservation_code` varchar(32) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `customer_email` varchar(190) DEFAULT NULL,
  `table_id` int(10) UNSIGNED NOT NULL,
  `guest_count` tinyint(3) UNSIGNED NOT NULL,
  `reservation_at` datetime NOT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 120,
  `status` enum('Pendiente','Confirmada','Sentada','Completada','Cancelada','No asistió') NOT NULL DEFAULT 'Confirmada',
  `notes` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_tables` (
  `id` int(10) UNSIGNED NOT NULL,
  `table_number` smallint(5) UNSIGNED NOT NULL,
  `label` varchar(40) NOT NULL,
  `zone` varchar(80) NOT NULL DEFAULT 'Salón principal',
  `capacity` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `operational_status` enum('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `permission_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `storefront_favorites` (
  `visitor_key` char(64) NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `tax_id` varchar(20) DEFAULT NULL,
  `contact_name` varchar(120) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `notes` varchar(1000) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL COMMENT 'Valor manual opcional de 1 a 5 estrellas',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_number` varchar(24) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `subject` varchar(180) NOT NULL,
  `category` enum('technical','orders','inventory','billing','access','other') NOT NULL DEFAULT 'other',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('open','in_progress','waiting_user','resolved','closed') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_ticket_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `author_id` bigint(20) UNSIGNED DEFAULT NULL,
  `author_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` varchar(500) NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `failed_login_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_preferences` (
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `theme_tone` tinyint(3) UNSIGNED NOT NULL DEFAULT 72,
  `show_notification_badge` tinyint(1) NOT NULL DEFAULT 1,
  `desktop_notifications` tinyint(1) NOT NULL DEFAULT 0,
  `sound_notifications` tinyint(1) NOT NULL DEFAULT 0,
  `default_view` varchar(20) NOT NULL DEFAULT 'home',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
);

ALTER TABLE `allergens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_allergens_name` (`name`),
  ADD KEY `idx_allergens_status` (`status`);

ALTER TABLE `analytics_preferences`
  ADD PRIMARY KEY (`user_id`,`metric_code`);

ALTER TABLE `api_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_api_access_token_hash` (`token_hash`),
  ADD KEY `idx_api_tokens_status_expiry` (`status`,`expires_at`),
  ADD KEY `fk_api_tokens_creator` (`created_by`);

ALTER TABLE `api_rate_limit_windows`
  ADD PRIMARY KEY (`token_id`,`window_start`),
  ADD KEY `idx_api_rate_window_start` (`window_start`);

ALTER TABLE `attendance_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step14_attendance_employee_date` (`employee_id`,`recorded_at`),
  ADD KEY `idx_step14_attendance_shift` (`shift_id`,`recorded_at`),
  ADD KEY `fk_step14_attendance_branch` (`branch_id`),
  ADD KEY `fk_step14_attendance_user` (`recorded_by`);

ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_event_date` (`event_code`,`created_at`),
  ADD KEY `idx_audit_user_date` (`user_id`,`created_at`);

ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step12_backup_date` (`created_at`,`status`),
  ADD KEY `idx_step12_backup_status` (`status`,`backup_kind`),
  ADD KEY `fk_step12_backup_user` (`created_by`);

ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step14_branch_code` (`code`),
  ADD KEY `idx_step14_branch_status` (`status`,`name`),
  ADD KEY `fk_step14_branch_created_by` (`created_by`),
  ADD KEY `fk_step14_branch_updated_by` (`updated_by`);

ALTER TABLE `cash_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_movements_session_date` (`session_id`,`created_at`),
  ADD KEY `fk_cash_movements_user` (`user_id`);

ALTER TABLE `cash_register_mutex`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cash_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cash_sessions_status` (`status`,`opened_at`),
  ADD KEY `idx_cash_sessions_date` (`opened_at`),
  ADD KEY `fk_cash_sessions_opened_by` (`opened_by`),
  ADD KEY `fk_cash_sessions_closed_by` (`closed_by`);

ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`),
  ADD UNIQUE KEY `uq_categories_name` (`name`),
  ADD KEY `idx_categories_status` (`status`);

ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_customers_code` (`customer_code`),
  ADD UNIQUE KEY `uq_customers_document` (`document_type`,`document_number`),
  ADD KEY `idx_customers_name` (`full_name`),
  ADD KEY `idx_customers_phone` (`phone`),
  ADD KEY `idx_customers_email` (`email`),
  ADD KEY `idx_customers_status` (`status`),
  ADD KEY `fk_customers_created_by` (`created_by`),
  ADD KEY `fk_customers_updated_by` (`updated_by`);

ALTER TABLE `customer_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_addresses_customer` (`customer_id`,`status`,`is_default`),
  ADD KEY `idx_customer_addresses_district` (`district`),
  ADD KEY `fk_customer_addresses_created_by` (`created_by`),
  ADD KEY `fk_customer_addresses_updated_by` (`updated_by`);

ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step14_employee_code` (`employee_code`),
  ADD UNIQUE KEY `uq_step14_employee_user` (`user_id`),
  ADD KEY `idx_step14_employee_status_branch` (`status`,`branch_id`),
  ADD KEY `idx_step14_employee_name` (`full_name`),
  ADD KEY `fk_step14_employee_branch` (`branch_id`),
  ADD KEY `fk_step14_employee_created_by` (`created_by`),
  ADD KEY `fk_step14_employee_updated_by` (`updated_by`);

ALTER TABLE `employee_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step14_shift_date_status` (`shift_date`,`status`),
  ADD KEY `idx_step14_shift_employee_date` (`employee_id`,`shift_date`,`status`),
  ADD KEY `idx_step14_shift_branch_date` (`branch_id`,`shift_date`),
  ADD KEY `fk_step14_shift_created_by` (`created_by`),
  ADD KEY `fk_step14_shift_updated_by` (`updated_by`);

ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ingredients_name` (`name`),
  ADD KEY `idx_ingredients_status_stock` (`status`,`stock_quantity`,`min_stock`);

ALTER TABLE `ingredient_allergens`
  ADD PRIMARY KEY (`ingredient_id`,`allergen_id`),
  ADD KEY `fk_ingredient_allergens_allergen` (`allergen_id`);

ALTER TABLE `ingredient_cost_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step13_cost_history_ingredient_date` (`ingredient_id`,`created_at`),
  ADD KEY `idx_step13_cost_history_supplier_date` (`supplier_id`,`created_at`),
  ADD KEY `fk_step13_cost_hist_order` (`purchase_order_id`),
  ADD KEY `fk_step13_cost_hist_receipt` (`receipt_id`),
  ADD KEY `fk_step13_cost_hist_user` (`user_id`);

ALTER TABLE `integration_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_integration_events_code_date` (`integration_code`,`created_at`),
  ADD KEY `fk_integration_events_actor` (`actor_id`);

ALTER TABLE `integration_registry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_integration_registry_code` (`integration_code`),
  ADD KEY `fk_integration_registry_updated_by` (`updated_by`);

ALTER TABLE `inventory_cost_layers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step13_cost_layer_receipt_item` (`receipt_item_id`),
  ADD KEY `idx_step13_cost_layer_fifo` (`ingredient_id`,`received_at`,`id`),
  ADD KEY `idx_step13_cost_layer_remaining` (`ingredient_id`,`quantity_remaining`);

ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_ingredient_date` (`ingredient_id`,`created_at`),
  ADD KEY `idx_inventory_type_date` (`movement_type`,`created_at`),
  ADD KEY `fk_inventory_user` (`user_id`);

ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step11_invoice_order` (`order_id`),
  ADD UNIQUE KEY `uq_step11_invoice_number` (`document_type`,`series`,`sequential_number`),
  ADD UNIQUE KEY `uq_step11_invoice_document` (`document_number`),
  ADD KEY `idx_step11_invoice_date` (`issued_at`,`status`),
  ADD KEY `idx_step11_invoice_buyer` (`buyer_document`,`buyer_name`),
  ADD KEY `fk_step11_invoice_issuer` (`issued_by`),
  ADD KEY `fk_step11_invoice_voider` (`voided_by`);

ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step11_invoice_items_invoice` (`invoice_id`);

ALTER TABLE `invoice_sequences`
  ADD PRIMARY KEY (`document_type`),
  ADD UNIQUE KEY `uq_step11_invoice_series` (`series`);

ALTER TABLE `loyalty_accounts`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `idx_step9_loyalty_balance` (`points_balance`);

ALTER TABLE `loyalty_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step9_loyalty_redemption_code` (`redemption_code`),
  ADD KEY `idx_step9_redemption_customer` (`customer_id`,`issued_at`),
  ADD KEY `idx_step9_redemption_status` (`status`,`issued_at`),
  ADD KEY `fk_step9_redemption_reward` (`reward_id`),
  ADD KEY `fk_step9_redemption_issued_by` (`issued_by`),
  ADD KEY `fk_step9_redemption_used_by` (`used_by`);

ALTER TABLE `loyalty_rewards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step9_loyalty_reward_code` (`reward_code`),
  ADD KEY `idx_step9_loyalty_rewards_state` (`status`,`starts_at`,`ends_at`),
  ADD KEY `fk_step9_loyalty_reward_product` (`product_id`),
  ADD KEY `fk_step9_loyalty_reward_created_by` (`created_by`),
  ADD KEY `fk_step9_loyalty_reward_updated_by` (`updated_by`);

ALTER TABLE `loyalty_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_step9_loyalty_settings_user` (`updated_by`);

ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step9_loyalty_order_type` (`order_id`,`transaction_type`),
  ADD KEY `idx_step9_loyalty_customer_date` (`customer_id`,`created_at`),
  ADD KEY `idx_step9_loyalty_type_date` (`transaction_type`,`created_at`),
  ADD KEY `fk_step9_loyalty_tx_reward` (`reward_id`),
  ADD KEY `fk_step9_loyalty_tx_redemption` (`redemption_id`),
  ADD KEY `fk_step9_loyalty_tx_user` (`user_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_recipient_created` (`recipient_user_id`,`created_at`),
  ADD KEY `idx_notifications_created` (`created_at`),
  ADD KEY `fk_notifications_creator` (`created_by`);

ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`notification_id`,`user_id`),
  ADD KEY `idx_notification_reads_user` (`user_id`,`read_at`);

ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_number` (`order_number`),
  ADD UNIQUE KEY `uq_orders_web_checkout_key` (`web_checkout_key`),
  ADD KEY `idx_orders_status_date` (`status`,`created_at`),
  ADD KEY `idx_orders_payment_date` (`payment_status`,`created_at`),
  ADD KEY `idx_orders_customer` (`customer_name`),
  ADD KEY `fk_orders_created_by` (`created_by`),
  ADD KEY `fk_orders_cash_session` (`cash_session_id`),
  ADD KEY `idx_orders_customer_id` (`customer_id`),
  ADD KEY `idx_orders_channel_date` (`order_channel`,`created_at`),
  ADD KEY `idx_orders_promotion` (`promotion_id`,`created_at`);

ALTER TABLE `order_delivery_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_delivery_order` (`order_id`),
  ADD KEY `idx_delivery_status_updated` (`status`,`updated_at`),
  ADD KEY `idx_delivery_courier_status` (`courier_user_id`,`status`),
  ADD KEY `idx_order_delivery_customer_address` (`customer_address_id`);

ALTER TABLE `order_ingredients`
  ADD PRIMARY KEY (`order_id`,`ingredient_id`),
  ADD KEY `fk_order_ingredients_ingredient` (`ingredient_id`);

ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_product` (`product_id`);

ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  ADD KEY `idx_password_reset_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_password_reset_ip_created` (`request_ip_hash`,`created_at`),
  ADD KEY `idx_password_reset_email_created` (`email_hash`,`created_at`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`,`used_at`);

ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payments_session_date` (`cash_session_id`,`created_at`),
  ADD KEY `idx_payments_order` (`order_id`,`payment_type`),
  ADD KEY `idx_payments_method_date` (`method`,`created_at`),
  ADD KEY `fk_payments_user` (`user_id`);

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_permissions_code` (`code`),
  ADD KEY `idx_permissions_module` (`module`);

ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_slug` (`slug`),
  ADD KEY `idx_products_category_status` (`category_id`,`status`);

ALTER TABLE `product_ingredients`
  ADD PRIMARY KEY (`product_id`,`ingredient_id`),
  ADD KEY `idx_product_ingredients_ingredient` (`ingredient_id`);

ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step10_review_order_product` (`order_id`,`product_id`),
  ADD KEY `idx_step10_reviews_product_status` (`product_id`,`status`,`created_at`),
  ADD KEY `idx_step10_reviews_customer` (`customer_id`,`created_at`),
  ADD KEY `idx_step10_reviews_status_date` (`status`,`created_at`),
  ADD KEY `fk_step10_review_moderator` (`moderated_by`);

ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promotions_code` (`code`),
  ADD KEY `idx_promotions_status_dates` (`status`,`starts_at`,`ends_at`),
  ADD KEY `idx_promotions_type_status` (`promotion_type`,`status`),
  ADD KEY `fk_promotions_created_by` (`created_by`),
  ADD KEY `fk_promotions_updated_by` (`updated_by`);

ALTER TABLE `promotion_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_promotion_redemptions_order` (`order_id`),
  ADD KEY `idx_promotion_redemptions_promo_customer` (`promotion_id`,`customer_key`),
  ADD KEY `idx_promotion_redemptions_customer` (`customer_id`,`created_at`);

ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step13_purchase_order_number` (`order_number`),
  ADD KEY `idx_step13_po_supplier_date` (`supplier_id`,`order_date`),
  ADD KEY `idx_step13_po_status_date` (`status`,`order_date`),
  ADD KEY `fk_step13_po_created_by` (`created_by`),
  ADD KEY `fk_step13_po_sent_by` (`sent_by`),
  ADD KEY `fk_step13_po_cancelled_by` (`cancelled_by`);

ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step13_poi_order` (`purchase_order_id`),
  ADD KEY `idx_step13_poi_ingredient` (`ingredient_id`);

ALTER TABLE `purchase_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_step13_receipt_number` (`receipt_number`),
  ADD KEY `idx_step13_receipt_order` (`purchase_order_id`,`received_at`),
  ADD KEY `fk_step13_receipt_supplier` (`supplier_id`),
  ADD KEY `fk_step13_receipt_user` (`received_by`);

ALTER TABLE `purchase_receipt_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_step13_receipt_item_receipt` (`receipt_id`),
  ADD KEY `idx_step13_receipt_item_ingredient` (`ingredient_id`,`created_at`),
  ADD KEY `fk_step13_receipt_item_order_item` (`purchase_order_item_id`);

ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remember_selector` (`selector`),
  ADD KEY `idx_remember_user_expiry` (`user_id`,`expires_at`);

ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reservations_code` (`reservation_code`),
  ADD KEY `idx_reservations_table_time` (`table_id`,`reservation_at`,`status`),
  ADD KEY `idx_reservations_time_status` (`reservation_at`,`status`),
  ADD KEY `idx_reservations_customer_phone` (`customer_phone`),
  ADD KEY `fk_reservations_created_by` (`created_by`),
  ADD KEY `fk_reservations_updated_by` (`updated_by`),
  ADD KEY `idx_reservations_customer_id` (`customer_id`);

ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_restaurant_tables_number` (`table_number`),
  ADD UNIQUE KEY `uq_restaurant_tables_label` (`label`),
  ADD KEY `idx_restaurant_tables_status` (`is_active`,`operational_status`,`zone`),
  ADD KEY `fk_restaurant_tables_created_by` (`created_by`),
  ADD KEY `fk_restaurant_tables_updated_by` (`updated_by`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_code` (`code`);

ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `fk_role_permissions_permission` (`permission_id`);

ALTER TABLE `storefront_favorites`
  ADD PRIMARY KEY (`visitor_key`,`product_id`),
  ADD KEY `idx_step10_favorites_product` (`product_id`);

ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_suppliers_tax_id` (`tax_id`),
  ADD KEY `idx_suppliers_status_name` (`status`,`name`),
  ADD KEY `idx_suppliers_email` (`email`),
  ADD KEY `fk_step13_supplier_created_by` (`created_by`),
  ADD KEY `fk_step13_supplier_updated_by` (`updated_by`);

ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_support_ticket_number` (`ticket_number`),
  ADD KEY `idx_support_ticket_status_priority` (`status`,`priority`,`updated_at`),
  ADD KEY `idx_support_ticket_owner` (`created_by`,`updated_at`),
  ADD KEY `fk_support_ticket_assignee` (`assigned_to`);

ALTER TABLE `support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_messages_ticket` (`ticket_id`,`created_at`),
  ADD KEY `fk_support_message_author` (`author_id`);

ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`),
  ADD KEY `fk_step12_setting_user` (`updated_by`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role_status` (`role_id`,`status`);

ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `allergens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

ALTER TABLE `api_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `attendance_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `backup_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `branches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `cash_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `cash_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `customer_addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `employee_shifts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ingredients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

ALTER TABLE `ingredient_cost_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `integration_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `integration_registry`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `inventory_cost_layers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `inventory_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `loyalty_redemptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `loyalty_rewards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `loyalty_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `order_delivery_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `promotions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `promotion_redemptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchase_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchase_order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchase_receipts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `purchase_receipt_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `reservations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `restaurant_tables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `suppliers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `support_ticket_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `analytics_preferences`
  ADD CONSTRAINT `fk_step12_analytics_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `api_access_tokens`
  ADD CONSTRAINT `fk_api_tokens_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `api_rate_limit_windows`
  ADD CONSTRAINT `fk_api_rate_limit_token` FOREIGN KEY (`token_id`) REFERENCES `api_access_tokens` (`id`) ON DELETE CASCADE;

ALTER TABLE `attendance_events`
  ADD CONSTRAINT `fk_step14_attendance_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_step14_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_step14_attendance_shift` FOREIGN KEY (`shift_id`) REFERENCES `employee_shifts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_step14_attendance_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `backup_history`
  ADD CONSTRAINT `fk_step12_backup_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `branches`
  ADD CONSTRAINT `fk_step14_branch_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step14_branch_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `cash_movements`
  ADD CONSTRAINT `fk_cash_movements_session` FOREIGN KEY (`session_id`) REFERENCES `cash_sessions` (`id`),
  ADD CONSTRAINT `fk_cash_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `cash_sessions`
  ADD CONSTRAINT `fk_cash_sessions_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cash_sessions_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`);

ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_customers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `fk_customer_addresses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_customer_addresses_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_customer_addresses_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `employees`
  ADD CONSTRAINT `fk_step14_employee_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step14_employee_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step14_employee_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step14_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `employee_shifts`
  ADD CONSTRAINT `fk_step14_shift_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `fk_step14_shift_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step14_shift_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `fk_step14_shift_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `ingredient_allergens`
  ADD CONSTRAINT `fk_ingredient_allergens_allergen` FOREIGN KEY (`allergen_id`) REFERENCES `allergens` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ingredient_allergens_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE;

ALTER TABLE `ingredient_cost_history`
  ADD CONSTRAINT `fk_step13_cost_hist_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_step13_cost_hist_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_cost_hist_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_cost_hist_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_cost_hist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `integration_events`
  ADD CONSTRAINT `fk_integration_events_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `integration_registry`
  ADD CONSTRAINT `fk_integration_registry_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_cost_layers`
  ADD CONSTRAINT `fk_step13_cost_layer_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_step13_cost_layer_receipt_item` FOREIGN KEY (`receipt_item_id`) REFERENCES `purchase_receipt_items` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_inventory_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_inventory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_step11_invoice_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_step11_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_step11_invoice_voider` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_step11_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

ALTER TABLE `loyalty_accounts`
  ADD CONSTRAINT `fk_step9_loyalty_account_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

ALTER TABLE `loyalty_redemptions`
  ADD CONSTRAINT `fk_step9_redemption_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_step9_redemption_issued_by` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step9_redemption_reward` FOREIGN KEY (`reward_id`) REFERENCES `loyalty_rewards` (`id`),
  ADD CONSTRAINT `fk_step9_redemption_used_by` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `loyalty_rewards`
  ADD CONSTRAINT `fk_step9_loyalty_reward_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step9_loyalty_reward_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step9_loyalty_reward_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `loyalty_settings`
  ADD CONSTRAINT `fk_step9_loyalty_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `loyalty_transactions`
  ADD CONSTRAINT `fk_step9_loyalty_tx_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_step9_loyalty_tx_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step9_loyalty_tx_redemption` FOREIGN KEY (`redemption_id`) REFERENCES `loyalty_redemptions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step9_loyalty_tx_reward` FOREIGN KEY (`reward_id`) REFERENCES `loyalty_rewards` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step9_loyalty_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `notification_reads`
  ADD CONSTRAINT `fk_notification_reads_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_cash_session` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_delivery_details`
  ADD CONSTRAINT `fk_delivery_courier` FOREIGN KEY (`courier_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_delivery_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_delivery_customer_address` FOREIGN KEY (`customer_address_id`) REFERENCES `customer_addresses` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_ingredients`
  ADD CONSTRAINT `fk_order_ingredients_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_order_ingredients_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_cash_session` FOREIGN KEY (`cash_session_id`) REFERENCES `cash_sessions` (`id`),
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

ALTER TABLE `product_ingredients`
  ADD CONSTRAINT `fk_product_ingredients_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_product_ingredients_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_step10_review_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_step10_review_moderator` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step10_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_step10_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `promotions`
  ADD CONSTRAINT `fk_promotions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_promotions_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `promotion_redemptions`
  ADD CONSTRAINT `fk_promotion_redemptions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_promotion_redemptions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_promotion_redemptions_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`);

ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `fk_step13_po_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_po_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_po_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `fk_step13_poi_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_step13_poi_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE;

ALTER TABLE `purchase_receipts`
  ADD CONSTRAINT `fk_step13_receipt_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `fk_step13_receipt_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_step13_receipt_user` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `purchase_receipt_items`
  ADD CONSTRAINT `fk_step13_receipt_item_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`),
  ADD CONSTRAINT `fk_step13_receipt_item_order_item` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`),
  ADD CONSTRAINT `fk_step13_receipt_item_receipt` FOREIGN KEY (`receipt_id`) REFERENCES `purchase_receipts` (`id`) ON DELETE CASCADE;

ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `reservations`
  ADD CONSTRAINT `fk_reservations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reservations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reservations_table` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`),
  ADD CONSTRAINT `fk_reservations_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `restaurant_tables`
  ADD CONSTRAINT `fk_restaurant_tables_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_restaurant_tables_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `storefront_favorites`
  ADD CONSTRAINT `fk_step10_favorite_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_step13_supplier_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_step13_supplier_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_support_ticket_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_ticket_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `support_ticket_messages`
  ADD CONSTRAINT `fk_support_message_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_message_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE;

ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_step12_setting_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
