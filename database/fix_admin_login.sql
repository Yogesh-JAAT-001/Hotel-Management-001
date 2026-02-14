-- The Heartland Abode: Admin login fix (XAMPP/phpMyAdmin-safe)
-- No DELIMITER blocks, no user variables, no := assignments.
-- Default admin:
--   Email: yogeshkumar@heartlandabode.com
--   Username: Yogesh Admin
--   Password: Admin@123

CREATE DATABASE IF NOT EXISTS heartland_abode
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE heartland_abode;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    admin_id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(120) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'SuperAdmin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id),
    UNIQUE KEY ux_admin_users_email (email),
    KEY idx_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE admin_users
    CONVERT TO CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

ALTER TABLE admin_users
    ADD COLUMN IF NOT EXISTS username VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'SuperAdmin',
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS admin_auth_tokens (
    token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_ref_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (token_id),
    UNIQUE KEY ux_admin_auth_tokens_selector (selector),
    KEY idx_admin_auth_tokens_admin (admin_ref_id),
    KEY idx_admin_auth_tokens_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_users (username, email, password, role, is_active, created_at)
SELECT
    'Yogesh Admin',
    'yogeshkumar@heartlandabode.com',
    '$2y$10$/ktjvCPvt26wvZnE3KEgIeFOy5JsGmcGlTYTksROpyvKBqDJMxKyy',
    'SuperAdmin',
    1,
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM admin_users
    WHERE CONVERT(email USING utf8mb4) COLLATE utf8mb4_unicode_ci =
          CONVERT('yogeshkumar@heartlandabode.com' USING utf8mb4) COLLATE utf8mb4_unicode_ci
);

UPDATE admin_users
SET
    username = 'Yogesh Admin',
    password = '$2y$10$/ktjvCPvt26wvZnE3KEgIeFOy5JsGmcGlTYTksROpyvKBqDJMxKyy',
    role = 'SuperAdmin',
    is_active = 1
WHERE CONVERT(email USING utf8mb4) COLLATE utf8mb4_unicode_ci =
      CONVERT('yogeshkumar@heartlandabode.com' USING utf8mb4) COLLATE utf8mb4_unicode_ci;

SELECT
    'ADMIN_LOGIN_READY' AS status,
    'yogeshkumar@heartlandabode.com' AS email,
    'Yogesh Admin' AS username,
    'Admin@123' AS password_hint;
