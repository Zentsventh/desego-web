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
