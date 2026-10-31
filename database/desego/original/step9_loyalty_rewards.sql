-- DESEGO CEVICHERÍA - PASO 9: Fidelización, puntos y recompensas.
-- Importar en la misma base de datos después del Paso 8. No elimina datos.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS loyalty_settings (
    id TINYINT UNSIGNED NOT NULL,
    soles_per_point DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    minimum_eligible_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    points_expiry_days INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = los puntos no vencen automáticamente en esta versión',
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_step9_loyalty_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO loyalty_settings (id, soles_per_point, minimum_eligible_amount, points_expiry_days)
VALUES (1, 10.00, 0.00, 0);

CREATE TABLE IF NOT EXISTS loyalty_accounts (
    customer_id BIGINT UNSIGNED NOT NULL,
    points_balance INT NOT NULL DEFAULT 0,
    lifetime_earned BIGINT UNSIGNED NOT NULL DEFAULT 0,
    lifetime_redeemed BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    KEY idx_step9_loyalty_balance (points_balance),
    CONSTRAINT fk_step9_loyalty_account_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_rewards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reward_code VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    reward_type ENUM('discount','free_product') NOT NULL DEFAULT 'discount',
    points_cost INT UNSIGNED NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    product_id INT UNSIGNED NULL,
    stock_limit INT UNSIGNED NULL,
    redeemed_count INT UNSIGNED NOT NULL DEFAULT 0,
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step9_loyalty_reward_code (reward_code),
    KEY idx_step9_loyalty_rewards_state (status, starts_at, ends_at),
    CONSTRAINT fk_step9_loyalty_reward_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT fk_step9_loyalty_reward_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step9_loyalty_reward_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    redemption_code CHAR(12) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    reward_id BIGINT UNSIGNED NOT NULL,
    points_spent INT UNSIGNED NOT NULL,
    status ENUM('issued','used','cancelled') NOT NULL DEFAULT 'issued',
    issued_by BIGINT UNSIGNED NULL,
    used_by BIGINT UNSIGNED NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step9_loyalty_redemption_code (redemption_code),
    KEY idx_step9_redemption_customer (customer_id, issued_at),
    KEY idx_step9_redemption_status (status, issued_at),
    CONSTRAINT fk_step9_redemption_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_step9_redemption_reward FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id),
    CONSTRAINT fk_step9_redemption_issued_by FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step9_redemption_used_by FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    reward_id BIGINT UNSIGNED NULL,
    redemption_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    transaction_type ENUM('earn','redeem','adjustment','order_refund','reward_cancel') NOT NULL,
    points_delta INT NOT NULL,
    balance_after INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step9_loyalty_order_type (order_id, transaction_type),
    KEY idx_step9_loyalty_customer_date (customer_id, created_at),
    KEY idx_step9_loyalty_type_date (transaction_type, created_at),
    CONSTRAINT fk_step9_loyalty_tx_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_step9_loyalty_tx_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_step9_loyalty_tx_reward FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id) ON DELETE SET NULL,
    CONSTRAINT fk_step9_loyalty_tx_redemption FOREIGN KEY (redemption_id) REFERENCES loyalty_redemptions(id) ON DELETE SET NULL,
    CONSTRAINT fk_step9_loyalty_tx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recompensa inicial de ejemplo; se puede editar/desactivar desde el panel.
INSERT IGNORE INTO loyalty_rewards
    (reward_code, name, description, reward_type, points_cost, discount_amount, stock_limit, starts_at, status)
VALUES
    ('DESEGO-S5', 'Descuento de S/ 5', 'Vale de descuento para usar en una compra presencial. Verifica las condiciones con caja.', 'discount', 50, 5.00, NULL, UTC_TIMESTAMP(), 'active');

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('loyalty.view', 'Consultar fidelización', 'loyalty', 'Consultar saldos de puntos, recompensas y movimientos.'),
('loyalty.manage', 'Administrar fidelización', 'loyalty', 'Configurar puntos, recompensas, ajustes y canjes.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN ('loyalty.view','loyalty.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'cashier' AND p.code IN ('loyalty.view','loyalty.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'waiter' AND p.code = 'loyalty.view';
