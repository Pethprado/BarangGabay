-- BarangGabay — Migration 004
-- Adds the sms_logs table used by SmsController / SmsLog / SemaphoreSmsService.
-- Safe to run on an existing database (CREATE TABLE IF NOT EXISTS).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS sms_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone           VARCHAR(20)     NOT NULL,
    message         TEXT            NOT NULL,
    type            VARCHAR(50)     NOT NULL DEFAULT 'general',
    reference_id    INT UNSIGNED    NULL,
    status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
    response        TEXT            NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type   (type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
