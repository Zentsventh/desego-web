-- DESEGO CEVICHERÍA - primer bloque: autenticación, usuarios, roles y permisos.
-- Selecciona primero la base de datos en phpMyAdmin y luego importa este archivo.
-- Compatible con MySQL 5.7+ / MariaDB con InnoDB.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    module VARCHAR(60) NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role_id, status),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    event_code VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_event_date (event_code, created_at),
    KEY idx_audit_user_date (user_id, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (code, name, description, is_system) VALUES
('admin', 'Administrador General', 'Control total de la plataforma.', 1),
('manager', 'Gerente', 'Supervisión de la operación del restaurante.', 1),
('cashier', 'Cajero', 'Caja y registro de ventas.', 1),
('kitchen', 'Cocina', 'Consulta y actualización de pedidos de cocina.', 1),
('waiter', 'Mozo', 'Atención de mesas, pedidos y reservas.', 1),
('delivery', 'Repartidor', 'Seguimiento de pedidos de reparto.', 1),
('collaborator', 'Colaborador', 'Acceso básico a la aplicación.', 1);

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('dashboard.view', 'Ver panel general', 'dashboard', 'Acceso al panel principal.'),
('profile.view', 'Ver perfil', 'profile', 'Consultar información de la cuenta.'),
('profile.update', 'Editar perfil', 'profile', 'Modificar el nombre y correo propios.'),
('users.view', 'Ver usuarios', 'users', 'Consultar usuarios del sistema.'),
('users.create', 'Crear usuarios', 'users', 'Registrar nuevas cuentas.'),
('users.update', 'Editar usuarios', 'users', 'Modificar cuentas y asignar roles autorizados.'),
('users.deactivate', 'Activar o desactivar usuarios', 'users', 'Cambiar el estado de acceso de usuarios.'),
('roles.manage', 'Gestionar roles y permisos', 'roles', 'Crear roles y asignar permisos.'),
('menu.view', 'Ver menú', 'menu', 'Consultar productos del restaurante.'),
('products.manage', 'Gestionar productos', 'menu', 'Crear y mantener productos.'),
('categories.manage', 'Gestionar categorías', 'menu', 'Mantener categorías del menú.'),
('orders.view', 'Ver pedidos', 'orders', 'Consultar pedidos.'),
('orders.create', 'Crear pedidos', 'orders', 'Registrar pedidos y ventas.'),
('orders.update', 'Actualizar pedidos', 'orders', 'Actualizar estados de pedidos.'),
('reservations.view', 'Ver reservas', 'reservations', 'Consultar agenda de reservas.'),
('reservations.manage', 'Gestionar reservas', 'reservations', 'Crear y modificar reservas.'),
('cash.manage', 'Gestionar caja', 'cash', 'Operaciones de caja.'),
('inventory.view', 'Ver inventario', 'inventory', 'Consultar existencias.'),
('inventory.manage', 'Gestionar inventario', 'inventory', 'Modificar existencias e inventario.'),
('reports.view', 'Ver reportes', 'reports', 'Consultar reportes del restaurante.'),
('settings.manage', 'Gestionar configuración', 'settings', 'Modificar parámetros del sistema.');

-- El administrador inicial se crea desde setup.php, con una contraseña elegida por el propietario.
-- El rol administrador recibe todos los permisos actuales y los nuevos permisos que se agreguen.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

-- Permisos del gerente.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN (
'dashboard.view','profile.view','profile.update','users.view','menu.view','products.manage','categories.manage',
'orders.view','orders.create','orders.update','reservations.view','reservations.manage','cash.manage',
'inventory.view','inventory.manage','reports.view','settings.manage'
);

-- Permisos del cajero.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'cashier' AND p.code IN (
'dashboard.view','profile.view','profile.update','menu.view','orders.view','orders.create','orders.update',
'reservations.view','reservations.manage','cash.manage'
);

-- Permisos de cocina.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'kitchen' AND p.code IN (
'dashboard.view','profile.view','profile.update','orders.view','orders.update'
);

-- Permisos del mozo.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'waiter' AND p.code IN (
'dashboard.view','profile.view','profile.update','menu.view','orders.view','orders.create','orders.update',
'reservations.view','reservations.manage'
);

-- Permisos del repartidor.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'delivery' AND p.code IN (
'dashboard.view','profile.view','profile.update','orders.view','orders.update'
);

-- Registro público: cuenta con acceso básico, nunca recibe el rol administrador.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'collaborator' AND p.code IN (
'dashboard.view','profile.view','profile.update'
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    user_agent VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user_expiry (user_id, expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ======================================================
-- PASO 2: CATÁLOGO, RECETAS, ALÉRGENOS E INVENTARIO
-- ======================================================
-- DESEGO CEVICHERÍA - PASO 2
-- Catálogo real, categorías, ingredientes/alérgenos e inventario.
-- Importar DESPUÉS de database/schema.sql en una instalación del Paso 1.
-- Es idempotente: puede ejecutarse más de una vez sin duplicar los registros base.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS allergens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_allergens_name (name),
    KEY idx_allergens_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    unit VARCHAR(12) NOT NULL DEFAULT 'g',
    stock_quantity DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    min_stock DECIMAL(14,3) NOT NULL DEFAULT 0.000,
    cost_per_unit DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ingredients_name (name),
    KEY idx_ingredients_status_stock (status, stock_quantity, min_stock)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ingredient_allergens (
    ingredient_id INT UNSIGNED NOT NULL,
    allergen_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ingredient_id, allergen_id),
    CONSTRAINT fk_ingredient_allergens_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
    CONSTRAINT fk_ingredient_allergens_allergen FOREIGN KEY (allergen_id) REFERENCES allergens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    emoji VARCHAR(16) NOT NULL DEFAULT '🍽️',
    status ENUM('available','unavailable') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category_status (category_id, status),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_ingredients (
    product_id INT UNSIGNED NOT NULL,
    ingredient_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 0.000 COMMENT 'Cantidad de ingrediente utilizada por una porción; usa la unidad base del ingrediente.',
    PRIMARY KEY (product_id, ingredient_id),
    KEY idx_product_ingredients_ingredient (ingredient_id),
    CONSTRAINT fk_product_ingredients_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_product_ingredients_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ingredient_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    movement_type ENUM('initial','entry','exit','adjustment','waste','sale') NOT NULL,
    quantity_delta DECIMAL(14,3) NOT NULL,
    previous_quantity DECIMAL(14,3) NOT NULL,
    new_quantity DECIMAL(14,3) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    reference VARCHAR(120) NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_inventory_ingredient_date (ingredient_id, created_at),
    KEY idx_inventory_type_date (movement_type, created_at),
    CONSTRAINT fk_inventory_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos nuevos para el módulo de catálogo/inventario.
INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('ingredients.manage', 'Gestionar ingredientes', 'ingredients', 'Crear ingredientes y relacionarlos con alérgenos.'),
('allergens.manage', 'Gestionar alérgenos', 'ingredients', 'Crear y mantener el catálogo de alérgenos.');

-- Asignaciones base; el rol administrador obtiene todos los permisos actuales.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'manager' AND p.code IN ('ingredients.manage','allergens.manage','inventory.view','inventory.manage','products.manage','categories.manage','menu.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.code = 'cashier' AND p.code IN ('menu.view');

-- Categorías iniciales.
INSERT IGNORE INTO categories (slug, name, description, status) VALUES
('entradas', 'Entradas', 'Entradas y porciones para compartir.', 'active'),
('platos', 'Platos', 'Platos principales de la cevichería.', 'active'),
('bebidas', 'Bebidas', 'Bebidas frías y tradicionales.', 'active'),
('postres', 'Postres', 'Postres y opciones dulces.', 'active');

-- Catálogo inicial de alérgenos.
INSERT IGNORE INTO allergens (name, description, status) VALUES
('Pescado', 'Pescados y productos derivados.', 'active'),
('Crustáceos', 'Camarón, langostino y otros crustáceos.', 'active'),
('Moluscos', 'Conchas, calamar, pulpo y otros moluscos.', 'active'),
('Lácteos', 'Leche y productos derivados.', 'active'),
('Gluten', 'Trigo y cereales que contienen gluten.', 'active'),
('Huevo', 'Huevos y productos derivados.', 'active'),
('Soya', 'Soya y productos derivados.', 'active'),
('Maní', 'Maní y productos derivados.', 'active'),
('Frutos secos', 'Almendras, nueces y otros frutos secos.', 'active');

-- Ingredientes demo. El inventario puede editarse desde la interfaz.
INSERT IGNORE INTO ingredients (name, description, unit, stock_quantity, min_stock, cost_per_unit, status) VALUES
('Pescado fresco', 'Pescado para ceviches y platos marinos.', 'g', 8000.000, 1500.000, 0.0350, 'active'),
('Limón', 'Limón fresco para preparación.', 'g', 5000.000, 1000.000, 0.0080, 'active'),
('Cebolla roja', 'Cebolla roja en juliana.', 'g', 4000.000, 800.000, 0.0060, 'active'),
('Ají limo', 'Ají limo fresco.', 'g', 1000.000, 200.000, 0.0200, 'active'),
('Camote', 'Camote cocido para guarnición.', 'g', 3000.000, 500.000, 0.0050, 'active'),
('Choclo', 'Maíz cocido para guarnición.', 'g', 2500.000, 500.000, 0.0060, 'active'),
('Aceite vegetal', 'Aceite para frituras.', 'ml', 5000.000, 1000.000, 0.0120, 'active'),
('Arroz', 'Arroz para preparación de platos.', 'g', 7000.000, 1200.000, 0.0040, 'active'),
('Mix de mariscos', 'Mezcla de mariscos para arroz.', 'g', 3000.000, 700.000, 0.0450, 'active'),
('Maíz morado', 'Maíz morado para chicha.', 'g', 2500.000, 400.000, 0.0120, 'active'),
('Azúcar', 'Azúcar para bebidas y postres.', 'g', 4000.000, 500.000, 0.0045, 'active'),
('Canela', 'Canela para bebidas y postres.', 'g', 300.000, 50.000, 0.0300, 'active'),
('Harina de trigo', 'Harina para repostería.', 'g', 3000.000, 500.000, 0.0050, 'active'),
('Chocolate', 'Chocolate para repostería.', 'g', 1500.000, 300.000, 0.0280, 'active'),
('Leche', 'Leche para repostería.', 'ml', 2500.000, 500.000, 0.0060, 'active'),
('Huevo', 'Huevo para repostería.', 'unidad', 60.000, 12.000, 0.5500, 'active');

-- Relación de alérgenos con ingredientes.
INSERT IGNORE INTO ingredient_allergens (ingredient_id, allergen_id)
SELECT i.id, a.id FROM ingredients i CROSS JOIN allergens a WHERE i.name = 'Pescado fresco' AND a.name = 'Pescado';
INSERT IGNORE INTO ingredient_allergens (ingredient_id, allergen_id)
SELECT i.id, a.id FROM ingredients i CROSS JOIN allergens a WHERE i.name = 'Mix de mariscos' AND a.name IN ('Crustáceos','Moluscos');
INSERT IGNORE INTO ingredient_allergens (ingredient_id, allergen_id)
SELECT i.id, a.id FROM ingredients i CROSS JOIN allergens a WHERE i.name = 'Harina de trigo' AND a.name = 'Gluten';
INSERT IGNORE INTO ingredient_allergens (ingredient_id, allergen_id)
SELECT i.id, a.id FROM ingredients i CROSS JOIN allergens a WHERE i.name = 'Leche' AND a.name = 'Lácteos';
INSERT IGNORE INTO ingredient_allergens (ingredient_id, allergen_id)
SELECT i.id, a.id FROM ingredients i CROSS JOIN allergens a WHERE i.name = 'Huevo' AND a.name = 'Huevo';
INSERT IGNORE INTO ingredient_allergens (ingredient_id, allergen_id)
SELECT i.id, a.id FROM ingredients i CROSS JOIN allergens a WHERE i.name = 'Chocolate' AND a.name = 'Soya';

-- Productos demo iniciales.
INSERT IGNORE INTO products (category_id, slug, name, description, price, emoji, status)
SELECT c.id, 'ceviche-desego', 'Ceviche Desego', 'Ceviche de la casa con pescado fresco.', 24.90, '🐟', 'available' FROM categories c WHERE c.slug = 'platos';
INSERT IGNORE INTO products (category_id, slug, name, description, price, emoji, status)
SELECT c.id, 'ceviche-clasico', 'Ceviche Clásico', 'El clásico sabor marino peruano.', 26.00, '🍋', 'available' FROM categories c WHERE c.slug = 'platos';
INSERT IGNORE INTO products (category_id, slug, name, description, price, emoji, status)
SELECT c.id, 'pescado-frito', 'Pescado Frito', 'Pescado dorado con su guarnición.', 29.00, '🐠', 'available' FROM categories c WHERE c.slug = 'platos';
INSERT IGNORE INTO products (category_id, slug, name, description, price, emoji, status)
SELECT c.id, 'arroz-con-mariscos', 'Arroz con Mariscos', 'Arroz sazonado con mariscos frescos.', 31.00, '🍤', 'available' FROM categories c WHERE c.slug = 'platos';
INSERT IGNORE INTO products (category_id, slug, name, description, price, emoji, status)
SELECT c.id, 'chicha-morada', 'Chicha Morada', 'Refrescante bebida tradicional peruana.', 8.00, '🥤', 'available' FROM categories c WHERE c.slug = 'bebidas';
INSERT IGNORE INTO products (category_id, slug, name, description, price, emoji, status)
SELECT c.id, 'torta-de-chocolate', 'Torta de Chocolate', 'Torta húmeda de intenso chocolate.', 12.50, '🍰', 'available' FROM categories c WHERE c.slug = 'postres';

-- Recetas base: cantidad utilizada para preparar una porción. Las cantidades son ejemplos ajustables.
INSERT IGNORE INTO product_ingredients (product_id, ingredient_id, quantity)
SELECT p.id, i.id, x.quantity FROM products p
JOIN (SELECT 'ceviche-desego' slug,'Pescado fresco' ingredient,180.000 quantity UNION ALL SELECT 'ceviche-desego','Limón',50 UNION ALL SELECT 'ceviche-desego','Cebolla roja',35 UNION ALL SELECT 'ceviche-desego','Ají limo',5 UNION ALL SELECT 'ceviche-desego','Camote',70 UNION ALL SELECT 'ceviche-desego','Choclo',50
UNION ALL SELECT 'ceviche-clasico','Pescado fresco',200 UNION ALL SELECT 'ceviche-clasico','Limón',55 UNION ALL SELECT 'ceviche-clasico','Cebolla roja',40 UNION ALL SELECT 'ceviche-clasico','Ají limo',5 UNION ALL SELECT 'ceviche-clasico','Camote',70 UNION ALL SELECT 'ceviche-clasico','Choclo',50
UNION ALL SELECT 'pescado-frito','Pescado fresco',220 UNION ALL SELECT 'pescado-frito','Aceite vegetal',35
UNION ALL SELECT 'arroz-con-mariscos','Arroz',180 UNION ALL SELECT 'arroz-con-mariscos','Mix de mariscos',160 UNION ALL SELECT 'arroz-con-mariscos','Cebolla roja',30 UNION ALL SELECT 'arroz-con-mariscos','Aceite vegetal',20
UNION ALL SELECT 'chicha-morada','Maíz morado',45 UNION ALL SELECT 'chicha-morada','Azúcar',20 UNION ALL SELECT 'chicha-morada','Canela',1
UNION ALL SELECT 'torta-de-chocolate','Harina de trigo',70 UNION ALL SELECT 'torta-de-chocolate','Chocolate',45 UNION ALL SELECT 'torta-de-chocolate','Leche',40 UNION ALL SELECT 'torta-de-chocolate','Huevo',1) x ON p.slug = x.slug
JOIN ingredients i ON i.name = x.ingredient;

-- Historial inicial de los stocks de ejemplo (no duplica movimientos al reimportar).
INSERT INTO inventory_movements (ingredient_id, user_id, movement_type, quantity_delta, previous_quantity, new_quantity, unit_cost, notes, created_at)
SELECT i.id, NULL, 'initial', i.stock_quantity, 0.000, i.stock_quantity, i.cost_per_unit, 'Carga inicial de inventario demo', NOW()
FROM ingredients i
WHERE i.stock_quantity > 0
AND NOT EXISTS (SELECT 1 FROM inventory_movements m WHERE m.ingredient_id = i.id AND m.movement_type = 'initial');


-- ======================================================
-- PASO 3: PEDIDOS, VENTAS Y CAJA
-- ======================================================
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

-- PASO 4: recuperación de contraseña por correo.
-- DESEGO CEVICHERÍA - RECUPERACIÓN SEGURA DE CONTRASEÑA
-- Importar UNA VEZ en la misma base de datos que usa DESEGO (Pasos 1-3).
-- No borra usuarios, roles ni datos existentes.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    email_hash CHAR(64) NOT NULL,
    token_hash CHAR(64) NULL,
    request_ip_hash CHAR(64) NOT NULL,
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user_created (user_id, created_at),
    KEY idx_password_reset_ip_created (request_ip_hash, created_at),
    KEY idx_password_reset_email_created (email_hash, created_at),
    KEY idx_password_reset_expiry (expires_at, used_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
