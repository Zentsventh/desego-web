-- DESEGO CEVICHERÍA - PASO 3
-- Pedidos reales, carrito, cobros y caja.
-- Importar DESPUÉS de database/phase2_catalog_inventory.sql.
-- No elimina ni recrea datos de los pasos anteriores.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS cash_register_mutex (
    id TINYINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO cash_register_mutex (id) VALUES (1);

CREATE TABLE IF NOT EXISTS cash_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opened_by BIGINT UNSIGNED NOT NULL,
    closed_by BIGINT UNSIGNED NULL,
    opening_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expected_closing DECIMAL(12,2) NULL,
    counted_closing DECIMAL(12,2) NULL,
    difference_amount DECIMAL(12,2) NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    opening_notes VARCHAR(500) NULL,
    closing_notes VARCHAR(500) NULL,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cash_sessions_status (status, opened_at),
    KEY idx_cash_sessions_date (opened_at),
    CONSTRAINT fk_cash_sessions_opened_by FOREIGN KEY (opened_by) REFERENCES users(id),
    CONSTRAINT fk_cash_sessions_closed_by FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(24) NULL,
    customer_name VARCHAR(100) NOT NULL DEFAULT 'Consumidor final',
    order_type ENUM('Mesa','Delivery','Para llevar') NOT NULL DEFAULT 'Mesa',
    table_label VARCHAR(40) NULL,
    notes VARCHAR(500) NULL,
    status ENUM('Pendiente','Preparando','Listo','En reparto','Entregado','Cancelado') NOT NULL DEFAULT 'Pendiente',
    payment_status ENUM('Pendiente','Pagado','Reembolsado') NOT NULL DEFAULT 'Pendiente',
    payment_method VARCHAR(30) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NOT NULL,
    cash_session_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    paid_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_number (order_number),
    KEY idx_orders_status_date (status, created_at),
    KEY idx_orders_payment_date (payment_status, created_at),
    KEY idx_orders_customer (customer_name),
    CONSTRAINT fk_orders_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_orders_cash_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_ingredients (
    order_id BIGINT UNSIGNED NOT NULL,
    ingredient_id INT UNSIGNED NOT NULL,
    consumed_quantity DECIMAL(14,3) NOT NULL,
    unit VARCHAR(12) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    restored_at DATETIME NULL,
    PRIMARY KEY (order_id, ingredient_id),
    CONSTRAINT fk_order_ingredients_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_ingredients_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    cash_session_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    payment_type ENUM('payment','refund') NOT NULL DEFAULT 'payment',
    method ENUM('Efectivo','Yape/Plin','Tarjeta','Transferencia') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_payments_session_date (cash_session_id, created_at),
    KEY idx_payments_order (order_id, payment_type),
    KEY idx_payments_method_date (method, created_at),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_payments_cash_session FOREIGN KEY (cash_session_id) REFERENCES cash_sessions(id),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cash_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('income','expense') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference VARCHAR(120) NULL,
    notes VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cash_movements_session_date (session_id, created_at),
    CONSTRAINT fk_cash_movements_session FOREIGN KEY (session_id) REFERENCES cash_sessions(id),
    CONSTRAINT fk_cash_movements_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('orders.pay', 'Cobrar pedidos', 'orders', 'Registrar pagos y reembolsos de pedidos.'),
('orders.cancel', 'Cancelar pedidos', 'orders', 'Cancelar pedidos y restaurar stock cuando no se haya iniciado la preparación.');

-- El administrador obtiene los permisos nuevos y los roles autorizados para caja/pedidos también.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('manager','cashier') AND p.code IN ('orders.pay','orders.cancel','cash.manage','orders.view','orders.create','orders.update');

