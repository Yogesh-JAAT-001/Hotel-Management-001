<?php
require_once '../../config.php';

initApiRequest(['POST']);

function verifyStoredPassword($inputPassword, $storedPassword, $allowLegacy = true) {
    $inputPassword = (string)$inputPassword;
    $storedPassword = (string)$storedPassword;

    if ($storedPassword === '' || $inputPassword === '') {
        return [
            'valid' => false,
            'needs_upgrade' => false
        ];
    }

    if (password_verify($inputPassword, $storedPassword)) {
        return [
            'valid' => true,
            'needs_upgrade' => password_needs_rehash($storedPassword, PASSWORD_BCRYPT)
        ];
    }

    if (!$allowLegacy) {
        return [
            'valid' => false,
            'needs_upgrade' => false
        ];
    }

    // Backward compatibility for legacy guest data.
    if (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), md5($inputPassword))) {
        return [
            'valid' => true,
            'needs_upgrade' => true
        ];
    }

    if (preg_match('/^[a-f0-9]{40}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), sha1($inputPassword))) {
        return [
            'valid' => true,
            'needs_upgrade' => true
        ];
    }

    if (hash_equals($storedPassword, $inputPassword)) {
        return [
            'valid' => true,
            'needs_upgrade' => true
        ];
    }

    return [
        'valid' => false,
        'needs_upgrade' => false
    ];
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['error' => 'Invalid request payload'], 400);
}

$password = (string)($input['password'] ?? '');
$userType = strtolower(trim((string)($input['user_type'] ?? 'guest')));

if (!in_array($userType, ['guest', 'admin'], true)) {
    jsonResponse(['error' => 'Invalid user type'], 400);
}

if ($password === '') {
    jsonResponse(['error' => 'Password is required'], 400);
}

try {
    if ($userType === 'admin') {
        $identifier = trim((string)($input['identifier'] ?? ($input['email'] ?? '')));
        $rememberMe = !empty($input['remember_me']);
        $invalidMessage = 'Invalid email/username or password';

        if ($identifier === '') {
            jsonResponse(['error' => $invalidMessage], 401);
        }

        ensureDefaultAdminAccount($pdo);
        $schema = adminUserSchema($pdo);
        if (empty($schema['id']) || empty($schema['email']) || empty($schema['password'])) {
            logSystemError('admin_login_schema', 'admin_users schema missing required columns');
            jsonResponse(['error' => 'Admin account configuration error'], 500);
        }

        $emailCol = $schema['email'];
        $usernameCol = $schema['username'];
        $idCol = $schema['id'];
        $passwordCol = $schema['password'];
        $isActiveCol = $schema['is_active'];
        $lastLoginCol = $schema['last_login'];

        $identifierFields = [];
        $params = [];

        $identifierFields[] = "CONVERT({$emailCol} USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci";
        $params[] = $identifier;

        if (!empty($usernameCol) && strtolower($usernameCol) !== strtolower($emailCol)) {
            $identifierFields[] = "CONVERT({$usernameCol} USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci";
            $params[] = $identifier;
        }

        $fullNameCol = dbFirstExistingColumn($pdo, 'admin_users', ['full_name']);
        if (!empty($fullNameCol) && strtolower($fullNameCol) !== strtolower((string)$usernameCol) && strtolower($fullNameCol) !== strtolower($emailCol)) {
            $identifierFields[] = "CONVERT({$fullNameCol} USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci";
            $params[] = $identifier;
        }

        $whereParts = ['(' . implode(' OR ', $identifierFields) . ')'];
        if (!empty($isActiveCol)) {
            $whereParts[] = "{$isActiveCol} = 1";
        }

        $sql = "SELECT * FROM admin_users WHERE " . implode(' AND ', $whereParts) . " ORDER BY {$idCol} ASC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $adminUser = $stmt->fetch();

        $check = $adminUser ? verifyStoredPassword($password, (string)($adminUser[$passwordCol] ?? ''), false) : ['valid' => false, 'needs_upgrade' => false];
        if (!$adminUser || !$check['valid']) {
            logLoginAttempt('admin', $identifier, false);
            jsonResponse(['error' => $invalidMessage], 401);
        }

        $adminId = (int)$adminUser[$idCol];
        if ($adminId <= 0 || !establishAdminSession($adminUser, $schema)) {
            logSystemError('admin_login_session', 'Failed to establish admin session');
            jsonResponse(['error' => 'Unable to start admin session'], 500);
        }

        if ($check['needs_upgrade']) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $upgradeStmt = $pdo->prepare("UPDATE admin_users SET {$passwordCol} = ? WHERE {$idCol} = ?");
            $upgradeStmt->execute([$newHash, $adminId]);
        }

        if (!empty($lastLoginCol)) {
            $lastStmt = $pdo->prepare("UPDATE admin_users SET {$lastLoginCol} = CURRENT_TIMESTAMP WHERE {$idCol} = ?");
            $lastStmt->execute([$adminId]);
        }

        if ($rememberMe) {
            issueAdminRememberToken($pdo, $adminId);
        } else {
            clearAdminRememberCookie($pdo);
        }

        logLoginAttempt('admin', $identifier, true);
        jsonResponse([
            'success' => true,
            'message' => 'Admin login successful',
            'user' => [
                'id' => $adminId,
                'name' => (string)($_SESSION['admin_name'] ?? 'Admin'),
                'email' => (string)($_SESSION['admin_email'] ?? ''),
                'role' => (string)($_SESSION['admin_role'] ?? 'admin'),
                'type' => 'admin'
            ],
            'redirect' => appPath('/admin/dashboard.php')
        ]);
    }

    // Guest login
    $identifier = trim((string)($input['identifier'] ?? ($input['email'] ?? '')));
    if ($identifier === '' || !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }

    $stmt = $pdo->prepare("SELECT guest_id, name, email, password, phone_no FROM GUEST WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        logLoginAttempt('guest', $identifier, false);
        jsonResponse(['error' => 'No account found for this email'], 401);
    }

    $passwordCheck = verifyStoredPassword($password, (string)($user['password'] ?? ''), true);
    if (!$passwordCheck['valid']) {
        logLoginAttempt('guest', $identifier, false);
        jsonResponse(['error' => 'Invalid password'], 401);
    }

    if ($passwordCheck['needs_upgrade']) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $upgradeStmt = $pdo->prepare("UPDATE GUEST SET password = ? WHERE guest_id = ?");
        $upgradeStmt->execute([$newHash, $user['guest_id']]);
    }

    $_SESSION = [];
    refreshSessionId();
    regenerateCsrfToken();

    $_SESSION['user_id'] = (int)$user['guest_id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_phone'] = (string)$user['phone_no'];
    $_SESSION['user_role'] = 'guest';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    logLoginAttempt('guest', $identifier, true);

    jsonResponse([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => (int)$user['guest_id'],
            'name' => (string)$user['name'],
            'email' => (string)$user['email'],
            'phone' => (string)$user['phone_no'],
            'type' => 'guest'
        ],
        'redirect' => appPath('/user/dashboard.php')
    ]);
} catch (PDOException $e) {
    $idForLog = (string)($input['identifier'] ?? ($input['email'] ?? ''));
    logSystemError('auth_login', (string)$e->getMessage(), 'identifier=' . $idForLog . '; user_type=' . $userType);
    jsonResponse(['error' => 'Login failed. Please try again.'], 500);
}
?>
