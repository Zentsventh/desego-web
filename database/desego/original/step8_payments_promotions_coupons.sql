-- DESEGO CEVICHERÍA - PASO 8: seguimiento de pagos, promociones y cupones.
-- Importar en la misma base de datos del Paso 7. No elimina datos existentes.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS promotions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    promotion_type ENUM('coupon','automatic') NOT NULL DEFAULT 'coupon',
    code VARCHAR(40) NULL,
    discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10,2) NOT NULL,
    max_discount DECIMAL(10,2) NULL,
    min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NULL,
    usage_limit INT UNSIGNED NULL,
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    per_customer_limit INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promotions_code (code),
    KEY idx_promotions_status_dates (status, starts_at, ends_at),
    KEY idx_promotions_type_status (promotion_type, status),
    CONSTRAINT fk_promotions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_promotions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promotion_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    promotion_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    customer_key CHAR(64) NULL,
    code_snapshot VARCHAR(40) NULL,
    discount_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promotion_redemptions_order (order_id),
    KEY idx_promotion_redemptions_promo_customer (promotion_id, customer_key),
    KEY idx_promotion_redemptions_customer (customer_id, created_at),
    CONSTRAINT fk_promotion_redemptions_promotion FOREIGN KEY (promotion_id) REFERENCES promotions(id),
    CONSTRAINT fk_promotion_redemptions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_promotion_redemptions_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS desego_step8_order_columns;
DELIMITER $$
CREATE PROCEDURE desego_step8_order_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'payment_preference') THEN
        ALTER TABLE orders ADD COLUMN payment_preference VARCHAR(30) NULL AFTER payment_method;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'promotion_id') THEN
        ALTER TABLE orders ADD COLUMN promotion_id BIGINT UNSIGNED NULL AFTER discount;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'promotion_code') THEN
        ALTER TABLE orders ADD COLUMN promotion_code VARCHAR(40) NULL AFTER promotion_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_promotion') THEN
        ALTER TABLE orders ADD KEY idx_orders_promotion (promotion_id, created_at);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'orders' AND constraint_name = 'fk_orders_promotion') THEN
        ALTER TABLE orders ADD CONSTRAINT fk_orders_promotion FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;
CALL desego_step8_order_columns();
DROP PROCEDURE IF EXISTS desego_step8_order_columns;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('promotions.view', 'Consultar promociones y cupones', 'promotions', 'Ver campañas, cupones, condiciones y usos.'),
('promotions.manage', 'Administrar promociones y cupones', 'promotions', 'Crear, editar, activar y desactivar campañas y cupones.'),
('payments.view', 'Consultar historial de pagos', 'payments', 'Consultar cobros y reembolsos registrados en caja.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'admin' AND p.code IN ('promotions.view','promotions.manage','payments.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN ('promotions.view','promotions.manage','payments.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'cashier' AND p.code IN ('promotions.view','payments.view');
