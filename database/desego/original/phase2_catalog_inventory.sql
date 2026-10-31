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
