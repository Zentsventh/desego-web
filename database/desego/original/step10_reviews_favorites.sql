-- DESEGO CEVICHERÍA - PASO 10: reseñas, calificaciones y favoritos.
-- Importar sobre la misma base de datos después del Paso 9. No elimina datos.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS product_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    reviewer_name VARCHAR(120) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(120) NULL,
    comment VARCHAR(1000) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    moderated_by BIGINT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    moderator_note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_step10_review_order_product (order_id, product_id),
    KEY idx_step10_reviews_product_status (product_id, status, created_at),
    KEY idx_step10_reviews_customer (customer_id, created_at),
    KEY idx_step10_reviews_status_date (status, created_at),
    CONSTRAINT chk_step10_review_rating CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_step10_review_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_step10_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_step10_review_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_step10_review_moderator FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storefront_favorites (
    visitor_key CHAR(64) NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (visitor_key, product_id),
    KEY idx_step10_favorites_product (product_id),
    CONSTRAINT fk_step10_favorite_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('reviews.view', 'Consultar reseñas', 'reviews', 'Consultar reseñas y calificaciones de clientes.'),
('reviews.manage', 'Moderar reseñas', 'reviews', 'Aprobar o rechazar reseñas de productos.');

-- El rol administrador obtiene los permisos nuevos sin cambiar usuarios existentes.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';
