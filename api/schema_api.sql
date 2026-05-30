-- ============================================================
-- Gunaso Management System - REST API Module
-- MySQL Schema for API Keys and API Logs Tables
-- बेसीशहर नगरपालिका
-- ============================================================
-- Run this AFTER your existing database is set up.
-- USE your existing database before running:
--   USE techcraf_besigunaso;
-- ============================================================

-- ------------------------------------------------------------
-- Table: api_keys
-- Stores authorized API clients (e.g. Municipality Dashboard)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `client_name`  VARCHAR(150)    NOT NULL COMMENT 'Human-readable name of the API consumer',
    `api_key`      VARCHAR(64)     NOT NULL COMMENT 'SHA-256 hex key, 64 chars',
    `status`       ENUM('active','inactive','revoked') NOT NULL DEFAULT 'active',
    `allowed_ips`  TEXT            DEFAULT NULL COMMENT 'Comma-separated IP whitelist; NULL = any IP allowed',
    `rate_limit`   SMALLINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Max requests per minute',
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_key` (`api_key`),
    KEY `idx_api_key_status` (`api_key`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='API Keys for external consumers of the Gunaso REST API';


-- ------------------------------------------------------------
-- Table: api_logs
-- Logs every inbound API request for auditing & debugging
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_logs` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `api_key_id`      INT UNSIGNED    DEFAULT NULL COMMENT 'FK to api_keys.id; NULL if auth failed',
    `endpoint`        VARCHAR(255)    NOT NULL COMMENT 'Requested URI path, e.g. /api/complaints.php',
    `ip_address`      VARCHAR(45)     NOT NULL COMMENT 'Supports IPv4 and IPv6',
    `request_method`  VARCHAR(10)     NOT NULL DEFAULT 'GET',
    `query_string`    TEXT            DEFAULT NULL COMMENT 'URL query parameters (sanitised)',
    `response_code`   SMALLINT UNSIGNED NOT NULL DEFAULT 200,
    `execution_ms`    SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Response time in milliseconds',
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_api_key_id`    (`api_key_id`),
    KEY `idx_created_at`    (`created_at`),
    KEY `idx_response_code` (`response_code`),
    CONSTRAINT `fk_log_api_key`
        FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log of all inbound REST API requests';


-- ------------------------------------------------------------
-- Table: api_rate_limit
-- Sliding-window counter for per-key rate limiting
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_rate_limit` (
    `api_key_id`   INT UNSIGNED    NOT NULL,
    `window_start` TIMESTAMP       NOT NULL COMMENT 'Start of the current 1-minute window',
    `hit_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`api_key_id`),
    CONSTRAINT `fk_rl_api_key`
        FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks per-key request count for rate limiting (1-minute window)';


-- ------------------------------------------------------------
-- Indexes on existing complaints table (safe to run again)
-- These speed up the filter queries used by API endpoints.
-- ------------------------------------------------------------
CREATE INDEX IF NOT EXISTS `idx_complaints_status`
    ON `complaints` (`status`);

CREATE INDEX IF NOT EXISTS `idx_complaints_created`
    ON `complaints` (`created_at`);

CREATE INDEX IF NOT EXISTS `idx_complaints_branch`
    ON `complaints` (`branch_id`);

CREATE INDEX IF NOT EXISTS `idx_complaints_type`
    ON `complaints` (`type_id`);


-- ------------------------------------------------------------
-- Sample API key  (REPLACE before going live!)
-- Key value: MunicipalityDashboard2026SecureKey  (plaintext)
-- Stored as SHA-256 hex:
--   SELECT SHA2('MunicipalityDashboard2026SecureKey', 256);
-- ------------------------------------------------------------
INSERT IGNORE INTO `api_keys`
    (`client_name`, `api_key`, `status`, `rate_limit`)
VALUES
    (
        'Besi Shahar Municipality Main Dashboard',
        SHA2('MunicipalityDashboard2026SecureKey', 256),
        'active',
        100
    );

-- ============================================================
-- END OF API SCHEMA
-- ============================================================
