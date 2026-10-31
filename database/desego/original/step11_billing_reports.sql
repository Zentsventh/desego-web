-- DESEGO CEVICHERÍA - PASO 11: comprobantes internos y reportes gerenciales.
-- Importar en la misma base de datos del Paso 10. No elimina tablas ni datos previos.
-- IMPORTANTE: estos comprobantes son representaciones internas; no son documentos electrónicos SUNAT.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS invoice_sequences (
    document_type ENUM('boleta','factura') NOT NULL,
    series VARCHAR(4) NOT NULL,
    current_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (document_type),
    UNIQUE KEY uq_step11_invoice_series (series)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO invoice_sequences (document_type, series, current_number) VALUES
('boleta', 'B001', 0),
('factura', 'F001', 0);

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    document_type ENUM('boleta','factura') NOT NULL,
    series VARCHAR(4) NOT NULL,
    sequential_number BIGINT UNSIGNED NOT NULL,
    document_number VARCHAR(24) NOT NULL,
    buyer_name VARCHAR(160) NOT NULL,
    buyer_document_type ENUM('DNI','RUC','CE','Pasaporte','Sin documento') NOT NULL DEFAULT 'Sin documento',
    buyer_document VARCHAR(20) NULL,
    buyer_address VARCHAR(255) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PEN',
    order_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    order_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    igv_rate DECIMAL(5,2) NOT NULL DEFAULT 18.00,
    igv_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('issued','voided') NOT NULL DEFAULT 'issued',
    notes VARCHAR(500) NULL,
    issued_by BIGINT UNSIGNED NOT NULL,
    issued_at DATETIME NOT NULL,
    voided_by BIGINT UNSIGNED NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step11_invoice_order (order_id),
    UNIQUE KEY uq_step11_invoice_number (document_type, series, sequential_number),
    UNIQUE KEY uq_step11_invoice_document (document_number),
    KEY idx_step11_invoice_date (issued_at, status),
    KEY idx_step11_invoice_buyer (buyer_document, buyer_name),
    CONSTRAINT fk_step11_invoice_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_step11_invoice_issuer FOREIGN KEY (issued_by) REFERENCES users(id),
    CONSTRAINT fk_step11_invoice_voider FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_step11_invoice_items_invoice (invoice_id),
    CONSTRAINT fk_step11_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('invoices.view', 'Consultar comprobantes', 'billing', 'Consultar comprobantes internos emitidos.'),
('invoices.manage', 'Emitir y anular comprobantes internos', 'billing', 'Emitir comprobantes internos para pedidos pagados y registrar su anulación.'),
('reports.view', 'Ver reportes', 'reports', 'Consultar reportes del restaurante.');

-- Administrador: todos los permisos actuales y los de este paso.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

-- Gerente: consulta y administración de comprobantes más reportes.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN ('invoices.view','invoices.manage','reports.view');

-- Cajero: consulta y emisión de comprobantes internos; no acceso a reportes gerenciales.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'cashier' AND p.code IN ('invoices.view','invoices.manage');
