-- DESEGO CEVICHERÍA — PASO 6: Clientes, direcciones y vínculos con pedidos/reservas.
-- Importar UNA VEZ en la misma base de datos del Paso 5. No reemplaza datos existentes.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_code VARCHAR(24) NULL,
    full_name VARCHAR(120) NOT NULL,
    document_type ENUM('DNI','RUC','CE','PASAPORTE','OTRO','SIN_DOCUMENTO') NOT NULL DEFAULT 'SIN_DOCUMENTO',
    document_number VARCHAR(30) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(190) NULL,
    birth_date DATE NULL,
    notes VARCHAR(500) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_code (customer_code),
    UNIQUE KEY uq_customers_document (document_type, document_number),
    KEY idx_customers_name (full_name),
    KEY idx_customers_phone (phone),
    KEY idx_customers_email (email),
    KEY idx_customers_status (status),
    CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(60) NOT NULL DEFAULT 'Casa',
    recipient_name VARCHAR(120) NULL,
    phone VARCHAR(30) NULL,
    address_line VARCHAR(255) NOT NULL,
    reference VARCHAR(255) NULL,
    district VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    department VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_addresses_customer (customer_id, status, is_default),
    KEY idx_customer_addresses_district (district),
    CONSTRAINT fk_customer_addresses_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_addresses_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_customer_addresses_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add columns and constraints only when missing, so this migration can be safely re-run.
DROP PROCEDURE IF EXISTS desego_step6_customer_links;
DELIMITER $$
CREATE PROCEDURE desego_step6_customer_links()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'customer_id') THEN
        ALTER TABLE orders ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER customer_name;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_customer_id') THEN
        ALTER TABLE orders ADD INDEX idx_orders_customer_id (customer_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = 'orders' AND constraint_name = 'fk_orders_customer') THEN
        ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'reservations' AND column_name = 'customer_id') THEN
        ALTER TABLE reservations ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER customer_name;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'reservations' AND index_name = 'idx_reservations_customer_id') THEN
        ALTER TABLE reservations ADD INDEX idx_reservations_customer_id (customer_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = 'reservations' AND constraint_name = 'fk_reservations_customer') THEN
        ALTER TABLE reservations ADD CONSTRAINT fk_reservations_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'order_delivery_details') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order_delivery_details' AND column_name = 'customer_address_id') THEN
            ALTER TABLE order_delivery_details ADD COLUMN customer_address_id BIGINT UNSIGNED NULL AFTER order_id;
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'order_delivery_details' AND index_name = 'idx_order_delivery_customer_address') THEN
            ALTER TABLE order_delivery_details ADD INDEX idx_order_delivery_customer_address (customer_address_id);
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = 'order_delivery_details' AND constraint_name = 'fk_order_delivery_customer_address') THEN
            ALTER TABLE order_delivery_details ADD CONSTRAINT fk_order_delivery_customer_address FOREIGN KEY (customer_address_id) REFERENCES customer_addresses(id) ON DELETE SET NULL;
        END IF;
    END IF;
END$$
DELIMITER ;
CALL desego_step6_customer_links();
DROP PROCEDURE IF EXISTS desego_step6_customer_links;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('customers.view', 'Ver clientes', 'customers', 'Consultar clientes y sus datos de contacto.'),
('customers.manage', 'Gestionar clientes', 'customers', 'Crear, editar y activar/desactivar clientes.'),
('customer_addresses.manage', 'Gestionar direcciones de clientes', 'customers', 'Crear, actualizar y elegir direcciones guardadas de clientes.');

-- Administrador recibe todos los permisos actuales y nuevos.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

-- Gerencia y caja gestionan la cartera de clientes y direcciones.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code IN ('manager','cashier') AND p.code IN ('customers.view','customers.manage','customer_addresses.manage');

-- Mozos pueden consultar clientes y usar sus direcciones durante la atención.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'waiter' AND p.code IN ('customers.view','customer_addresses.manage');
