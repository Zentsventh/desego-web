-- DESEGO CEVICHERÍA - PASO 13: proveedores, compras, recepción y costo de inventario.
-- Importar en la misma base de datos del Paso 12. No elimina datos existentes.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    tax_id VARCHAR(20) NULL,
    contact_name VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    notes VARCHAR(1000) NULL,
    rating DECIMAL(2,1) NULL DEFAULT NULL COMMENT 'Valor manual opcional de 1 a 5 estrellas',
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_suppliers_tax_id (tax_id),
    KEY idx_suppliers_status_name (status, name),
    KEY idx_suppliers_email (email),
    CONSTRAINT fk_step13_supplier_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step13_supplier_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(32) NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    status ENUM('draft','sent','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    supplier_reference VARCHAR(120) NULL,
    notes VARCHAR(1000) NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    sent_by BIGINT UNSIGNED NULL,
    sent_at DATETIME NULL,
    received_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step13_purchase_order_number (order_number),
    KEY idx_step13_po_supplier_date (supplier_id, order_date),
    KEY idx_step13_po_status_date (status, order_date),
    CONSTRAINT fk_step13_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_step13_po_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step13_po_sent_by FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_step13_po_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    ingredient_id INT UNSIGNED NOT NULL,
    ingredient_name_snapshot VARCHAR(100) NOT NULL,
    unit VARCHAR(12) NOT NULL,
    ordered_quantity DECIMAL(14,3) NOT NULL,
    received_quantity DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    unit_cost DECIMAL(14,4) NOT NULL,
    line_total DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_step13_poi_order (purchase_order_id),
    KEY idx_step13_poi_ingredient (ingredient_id),
    CONSTRAINT fk_step13_poi_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_step13_poi_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_number VARCHAR(32) NOT NULL,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    supplier_invoice VARCHAR(120) NULL,
    notes VARCHAR(1000) NULL,
    received_by BIGINT UNSIGNED NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step13_receipt_number (receipt_number),
    KEY idx_step13_receipt_order (purchase_order_id, received_at),
    CONSTRAINT fk_step13_receipt_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_step13_receipt_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_step13_receipt_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_receipt_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    receipt_id BIGINT UNSIGNED NOT NULL,
    purchase_order_item_id BIGINT UNSIGNED NOT NULL,
    ingredient_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL,
    line_total DECIMAL(14,2) NOT NULL,
    stock_before DECIMAL(14,3) NOT NULL,
    stock_after DECIMAL(14,3) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_step13_receipt_item_receipt (receipt_id),
    KEY idx_step13_receipt_item_ingredient (ingredient_id, created_at),
    CONSTRAINT fk_step13_receipt_item_receipt FOREIGN KEY (receipt_id) REFERENCES purchase_receipts(id) ON DELETE CASCADE,
    CONSTRAINT fk_step13_receipt_item_order_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id),
    CONSTRAINT fk_step13_receipt_item_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_cost_layers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ingredient_id INT UNSIGNED NOT NULL,
    layer_type ENUM('opening','purchase','adjustment') NOT NULL DEFAULT 'purchase',
    receipt_item_id BIGINT UNSIGNED NULL,
    quantity_received DECIMAL(14,3) NOT NULL,
    quantity_remaining DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL,
    notes VARCHAR(255) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step13_cost_layer_receipt_item (receipt_item_id),
    KEY idx_step13_cost_layer_fifo (ingredient_id, received_at, id),
    KEY idx_step13_cost_layer_remaining (ingredient_id, quantity_remaining),
    CONSTRAINT fk_step13_cost_layer_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    CONSTRAINT fk_step13_cost_layer_receipt_item FOREIGN KEY (receipt_item_id) REFERENCES purchase_receipt_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_cost_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ingredient_id INT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    purchase_order_id BIGINT UNSIGNED NULL,
    receipt_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    previous_unit_cost DECIMAL(14,4) NOT NULL,
    new_unit_cost DECIMAL(14,4) NOT NULL,
    quantity_received DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    costing_method ENUM('FIFO','LIFO','AVG') NOT NULL DEFAULT 'FIFO',
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_step13_cost_history_ingredient_date (ingredient_id, created_at),
    KEY idx_step13_cost_history_supplier_date (supplier_id, created_at),
    CONSTRAINT fk_step13_cost_hist_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    CONSTRAINT fk_step13_cost_hist_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_step13_cost_hist_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_step13_cost_hist_receipt FOREIGN KEY (receipt_id) REFERENCES purchase_receipts(id) ON DELETE SET NULL,
    CONSTRAINT fk_step13_cost_hist_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('inventory_costing_method', 'FIFO');

-- Semilla una capa de apertura por ingrediente para permitir FIFO/LIFO sobre el stock que ya existía.
INSERT INTO inventory_cost_layers (ingredient_id, layer_type, receipt_item_id, quantity_received, quantity_remaining, unit_cost, notes, received_at)
SELECT i.id, 'opening', NULL, i.stock_quantity, i.stock_quantity, i.cost_per_unit, 'Capa inicial al instalar Paso 13', NOW()
FROM ingredients i
WHERE i.stock_quantity > 0
  AND NOT EXISTS (SELECT 1 FROM inventory_cost_layers l WHERE l.ingredient_id = i.id AND l.layer_type = 'opening');

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('suppliers.view', 'Consultar proveedores', 'purchasing', 'Consultar proveedores y sus datos de contacto.'),
('suppliers.manage', 'Gestionar proveedores', 'purchasing', 'Crear, editar, activar y evaluar proveedores.'),
('purchases.view', 'Consultar compras', 'purchasing', 'Consultar órdenes de compra, recepción e historial.'),
('purchases.manage', 'Gestionar órdenes de compra', 'purchasing', 'Crear, enviar, cancelar y administrar órdenes.'),
('purchases.receive', 'Recepcionar compras', 'purchasing', 'Registrar recepción parcial o completa de mercadería.'),
('inventory.costs.view', 'Consultar costos de inventario', 'inventory', 'Ver valoración, costo por ingrediente e historial.'),
('inventory.costs.manage', 'Configurar costeo de inventario', 'inventory', 'Cambiar el método de valoración FIFO, LIFO o promedio.');

-- Administradores obtienen los permisos nuevos; gerentes pueden gestionar compras, proveedores y costos.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('suppliers.view','suppliers.manage','purchases.view','purchases.manage','purchases.receive','inventory.costs.view','inventory.costs.manage')
WHERE r.code = 'manager';
