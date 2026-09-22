-- Migración incremental para segundo factor TOTP. No elimina ni reemplaza datos.
USE control_stock;

ALTER TABLE usuarios
    ADD COLUMN totp_secret_encrypted TEXT NULL AFTER rol,
    ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret_encrypted,
    ADD COLUMN totp_confirmed_at DATETIME NULL AFTER totp_enabled,
    ADD COLUMN totp_last_timeslice BIGINT UNSIGNED NULL AFTER totp_confirmed_at;

