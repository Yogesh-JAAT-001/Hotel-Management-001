<?php
function loadEnvFile($path) {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim((string)$parts[0]);
        $value = trim((string)$parts[1]);
        $value = trim($value, "\"'");
        if ($key === '') {
            continue;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function envValue($key, $default = null) {
    $value = getenv((string)$key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return $default;
}

loadEnvFile(__DIR__ . '/.env');

// Database Configuration
define('DB_HOST', (string)envValue('DB_HOST', 'localhost'));
define('DB_USER', (string)envValue('DB_USER', 'root'));
define('DB_PASS', (string)envValue('DB_PASS', ''));
define('DB_NAME', (string)envValue('DB_NAME', 'heartland_abode'));

// Application Configuration
define('APP_NAME', (string)envValue('APP_NAME', 'The Heartland Abode'));
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Security Configuration
define('JWT_SECRET', (string)envValue('JWT_SECRET', 'change-this-secret-key'));
define('SESSION_TIMEOUT', (int)envValue('SESSION_TIMEOUT', 3600)); // 1 hour
define('ADMIN_REMEMBER_COOKIE', 'heartland_admin_remember');
define('ADMIN_REMEMBER_DAYS', (int)envValue('ADMIN_REMEMBER_DAYS', 30));

// Normalize app base path from current deployment directory.
$appBasePath = '/heartland_abode';
if (PHP_SAPI !== 'cli' && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $appRoot = realpath(__DIR__);
    if ($documentRoot && $appRoot && strpos($appRoot, $documentRoot) === 0) {
        $relative = trim(str_replace('\\', '/', substr($appRoot, strlen($documentRoot))), '/');
        if ($relative === '') {
            $appBasePath = '';
        } else {
            $segments = explode('/', $relative);
            $encodedSegments = array_map('rawurlencode', $segments);
            $appBasePath = '/' . implode('/', $encodedSegments);
        }
    }
}
define('APP_BASE_PATH', $appBasePath);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('APP_URL', $scheme . '://' . $host . APP_BASE_PATH);
define('APP_COOKIE_PATH', APP_BASE_PATH === '' ? '/' : (rtrim(APP_BASE_PATH, '/') . '/'));

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_COOKIE_PATH,
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    session_name('heartland_session');
    session_start();
}

// Helper Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function appPath($path = '/') {
    $normalizedPath = '/' . ltrim((string)$path, '/');
    $basePath = APP_BASE_PATH;

    if ($basePath === '' || $basePath === '/') {
        return $normalizedPath;
    }

    if ($normalizedPath === '/') {
        return $basePath . '/';
    }

    return $basePath . $normalizedPath;
}

function appUrl($path = '') {
    if ($path === '' || $path === '/') {
        return rtrim(APP_URL, '/') . '/';
    }
    return rtrim(APP_URL, '/') . '/' . ltrim((string)$path, '/');
}

function isApiRequest() {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/api/') !== false) {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'guest';
}

function isAdmin() {
    return isset($_SESSION['admin_id']) && ($_SESSION['user_role'] ?? '') === 'admin';
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function clearSession() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function refreshSessionId() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function getSessionLastActivity() {
    if (isset($_SESSION['last_activity'])) {
        return (int)$_SESSION['last_activity'];
    }
    if (isset($_SESSION['login_time'])) {
        return (int)$_SESSION['login_time'];
    }
    return 0;
}

function touchSessionActivity() {
    $_SESSION['last_activity'] = time();
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

function regenerateCsrfToken() {
    $_SESSION['csrf_token'] = generateToken(32);
    return $_SESSION['csrf_token'];
}

function setAppCookie($name, $value, $expiresAt) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie($name, $value, [
        'expires' => (int)$expiresAt,
        'path' => APP_COOKIE_PATH,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearAppCookie($name) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => APP_COOKIE_PATH,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function verifyCsrfToken($token = null) {
    if ($token === null || $token === '') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return $sessionToken !== '' && hash_equals($sessionToken, (string)$token);
}

function requireCsrfToken() {
    if (!verifyCsrfToken()) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Invalid CSRF token'], 419);
        }
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function getAllowedOrigins() {
    $origins = [];
    $appParts = parse_url(APP_URL);
    if (!empty($appParts['scheme']) && !empty($appParts['host'])) {
        $appOrigin = $appParts['scheme'] . '://' . $appParts['host'];
        if (!empty($appParts['port'])) {
            $appOrigin .= ':' . $appParts['port'];
        }
        $origins[] = $appOrigin;
    }

    $currentScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $origins[] = $currentScheme . '://' . $_SERVER['HTTP_HOST'];
    }

    $origins[] = 'http://localhost';
    $origins[] = 'http://127.0.0.1';
    $origins[] = 'https://localhost';
    $origins[] = 'https://127.0.0.1';

    return array_values(array_unique($origins));
}

function applyCorsHeaders($allowedMethods = ['GET'], $allowedHeaders = ['Content-Type', 'X-CSRF-Token']) {
    header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
    header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true;
    }

    if (in_array($origin, getAllowedOrigins(), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
        return true;
    }

    return false;
}

function initApiRequest($allowedMethods = ['GET'], $requireCsrf = true) {
    header('Content-Type: application/json');
    $originAllowed = applyCorsHeaders($allowedMethods);
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($requestMethod === 'OPTIONS') {
        http_response_code($originAllowed ? 204 : 403);
        exit();
    }

    if (!$originAllowed && isset($_SERVER['HTTP_ORIGIN'])) {
        jsonResponse(['error' => 'Origin not allowed'], 403);
    }

    if (!in_array($requestMethod, $allowedMethods, true)) {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $csrfMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    if ($requireCsrf && in_array($requestMethod, $csrfMethods, true)) {
        requireCsrfToken();
    }
}

// ---------------------------
// DB schema compatibility
// ---------------------------
// This project has multiple SQL schema variants in circulation (legacy + updated).
// These helpers let the app detect columns/tables at runtime and avoid fatal SQL errors.
function dbHasTable(PDO $pdo, $tableName) {
    static $cache = [];
    $key = strtoupper((string)$tableName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([(string)$tableName]);
    $cache[$key] = ((int)($stmt->fetch()['total'] ?? 0)) > 0;
    return $cache[$key];
}

function dbHasColumn(PDO $pdo, $tableName, $columnName) {
    static $cache = [];
    $key = strtoupper((string)$tableName) . '.' . strtoupper((string)$columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([(string)$tableName, (string)$columnName]);
    $cache[$key] = ((int)($stmt->fetch()['total'] ?? 0)) > 0;
    return $cache[$key];
}

function dbFirstExistingColumn(PDO $pdo, $tableName, array $candidates) {
    foreach ($candidates as $candidate) {
        if (dbHasColumn($pdo, $tableName, $candidate)) {
            return (string)$candidate;
        }
    }
    return null;
}

function adminUserSchema(PDO $pdo) {
    return [
        'id' => dbFirstExistingColumn($pdo, 'admin_users', ['admin_id', 'id']),
        'username' => dbFirstExistingColumn($pdo, 'admin_users', ['username', 'full_name']),
        'email' => dbFirstExistingColumn($pdo, 'admin_users', ['email']),
        'password' => dbFirstExistingColumn($pdo, 'admin_users', ['password']),
        'role' => dbFirstExistingColumn($pdo, 'admin_users', ['role']),
        'is_active' => dbFirstExistingColumn($pdo, 'admin_users', ['is_active']),
        'last_login' => dbFirstExistingColumn($pdo, 'admin_users', ['last_login']),
        'created_at' => dbFirstExistingColumn($pdo, 'admin_users', ['created_at'])
    ];
}

function ensureAdminUsersTable(PDO $pdo) {
    if (!dbHasTable($pdo, 'admin_users')) {
        $pdo->exec("
            CREATE TABLE admin_users (
                admin_id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(120) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'superadmin',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return;
    }

    if (!dbHasColumn($pdo, 'admin_users', 'username')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN username VARCHAR(120) NULL");
    }
    if (!dbHasColumn($pdo, 'admin_users', 'email')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN email VARCHAR(255) NULL");
    }
    if (!dbHasColumn($pdo, 'admin_users', 'password')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN password VARCHAR(255) NULL");
    }
    if (!dbHasColumn($pdo, 'admin_users', 'role')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'superadmin'");
    }
    if (!dbHasColumn($pdo, 'admin_users', 'is_active')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
    if (!dbHasColumn($pdo, 'admin_users', 'created_at')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
}

function normalizeAdminRoleForColumn(PDO $pdo, $roleColumn) {
    if ($roleColumn === null) {
        return 'superadmin';
    }

    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'admin_users'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$roleColumn]);
    $columnType = strtolower((string)($stmt->fetchColumn() ?: ''));

    if ($columnType === '' || strpos($columnType, 'enum(') !== 0) {
        return 'superadmin';
    }

    if (strpos($columnType, "'superadmin'") !== false) {
        return 'superadmin';
    }
    if (strpos($columnType, "'super admin'") !== false) {
        return 'super admin';
    }
    if (strpos($columnType, "'superadmin'") === false && strpos($columnType, "'admin'") !== false) {
        return 'Admin';
    }

    return 'superadmin';
}

function ensureDefaultAdminAccount(PDO $pdo) {
    ensureAdminUsersTable($pdo);
    $schema = adminUserSchema($pdo);

    if (empty($schema['id']) || empty($schema['email']) || empty($schema['password'])) {
        return false;
    }

    $adminEmail = 'yogeshkumar@heartlandabode.com';
    $adminUsername = 'Yogesh Admin';
    $adminPasswordHash = password_hash('Admin@123', PASSWORD_BCRYPT);
    $roleValue = normalizeAdminRoleForColumn($pdo, $schema['role']);

    $pdo->beginTransaction();
    try {
        $select = $pdo->prepare("
            SELECT {$schema['id']} AS admin_pk
            FROM admin_users
            WHERE CONVERT({$schema['email']} USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
            LIMIT 1
        ");
        $select->execute([$adminEmail]);
        $existing = $select->fetch();

        if ($existing) {
            $set = [];
            $params = [];

            if (!empty($schema['username'])) {
                $set[] = "{$schema['username']} = ?";
                $params[] = $adminUsername;
            }
            $set[] = "{$schema['email']} = ?";
            $params[] = $adminEmail;

            $set[] = "{$schema['password']} = ?";
            $params[] = $adminPasswordHash;

            if (!empty($schema['role'])) {
                $set[] = "{$schema['role']} = ?";
                $params[] = $roleValue;
            }
            if (!empty($schema['is_active'])) {
                $set[] = "{$schema['is_active']} = 1";
            }

            $params[] = (int)$existing['admin_pk'];
            $sql = "UPDATE admin_users SET " . implode(', ', $set) . " WHERE {$schema['id']} = ?";
            $update = $pdo->prepare($sql);
            $update->execute($params);
        } else {
            $columns = [];
            $values = [];
            $params = [];

            if (!empty($schema['username'])) {
                $columns[] = $schema['username'];
                $values[] = '?';
                $params[] = $adminUsername;
            }
            $columns[] = $schema['email'];
            $values[] = '?';
            $params[] = $adminEmail;

            $columns[] = $schema['password'];
            $values[] = '?';
            $params[] = $adminPasswordHash;

            if (!empty($schema['role'])) {
                $columns[] = $schema['role'];
                $values[] = '?';
                $params[] = $roleValue;
            }
            if (!empty($schema['is_active'])) {
                $columns[] = $schema['is_active'];
                $values[] = '1';
            }

            $sql = "INSERT INTO admin_users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
            $insert = $pdo->prepare($sql);
            $insert->execute($params);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logSystemError('ensure_default_admin_account', (string)$e->getMessage());
        return false;
    }
}

function ensureAdminRememberTable(PDO $pdo) {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_auth_tokens (
            token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_ref_id INT NOT NULL,
            selector CHAR(24) NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_admin_auth_tokens_admin (admin_ref_id),
            INDEX idx_admin_auth_tokens_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // One-time migration from legacy selector size.
    try {
        $pdo->exec("ALTER TABLE admin_auth_tokens MODIFY COLUMN selector CHAR(24) NOT NULL");
    } catch (Throwable $e) {
        // No action needed.
    }

    $ensured = true;
}

function clearAdminRememberCookie(PDO $pdo = null) {
    $cookieValue = $_COOKIE[ADMIN_REMEMBER_COOKIE] ?? '';
    if ($pdo instanceof PDO && $cookieValue !== '') {
        ensureAdminRememberTable($pdo);
        $parts = explode(':', $cookieValue, 2);
        if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/i', $parts[0])) {
            $stmt = $pdo->prepare("DELETE FROM admin_auth_tokens WHERE selector = ?");
            $stmt->execute([strtolower($parts[0])]);
        }
    }

    unset($_COOKIE[ADMIN_REMEMBER_COOKIE]);
    clearAppCookie(ADMIN_REMEMBER_COOKIE);
}

function issueAdminRememberToken(PDO $pdo, $adminId) {
    ensureAdminRememberTable($pdo);

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expiresAtTs = time() + (ADMIN_REMEMBER_DAYS * 86400);
    $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);

    $deleteStmt = $pdo->prepare("DELETE FROM admin_auth_tokens WHERE admin_ref_id = ?");
    $deleteStmt->execute([(int)$adminId]);

    $insert = $pdo->prepare("
        INSERT INTO admin_auth_tokens (admin_ref_id, selector, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $insert->execute([(int)$adminId, $selector, $tokenHash, $expiresAt]);

    $cookiePayload = $selector . ':' . $validator;
    setAppCookie(ADMIN_REMEMBER_COOKIE, $cookiePayload, $expiresAtTs);
}

function establishAdminSession(array $adminUser, array $schema) {
    $idCol = $schema['id'] ?? null;
    if ($idCol === null || !isset($adminUser[$idCol])) {
        return false;
    }

    $nameCol = $schema['username'] ?? null;
    $roleCol = $schema['role'] ?? null;
    $emailCol = $schema['email'] ?? null;

    $adminId = (int)$adminUser[$idCol];
    $adminName = $nameCol !== null ? (string)($adminUser[$nameCol] ?? '') : '';
    if ($adminName === '') {
        $adminName = (string)($emailCol !== null ? ($adminUser[$emailCol] ?? 'Admin') : 'Admin');
    }
    $adminRole = $roleCol !== null ? (string)($adminUser[$roleCol] ?? 'admin') : 'admin';
    $adminEmail = $emailCol !== null ? (string)($adminUser[$emailCol] ?? '') : '';

    $_SESSION = [];
    refreshSessionId();
    regenerateCsrfToken();

    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_name'] = $adminName;
    $_SESSION['admin_email'] = $adminEmail;
    $_SESSION['admin_role'] = $adminRole;
    $_SESSION['user_role'] = 'admin';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    return true;
}

function tryAutoLoginAdminFromRememberCookie(PDO $pdo) {
    if (isset($_SESSION['admin_id']) || isset($_SESSION['user_id'])) {
        return false;
    }

    $cookieValue = trim((string)($_COOKIE[ADMIN_REMEMBER_COOKIE] ?? ''));
    if ($cookieValue === '') {
        return false;
    }

    $parts = explode(':', $cookieValue, 2);
    if (count($parts) !== 2) {
        clearAdminRememberCookie($pdo);
        return false;
    }

    $selector = strtolower(trim((string)$parts[0]));
    $validator = strtolower(trim((string)$parts[1]));
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        clearAdminRememberCookie($pdo);
        return false;
    }

    ensureAdminRememberTable($pdo);
    $tokenStmt = $pdo->prepare("
        SELECT token_id, admin_ref_id, token_hash, expires_at
        FROM admin_auth_tokens
        WHERE selector = ?
        LIMIT 1
    ");
    $tokenStmt->execute([$selector]);
    $tokenRow = $tokenStmt->fetch();

    if (!$tokenRow) {
        clearAdminRememberCookie($pdo);
        return false;
    }

    if (strtotime((string)$tokenRow['expires_at']) < time()) {
        $delete = $pdo->prepare("DELETE FROM admin_auth_tokens WHERE token_id = ?");
        $delete->execute([(int)$tokenRow['token_id']]);
        clearAdminRememberCookie($pdo);
        return false;
    }

    $validatorHash = hash('sha256', $validator);
    if (!hash_equals((string)$tokenRow['token_hash'], $validatorHash)) {
        $delete = $pdo->prepare("DELETE FROM admin_auth_tokens WHERE token_id = ?");
        $delete->execute([(int)$tokenRow['token_id']]);
        clearAdminRememberCookie($pdo);
        return false;
    }

    ensureDefaultAdminAccount($pdo);
    $schema = adminUserSchema($pdo);
    if (empty($schema['id']) || empty($schema['email'])) {
        clearAdminRememberCookie($pdo);
        return false;
    }

    $where = ["{$schema['id']} = ?"];
    if (!empty($schema['is_active'])) {
        $where[] = "{$schema['is_active']} = 1";
    }

    $adminStmt = $pdo->prepare("SELECT * FROM admin_users WHERE " . implode(' AND ', $where) . " LIMIT 1");
    $adminStmt->execute([(int)$tokenRow['admin_ref_id']]);
    $adminUser = $adminStmt->fetch();

    if (!$adminUser || !establishAdminSession($adminUser, $schema)) {
        clearAdminRememberCookie($pdo);
        return false;
    }

    // Rotate token after successful remember-login.
    $delete = $pdo->prepare("DELETE FROM admin_auth_tokens WHERE token_id = ?");
    $delete->execute([(int)$tokenRow['token_id']]);
    issueAdminRememberToken($pdo, (int)$tokenRow['admin_ref_id']);

    if (!empty($schema['last_login'])) {
        $updateLogin = $pdo->prepare("UPDATE admin_users SET {$schema['last_login']} = CURRENT_TIMESTAMP WHERE {$schema['id']} = ?");
        $updateLogin->execute([(int)$tokenRow['admin_ref_id']]);
    }

    logLoginAttempt('admin', (string)($_SESSION['admin_email'] ?? ''), true);
    return true;
}

function ensureAuditTables(PDO $pdo) {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS error_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                context VARCHAR(120) NOT NULL,
                error_message TEXT NOT NULL,
                meta TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_error_logs_context (context),
                INDEX idx_error_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        error_log('Audit table ensure error: ' . $e->getMessage());
    }

    $ensured = true;
}

function getClientIpAddress() {
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = trim((string)$_SERVER[$key]);
            if ($key === 'HTTP_X_FORWARDED_FOR' && strpos($value, ',') !== false) {
                $parts = explode(',', $value);
                $value = trim((string)$parts[0]);
            }
            return substr($value, 0, 64);
        }
    }
    return 'unknown';
}

function logAdminAction($actionType, $targetTable = null, $targetId = null, $details = null) {
    global $pdo;

    if (!($pdo instanceof PDO)) {
        return;
    }

    try {
        ensureAuditTables($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address, user_agent)
            VALUES (:admin_id, :action_type, :target_table, :target_id, :details, :ip_address, :user_agent)
        ");
        $stmt->execute([
            ':admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            ':action_type' => substr((string)$actionType, 0, 80),
            ':target_table' => $targetTable !== null ? substr((string)$targetTable, 0, 80) : null,
            ':target_id' => $targetId !== null ? substr((string)$targetId, 0, 80) : null,
            ':details' => $details !== null ? substr((string)$details, 0, 65000) : null,
            ':ip_address' => getClientIpAddress(),
            ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        ]);
    } catch (Throwable $e) {
        error_log('logAdminAction error: ' . $e->getMessage());
    }
}

function logLoginAttempt($userType, $identifier, $success) {
    global $pdo;

    if (!($pdo instanceof PDO)) {
        return;
    }

    try {
        ensureAuditTables($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (user_type, identifier, success, ip_address, user_agent)
            VALUES (:user_type, :identifier, :success, :ip_address, :user_agent)
        ");
        $stmt->execute([
            ':user_type' => substr((string)$userType, 0, 20),
            ':identifier' => substr((string)$identifier, 0, 255),
            ':success' => $success ? 1 : 0,
            ':ip_address' => getClientIpAddress(),
            ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        ]);
    } catch (Throwable $e) {
        error_log('logLoginAttempt error: ' . $e->getMessage());
    }
}

function logSystemError($context, $errorMessage, $meta = null) {
    global $pdo;

    error_log($context . ': ' . $errorMessage);

    if (!($pdo instanceof PDO)) {
        return;
    }

    try {
        ensureAuditTables($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO error_logs (context, error_message, meta)
            VALUES (:context, :error_message, :meta)
        ");
        $stmt->execute([
            ':context' => substr((string)$context, 0, 120),
            ':error_message' => substr((string)$errorMessage, 0, 65000),
            ':meta' => $meta !== null ? substr((string)$meta, 0, 65000) : null
        ]);
    } catch (Throwable $e) {
        error_log('logSystemError error: ' . $e->getMessage());
    }
}

// Enforce idle session timeout for authenticated users.
$hasAuthSession = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
if (!$hasAuthSession) {
    try {
        tryAutoLoginAdminFromRememberCookie($pdo);
    } catch (Throwable $e) {
        logSystemError('admin_remember_autologin', (string)$e->getMessage());
        clearAdminRememberCookie($pdo);
    }
}

$hasAuthSession = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
if ($hasAuthSession) {
    $lastActivity = getSessionLastActivity();
    if ($lastActivity > 0 && (time() - $lastActivity) > SESSION_TIMEOUT) {
        clearSession();
        if (isApiRequest()) {
            jsonResponse(['error' => 'Session expired. Please login again.'], 401);
        }
        redirect(appPath('/index.php?session_expired=1'));
    }
    touchSessionActivity();
}
?>
