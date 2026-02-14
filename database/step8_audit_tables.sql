CREATE DATABASE IF NOT EXISTS heartland_abode;
USE heartland_abode;

CREATE TABLE IF NOT EXISTS admin_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    action_type VARCHAR(80) NOT NULL,
    target_table VARCHAR(80) NULL,
    target_id VARCHAR(80) NULL,
    details TEXT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_actions_admin (admin_id),
    INDEX idx_admin_actions_action (action_type),
    INDEX idx_admin_actions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type VARCHAR(20) NOT NULL,
    identifier VARCHAR(255) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_identifier (identifier),
    INDEX idx_login_attempts_success (success),
    INDEX idx_login_attempts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    context VARCHAR(120) NOT NULL,
    error_message TEXT NOT NULL,
    meta TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_error_logs_context (context),
    INDEX idx_error_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
