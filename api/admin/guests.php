<?php
require_once '../../config.php';

initApiRequest(['GET', 'POST', 'PUT', 'DELETE']);

if (!isAdmin()) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin access required',
        'error' => 'Admin access required',
        'data' => null
    ], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        guestGet();
        break;
    case 'POST':
        guestCreate();
        break;
    case 'PUT':
        guestUpdate();
        break;
    case 'DELETE':
        guestDelete();
        break;
    default:
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Method not allowed',
            'error' => 'Method not allowed',
            'data' => null
        ], 405);
}

function guestValidatePayload($payload, $isUpdate = false) {
    $errors = [];

    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $phone = trim((string)($payload['phone_no'] ?? ($payload['phone'] ?? '')));
    $ageRaw = trim((string)($payload['age'] ?? ''));
    $age = ($ageRaw === '') ? null : (int)$ageRaw;

    if ($name === '') {
        $errors[] = 'Name is required';
    } elseif (mb_strlen($name) > 255) {
        $errors[] = 'Name is too long';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if ($phone === '') {
        $errors[] = 'Phone is required';
    } elseif (!preg_match('/^[0-9+\-() ]{7,20}$/', $phone)) {
        $errors[] = 'Invalid phone format';
    }

    if ($age !== null && ($age < 0 || $age > 120)) {
        $errors[] = 'Age must be between 0 and 120';
    }

    $gender = trim((string)($payload['gender'] ?? ''));
    if ($gender !== '' && !in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Invalid gender';
    }

    if (!$isUpdate) {
        $password = (string)($payload['password'] ?? '');
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
    }

    return [
        'errors' => $errors,
        'data' => [
            'name' => $name,
            'email' => $email,
            'phone_no' => $phone,
            'address' => trim((string)($payload['address'] ?? '')),
            'gender' => $gender,
            'age' => $age,
            'in_id' => trim((string)($payload['in_id'] ?? '')),
            'password' => (string)($payload['password'] ?? '')
        ]
    ];
}

function guestGet() {
    global $pdo;

    $guestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $reservationDateColumn = dbHasColumn($pdo, 'RESERVATION', 'created_at')
        ? 'created_at'
        : (dbHasColumn($pdo, 'RESERVATION', 'r_date') ? 'r_date' : 'check_in');

    try {
        if ($guestId > 0) {
            $stmt = $pdo->prepare("\n                SELECT\n                    g.*,\n                    COUNT(r.res_id) AS total_reservations,\n                    MAX(r.{$reservationDateColumn}) AS last_booking,\n                    COALESCE(SUM(CASE WHEN r.status IN ('Confirmed', 'Checked-in', 'Checked-out') THEN r.total_price ELSE 0 END), 0) AS total_spent\n                FROM GUEST g\n                LEFT JOIN RESERVATION r ON r.guest_id = g.guest_id\n                WHERE g.guest_id = ?\n                GROUP BY g.guest_id\n                LIMIT 1\n            ");
            $stmt->execute([$guestId]);
            $guest = $stmt->fetch();

            if (!$guest) {
                jsonResponse([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Guest not found',
                    'error' => 'Guest not found',
                    'data' => null
                ], 404);
            }

            $reservationStmt = $pdo->prepare("\n                SELECT\n                    res.res_id,\n                    res.check_in,\n                    res.check_out,\n                    res.status,\n                    res.total_price,\n                    r.room_no,\n                    rt.name AS room_type\n                FROM RESERVATION res\n                LEFT JOIN ROOMS r ON r.room_id = res.room_id\n                LEFT JOIN ROOM_TYPE rt ON rt.room_type_id = r.room_type_id\n                WHERE res.guest_id = ?\n                ORDER BY res.{$reservationDateColumn} DESC, res.res_id DESC\n                LIMIT 20\n            ");
            $reservationStmt->execute([$guestId]);
            $reservations = $reservationStmt->fetchAll();

            jsonResponse([
                'success' => true,
                'status' => 'success',
                'message' => 'Guest loaded successfully',
                'data' => [
                    'guest' => $guest,
                    'reservations' => $reservations
                ]
            ]);
        }

        $stmt = $pdo->query("\n            SELECT\n                g.*,\n                COUNT(r.res_id) AS total_reservations,\n                MAX(r.{$reservationDateColumn}) AS last_booking,\n                COALESCE(SUM(CASE WHEN r.status IN ('Confirmed', 'Checked-in', 'Checked-out') THEN r.total_price ELSE 0 END), 0) AS total_spent\n            FROM GUEST g\n            LEFT JOIN RESERVATION r ON r.guest_id = g.guest_id\n            GROUP BY g.guest_id\n            ORDER BY g.created_at DESC, g.guest_id DESC\n        ");

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Guest list loaded successfully',
            'data' => $stmt->fetchAll()
        ]);
    } catch (Throwable $e) {
        logSystemError('admin_guests_get', (string)$e->getMessage(), 'guest_id=' . $guestId);
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to load guests',
            'error' => 'Failed to load guests',
            'data' => null
        ], 500);
    }
}

function guestCreate() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Invalid request payload', 'error' => 'Invalid request payload', 'data' => null], 400);
    }

    $validated = guestValidatePayload($input, false);
    if (!empty($validated['errors'])) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => implode('. ', $validated['errors']), 'error' => 'Validation failed', 'data' => ['errors' => $validated['errors']]], 422);
    }

    $data = $validated['data'];

    try {
        if ($data['email'] !== '') {
            $dupStmt = $pdo->prepare('SELECT guest_id FROM GUEST WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $dupStmt->execute([$data['email']]);
            if ($dupStmt->fetch()) {
                jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Email already exists', 'error' => 'Email already exists', 'data' => null], 409);
            }
        }

        $passwordHash = $data['password'] !== '' ? password_hash($data['password'], PASSWORD_BCRYPT) : '';

        $stmt = $pdo->prepare("\n            INSERT INTO GUEST (name, gender, age, in_id, phone_no, email, address, password)\n            VALUES (:name, :gender, :age, :in_id, :phone_no, :email, :address, :password)\n        ");
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':gender', $data['gender'] !== '' ? $data['gender'] : null, $data['gender'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':age', $data['age'] !== null ? (int)$data['age'] : null, $data['age'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':in_id', $data['in_id'] !== '' ? $data['in_id'] : null, $data['in_id'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':phone_no', $data['phone_no']);
        $stmt->bindValue(':email', $data['email'] !== '' ? $data['email'] : null, $data['email'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':address', $data['address'] !== '' ? $data['address'] : null, $data['address'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':password', $passwordHash !== '' ? $passwordHash : null, $passwordHash !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();
        $newGuestId = (int)$pdo->lastInsertId();
        logAdminAction('guest_create', 'GUEST', (string)$newGuestId, 'email=' . $data['email']);

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Guest created successfully',
            'data' => ['guest_id' => $newGuestId]
        ], 201);
    } catch (Throwable $e) {
        logSystemError('admin_guests_create', (string)$e->getMessage(), 'email=' . $data['email']);
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Failed to create guest', 'error' => 'Failed to create guest', 'data' => null], 500);
    }
}

function guestUpdate() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Invalid request payload', 'error' => 'Invalid request payload', 'data' => null], 400);
    }

    $guestId = isset($input['guest_id']) ? (int)$input['guest_id'] : 0;
    if ($guestId <= 0) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Guest ID is required', 'error' => 'Guest ID is required', 'data' => null], 422);
    }

    $validated = guestValidatePayload($input, true);
    if (!empty($validated['errors'])) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => implode('. ', $validated['errors']), 'error' => 'Validation failed', 'data' => ['errors' => $validated['errors']]], 422);
    }

    $data = $validated['data'];

    try {
        $existsStmt = $pdo->prepare('SELECT guest_id FROM GUEST WHERE guest_id = ? LIMIT 1');
        $existsStmt->execute([$guestId]);
        if (!$existsStmt->fetch()) {
            jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Guest not found', 'error' => 'Guest not found', 'data' => null], 404);
        }

        if ($data['email'] !== '') {
            $dupStmt = $pdo->prepare('SELECT guest_id FROM GUEST WHERE LOWER(email) = LOWER(?) AND guest_id != ? LIMIT 1');
            $dupStmt->execute([$data['email'], $guestId]);
            if ($dupStmt->fetch()) {
                jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Email already exists', 'error' => 'Email already exists', 'data' => null], 409);
            }
        }

        $sql = "\n            UPDATE GUEST\n            SET name = :name, gender = :gender, age = :age, in_id = :in_id,\n                phone_no = :phone_no, email = :email, address = :address";

        $params = [
            ':name' => $data['name'],
            ':gender' => $data['gender'] !== '' ? $data['gender'] : null,
            ':age' => $data['age'],
            ':in_id' => $data['in_id'] !== '' ? $data['in_id'] : null,
            ':phone_no' => $data['phone_no'],
            ':email' => $data['email'] !== '' ? $data['email'] : null,
            ':address' => $data['address'] !== '' ? $data['address'] : null,
            ':guest_id' => $guestId
        ];

        if (dbHasColumn($pdo, 'GUEST', 'updated_at')) {
            $sql .= ', updated_at = CURRENT_TIMESTAMP';
        }

        if ($data['password'] !== '') {
            if (strlen($data['password']) < 8) {
                jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Password must be at least 8 characters', 'error' => 'Validation failed', 'data' => null], 422);
            }
            $sql .= ', password = :password';
            $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $sql .= ' WHERE guest_id = :guest_id';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            if ($value === null) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        logAdminAction('guest_update', 'GUEST', (string)$guestId, 'email=' . $data['email']);

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Guest updated successfully',
            'data' => ['guest_id' => $guestId]
        ]);
    } catch (Throwable $e) {
        logSystemError('admin_guests_update', (string)$e->getMessage(), 'guest_id=' . $guestId);
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Failed to update guest', 'error' => 'Failed to update guest', 'data' => null], 500);
    }
}

function guestDelete() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Invalid request payload', 'error' => 'Invalid request payload', 'data' => null], 400);
    }

    $guestId = isset($input['guest_id']) ? (int)$input['guest_id'] : 0;
    if ($guestId <= 0) {
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Guest ID is required', 'error' => 'Guest ID is required', 'data' => null], 422);
    }

    try {
        $stmt = $pdo->prepare('SELECT guest_id FROM GUEST WHERE guest_id = ? LIMIT 1');
        $stmt->execute([$guestId]);
        if (!$stmt->fetch()) {
            jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Guest not found', 'error' => 'Guest not found', 'data' => null], 404);
        }

        $deleteStmt = $pdo->prepare('DELETE FROM GUEST WHERE guest_id = ?');
        $deleteStmt->execute([$guestId]);
        logAdminAction('guest_delete', 'GUEST', (string)$guestId, 'deleted_by_admin');

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Guest deleted successfully',
            'data' => ['guest_id' => $guestId]
        ]);
    } catch (Throwable $e) {
        logSystemError('admin_guests_delete', (string)$e->getMessage(), 'guest_id=' . $guestId);
        jsonResponse(['success' => false, 'status' => 'error', 'message' => 'Failed to delete guest', 'error' => 'Failed to delete guest', 'data' => null], 500);
    }
}
