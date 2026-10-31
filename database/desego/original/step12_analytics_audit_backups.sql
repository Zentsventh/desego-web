-- DESEGO CEVICHERÍA - PASO 12: analítica, auditoría y copias de seguridad.
-- Importar en la misma base de datos del Paso 11. No elimina información existente.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(80) NOT NULL,
    setting_value VARCHAR(500) NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_step12_setting_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('audit_retention_days', '365'),
('backup_retention_days', '90'),
('suspicious_login_threshold', '5');

CREATE TABLE IF NOT EXISTS analytics_preferences (
    user_id BIGINT UNSIGNED NOT NULL,
    metric_code VARCHAR(50) NOT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, metric_code),
    CONSTRAINT fk_step12_analytics_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_name VARCHAR(180) NULL,
    secondary_file_name VARCHAR(180) NULL,
    backup_kind ENUM('manual','automatic','pre_restore') NOT NULL DEFAULT 'manual',
    status ENUM('completed','failed','deleted') NOT NULL DEFAULT 'completed',
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    sha256_hash CHAR(64) NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 1,
    verification_status ENUM('pending','valid','invalid','deleted','not_available') NOT NULL DEFAULT 'pending',
    verified_at DATETIME NULL,
    error_message VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_step12_backup_date (created_at, status),
    KEY idx_step12_backup_status (status, backup_kind),
    CONSTRAINT fk_step12_backup_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module, description) VALUES
('analytics.view', 'Ver analítica avanzada', 'analytics', 'Consultar tendencias, rentabilidad estimada y comportamiento comercial.'),
('analytics.preferences', 'Personalizar indicadores de analítica', 'analytics', 'Guardar los indicadores visibles del usuario.'),
('audit.view', 'Consultar auditoría', 'audit', 'Consultar el registro de acciones y alertas de seguridad.'),
('audit.export', 'Exportar auditoría', 'audit', 'Descargar el registro de auditoría filtrado.'),
('audit.retention.manage', 'Configurar retención de auditoría', 'audit', 'Cambiar la retención y purgar registros vencidos.'),
('backups.view', 'Consultar copias de seguridad', 'backups', 'Consultar historial, verificar integridad y descargar backups.'),
('backups.create', 'Crear copias de seguridad', 'backups', 'Crear copias cifradas bajo demanda.'),
('backups.manage', 'Administrar copias de seguridad', 'backups', 'Verificar, eliminar copias y configurar retención.');

-- Administrador: permisos completos, incluidos los nuevos.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin';

-- Gerencia: analítica y auditoría, sin acceso a descargas/copias de respaldo por defecto.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('analytics.view','analytics.preferences','audit.view','audit.export')
WHERE r.code = 'manager';

-- Registrar también esta migración en auditoría no es necesario: se importa una sola vez mediante phpMyAdmin.
