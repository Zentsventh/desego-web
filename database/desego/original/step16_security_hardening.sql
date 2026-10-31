-- DESEGO CEVICHERÍA — PASO 16
-- Seguridad final: rate limiting de API externa. Importar en la base de datos existente.
-- No elimina ni reinicia tablas o datos previos.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS api_rate_limit_windows (
    token_id BIGINT UNSIGNED NOT NULL,
    window_start DATETIME NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id, window_start),
    KEY idx_api_rate_window_start (window_start),
    CONSTRAINT fk_api_rate_limit_token FOREIGN KEY (token_id)
        REFERENCES api_access_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value)
VALUES ('external_api_requests_per_minute', '120');
