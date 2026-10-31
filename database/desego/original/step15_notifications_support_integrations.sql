-- DESEGO CEVICHERÍA — Paso 15
-- Migración incremental: notificaciones, soporte, integraciones y API tokens.
-- Ejecutar en la misma base de datos de DESEGO. No importar schema.sql otra vez.

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  recipient_user_id BIGINT UNSIGNED NULL COMMENT 'NULL significa aviso para todos los usuarios activos',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  category ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
  target_view VARCHAR(40) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_recipient_created (recipient_user_id, created_at),
  KEY idx_notifications_created (created_at),
  CONSTRAINT fk_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id, user_id),
  KEY idx_notification_reads_user (user_id, read_at),
  CONSTRAINT fk_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_number VARCHAR(24) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NULL,
  subject VARCHAR(180) NOT NULL,
  category ENUM('technical','orders','inventory','billing','access','other') NOT NULL DEFAULT 'other',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','in_progress','waiting_user','resolved','closed') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_support_ticket_number (ticket_number),
  KEY idx_support_ticket_status_priority (status, priority, updated_at),
  KEY idx_support_ticket_owner (created_by, updated_at),
  CONSTRAINT fk_support_ticket_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_support_ticket_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  author_id BIGINT UNSIGNED NULL,
  author_name VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_support_messages_ticket (ticket_id, created_at),
  CONSTRAINT fk_support_message_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_message_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_registry (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_code VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  category ENUM('email','messaging','maps','payments','webhook','api','other') NOT NULL DEFAULT 'other',
  description VARCHAR(500) NULL,
  endpoint_url VARCHAR(500) NULL,
  environment_key VARCHAR(100) NULL COMMENT 'Nombre de variable de entorno. Nunca guardar secretos aquí.',
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  last_check_status ENUM('not_checked','configured','needs_configuration','invalid','error') NOT NULL DEFAULT 'not_checked',
  last_check_message VARCHAR(500) NULL,
  last_checked_at DATETIME NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integration_registry_code (integration_code),
  CONSTRAINT fk_integration_registry_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_code VARCHAR(60) NOT NULL,
  event_code VARCHAR(80) NOT NULL,
  status ENUM('success','warning','error') NOT NULL,
  message VARCHAR(500) NOT NULL,
  details_json JSON NULL,
  actor_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_integration_events_code_date (integration_code, created_at),
  CONSTRAINT fk_integration_events_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_access_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  token_prefix VARCHAR(20) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  scopes_json JSON NOT NULL,
  status ENUM('active','revoked') NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_access_token_hash (token_hash),
  KEY idx_api_tokens_status_expiry (status, expires_at),
  CONSTRAINT fk_api_tokens_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('notifications.view','Consultar notificaciones','notifications','Consultar los avisos dirigidos al usuario y avisos globales.'),
('notifications.manage','Publicar notificaciones','notifications','Publicar avisos para todos los usuarios activos.'),
('support.view','Consultar tickets de soporte','support','Consultar tickets de soporte de la organización.'),
('support.create','Crear tickets de soporte','support','Crear tickets y consultar los propios.'),
('support.reply','Responder tickets de soporte','support','Responder tickets y añadir mensajes públicos.'),
('support.manage','Administrar soporte','support','Asignar, priorizar, cambiar estados y consultar notas internas de tickets.'),
('integrations.view','Consultar integraciones','integrations','Consultar el registro de integraciones y sus estados de configuración.'),
('integrations.manage','Administrar integraciones','integrations','Editar la configuración no secreta y verificar variables de entorno.'),
('api_tokens.manage','Administrar tokens API','integrations','Crear y revocar tokens de acceso API.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'notifications.view','notifications.manage','support.view','support.create','support.reply','support.manage',
  'integrations.view','integrations.manage','api_tokens.manage'
) WHERE r.code = 'manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('notifications.view','support.create','support.reply')
WHERE r.code IN ('cashier','kitchen','waiter','delivery','collaborator');

INSERT IGNORE INTO integration_registry (integration_code,name,category,description,endpoint_url,environment_key,is_enabled)
VALUES
('smtp_email','Correo SMTP','email','Envío de recuperación de contraseña, avisos por correo y mensajes operativos.','', 'DESEGO_SMTP_HOST', 0),
('whatsapp_business','WhatsApp Business','messaging','Notificaciones por WhatsApp mediante un proveedor compatible.','', 'DESEGO_WHATSAPP_TOKEN', 0),
('maps_provider','Mapas y geolocalización','maps','Búsqueda de direcciones y geolocalización si se configura un proveedor.','', 'DESEGO_MAPS_API_KEY', 0),
('payment_gateway','Pasarela de pagos','payments','Proveedor externo de pagos. No procesa cobros por sí misma.','', 'DESEGO_PAYMENT_SECRET', 0),
('outgoing_webhook','Webhook saliente','webhook','Endpoint de terceros para recibir eventos cuando se implemente el envío.','', 'DESEGO_WEBHOOK_SECRET', 0);
