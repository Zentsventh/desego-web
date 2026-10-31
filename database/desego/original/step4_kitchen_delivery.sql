-- DESEGO CEVICHERÍA — Paso 4: cocina (KDS) y delivery
-- Importar en la MISMA base de datos existente, después de las migraciones de los pasos 2, 3 y recuperación.
-- No elimina tablas ni registros existentes.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS order_delivery_details (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    recipient_phone VARCHAR(30) NULL,
    address_line VARCHAR(255) NULL,
    zone VARCHAR(100) NULL,
    courier_user_id BIGINT UNSIGNED NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pendiente','Asignado','En reparto','Entregado','Incidencia') NOT NULL DEFAULT 'Pendiente',
    estimated_delivery_at DATETIME NULL,
    picked_up_at DATETIME NULL,
    delivered_at DATETIME NULL,
    incident_notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_delivery_order (order_id),
    KEY idx_delivery_status_updated (status, updated_at),
    KEY idx_delivery_courier_status (courier_user_id, status),
    CONSTRAINT fk_delivery_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_courier FOREIGN KEY (courier_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añade un registro de seguimiento a los pedidos Delivery ya creados en versiones anteriores.
INSERT IGNORE INTO order_delivery_details (order_id, status, created_at, updated_at)
SELECT id,
       CASE
         WHEN status = 'En reparto' THEN 'En reparto'
         WHEN status = 'Entregado' THEN 'Entregado'
         ELSE 'Pendiente'
       END,
       COALESCE(created_at, NOW()), COALESCE(updated_at, NOW())
FROM orders
WHERE order_type = 'Delivery';

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('kitchen.view', 'Ver pantalla de cocina', 'kitchen', 'Consultar la cola KDS y los pedidos por preparar.'),
('kitchen.manage', 'Gestionar preparación', 'kitchen', 'Avanzar pedidos de Pendiente a Preparando y Listo.'),
('delivery.view', 'Ver delivery', 'delivery', 'Consultar pedidos y seguimiento de entregas.'),
('delivery.manage', 'Gestionar delivery', 'delivery', 'Asignar repartidores y administrar incidencias y entregas.'),
('delivery.update', 'Actualizar entregas asignadas', 'delivery', 'Actualizar el estado de entregas asignadas al repartidor.');

-- Administrador: todos los permisos actuales y nuevos.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

-- Gerencia supervisa cocina y delivery.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN ('kitchen.view','kitchen.manage','delivery.view','delivery.manage','delivery.update','orders.view','orders.update');

-- Cocina: ver cola y cambiar únicamente el flujo de preparación.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'kitchen' AND p.code IN ('kitchen.view','kitchen.manage','orders.view','orders.update');

-- Repartidor: solo ve entregas asignadas y puede actualizar sus propios recorridos.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'delivery' AND p.code IN ('delivery.view','delivery.update','orders.view');

-- El repartidor ya no cambia arbitrariamente el estado del pedido desde la lista general;
-- los cambios de reparto deben pasar por api/operations.php y validan su asignación.
DELETE rp FROM role_permissions rp
INNER JOIN roles r ON r.id = rp.role_id
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE r.code = 'delivery' AND p.code = 'orders.update';
