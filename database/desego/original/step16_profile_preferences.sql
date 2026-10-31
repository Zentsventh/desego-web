-- DESEGO — Preferencias de perfil funcionales
-- Importar una sola vez en la misma base de datos usada desde el Paso 16.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id BIGINT UNSIGNED NOT NULL,
    theme_tone TINYINT UNSIGNED NOT NULL DEFAULT 72,
    show_notification_badge TINYINT(1) NOT NULL DEFAULT 1,
    desktop_notifications TINYINT(1) NOT NULL DEFAULT 0,
    sound_notifications TINYINT(1) NOT NULL DEFAULT 0,
    default_view VARCHAR(20) NOT NULL DEFAULT 'home',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_user_preferences_tone CHECK (theme_tone BETWEEN 0 AND 100),
    CONSTRAINT chk_user_preferences_view CHECK (default_view IN ('home','orders','menu','reservations','profile'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_preferences (user_id)
SELECT id FROM users;
