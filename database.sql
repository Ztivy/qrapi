CREATE DATABASE IF NOT EXISTS qr_api_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE qr_api_db;

CREATE TABLE IF NOT EXISTS qr_codes (
    id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED    NULL COMMENT 'Usuario creador (opcional)',
    type             ENUM('text','url','wifi','geo') NOT NULL,
    content          TEXT            NOT NULL        COMMENT 'Contenido original',
    size             SMALLINT        NOT NULL DEFAULT 300 COMMENT 'Tamaño en px',
    error_correction ENUM('L','M','Q','H') NOT NULL DEFAULT 'M',
    file_path        VARCHAR(255)    NOT NULL COMMENT 'Ruta del archivo generado',
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at       DATETIME        NULL     COMMENT 'Fecha de expiración (opcional)',
    INDEX idx_type    (type),
    INDEX idx_user    (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── 2. Tabla de escaneos ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS qr_scans (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    qr_id      INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    scanned_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    country    VARCHAR(100) NULL COMMENT 'País (opcional)',
    FOREIGN KEY (qr_id) REFERENCES qr_codes(id) ON DELETE CASCADE,
    INDEX idx_qr_id   (qr_id),
    INDEX idx_scanned (scanned_at)
) ENGINE=InnoDB;