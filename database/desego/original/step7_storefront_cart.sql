-- DESEGO CEVICHERÍA - PASO 7: tienda web, carrito y pedidos de clientes.
-- Importar en la MISMA base de datos, después de los pasos 1 a 6.
-- Añade solamente columnas que falten; no elimina pedidos ni datos anteriores.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP PROCEDURE IF EXISTS desego_step7_storefront_columns;
DELIMITER $$
CREATE PROCEDURE desego_step7_storefront_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'order_channel'
    ) THEN
        ALTER TABLE orders ADD COLUMN order_channel ENUM('admin','web') NOT NULL DEFAULT 'admin' AFTER order_type;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'web_checkout_key'
    ) THEN
        ALTER TABLE orders ADD COLUMN web_checkout_key VARCHAR(64) NULL AFTER order_channel;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'uq_orders_web_checkout_key'
    ) THEN
        ALTER TABLE orders ADD UNIQUE KEY uq_orders_web_checkout_key (web_checkout_key);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'orders' AND index_name = 'idx_orders_channel_date'
    ) THEN
        ALTER TABLE orders ADD KEY idx_orders_channel_date (order_channel, created_at);
    END IF;
END$$
DELIMITER ;
CALL desego_step7_storefront_columns();
DROP PROCEDURE IF EXISTS desego_step7_storefront_columns;
