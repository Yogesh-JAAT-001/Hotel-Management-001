<?php
require_once '../../config.php';

initApiRequest(['POST']);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['error' => 'Invalid request payload'], 400);
}

$name = trim((string)($input['name'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$phoneInput = trim((string)($input['phone'] ?? ($input['phone_no'] ?? '')));
$password = (string)($input['password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if ($name === '' || $email === '' || $phoneInput === '' || $password === '' || $confirmPassword === '') {
    jsonResponse(['error' => 'Required fields missing: name, email, phone, password, and confirm password are required'], 400);
}

if (strlen($name) < 2 || strlen($name) > 100) {
    jsonResponse(['error' => 'Name must be between 2 and 100 characters'], 400);
}

if (!preg_match("/^[A-Za-z][A-Za-z .'-]{1,99}$/", $name)) {
    jsonResponse(['error' => 'Name contains invalid characters'], 400);
}

if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Invalid email format'], 400);
}

$phoneDigits = preg_replace('/\D+/', '', $phoneInput);
if (!is_string($phoneDigits) || strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
    jsonResponse(['error' => 'Phone number must contain 10 to 15 digits'], 400);
}

if ($password !== $confirmPassword) {
    jsonResponse(['error' => 'Password and confirm password do not match'], 400);
}

if (strlen($password) < 8 || strlen($password) > 72) {
    jsonResponse(['error' => 'Weak password: use 8 to 72 characters'], 400);
}

if (
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[^A-Za-z0-9]/', $password)
) {
    jsonResponse(['error' => 'Weak password: include uppercase, lowercase, number, and special character'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT guest_id FROM GUEST WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Email already exists'], 409);
    }

    $stmt = $pdo->prepare("SELECT guest_id FROM GUEST WHERE phone_no = ? LIMIT 1");
    $stmt->execute([$phoneDigits]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Phone number already exists'], 409);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO GUEST (name, phone_no, email, password)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$name, $phoneDigits, $email, $hashedPassword]);
    $guestId = (int)$pdo->lastInsertId();

    $_SESSION = [];
    refreshSessionId();
    regenerateCsrfToken();

    $_SESSION['user_id'] = $guestId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_phone'] = $phoneDigits;
    $_SESSION['user_role'] = 'guest';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful. You are now logged in.',
        'user' => [
            'id' => $guestId,
            'name' => $name,
            'email' => $email,
            'phone' => $phoneDigits,
            'type' => 'guest'
        ],
        'redirect' => appPath('/user/dashboard.php')
    ], 201);

} catch (PDOException $e) {
    if (($e->errorInfo[0] ?? '') === '23000') {
        jsonResponse(['error' => 'Email already exists'], 409);
    }
    error_log("Registration error: " . $e->getMessage());
    jsonResponse(['error' => 'Registration failed. Please try again.'], 500);
}
?>
