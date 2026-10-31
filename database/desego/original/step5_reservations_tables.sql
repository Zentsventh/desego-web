-- DESEGO CEVICHERÍA — PASO 5: Reservas y gestión de mesas
-- Importar en la MISMA base de datos del Paso 4.
-- No elimina ni reemplaza usuarios, pedidos, productos ni inventario.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS restaurant_tables (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_number SMALLINT UNSIGNED NOT NULL,
    label VARCHAR(40) NOT NULL,
    zone VARCHAR(80) NOT NULL DEFAULT 'Salón principal',
    capacity TINYINT UNSIGNED NOT NULL DEFAULT 4,
    operational_status ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_restaurant_tables_number (table_number),
    UNIQUE KEY uq_restaurant_tables_label (label),
    KEY idx_restaurant_tables_status (is_active, operational_status, zone),
    CONSTRAINT fk_restaurant_tables_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_restaurant_tables_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_code VARCHAR(32) NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(30) NULL,
    customer_email VARCHAR(190) NULL,
    table_id INT UNSIGNED NOT NULL,
    guest_count TINYINT UNSIGNED NOT NULL,
    reservation_at DATETIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    status ENUM('Pendiente','Confirmada','Sentada','Completada','Cancelada','No asistió') NOT NULL DEFAULT 'Confirmada',
    notes VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reservations_code (reservation_code),
    KEY idx_reservations_table_time (table_id, reservation_at, status),
    KEY idx_reservations_time_status (reservation_at, status),
    KEY idx_reservations_customer_phone (customer_phone),
    CONSTRAINT fk_reservations_table FOREIGN KEY (table_id) REFERENCES restaurant_tables(id),
    CONSTRAINT fk_reservations_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_reservations_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mesas iniciales. INSERT IGNORE permite volver a importar la migración sin duplicarlas.
INSERT IGNORE INTO restaurant_tables (table_number, label, zone, capacity, operational_status) VALUES
(1,'Mesa 01','Salón principal',2,'available'),
(2,'Mesa 02','Salón principal',2,'available'),
(3,'Mesa 03','Salón principal',4,'available'),
(4,'Mesa 04','Salón principal',4,'available'),
(5,'Mesa 05','Salón principal',4,'available'),
(6,'Mesa 06','Salón principal',4,'available'),
(7,'Mesa 07','Salón principal',4,'available'),
(8,'Mesa 08','Salón principal',6,'available'),
(9,'Mesa 09','Salón principal',6,'available'),
(10,'Mesa 10','Salón principal',4,'available'),
(11,'Mesa 11','Salón principal',4,'available'),
(12,'Mesa 12','Salón principal',6,'available'),
(13,'Mesa 13','Salón principal',4,'available'),
(14,'Mesa 14','Salón principal',4,'available'),
(15,'Mesa 15','Salón principal',6,'available'),
(16,'Mesa 16','Salón principal',4,'available'),
(17,'Mesa 17','Salón principal',4,'available'),
(18,'Mesa 18','Salón principal',6,'available'),
(19,'Mesa 19','Salón principal',6,'available'),
(20,'Mesa 20','Salón principal',8,'available'),
(21,'Mesa 21','Salón principal',4,'available'),
(22,'Mesa 22','Salón principal',4,'available'),
(23,'Mesa 23','Salón principal',8,'available'),
(24,'Mesa 24','Salón principal',8,'available');

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('tables.view', 'Ver mesas', 'tables', 'Consultar el plano, estados y capacidades de las mesas.'),
('tables.manage', 'Gestionar mesas', 'tables', 'Crear, editar, activar, desactivar y cambiar el estado operativo de mesas.');

-- Administrador obtiene todos los permisos.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

-- Gerencia puede administrar las mesas y reservas.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN ('tables.view','tables.manage','reservations.view','reservations.manage');

-- Mozos pueden consultar y cambiar la operativa de mesas y gestionar reservas.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'waiter' AND p.code IN ('tables.view','tables.manage','reservations.view','reservations.manage');

-- El administrador recibe también los permisos añadidos a mano a la tabla de permisos.
