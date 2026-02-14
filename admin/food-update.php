<?php
require_once '../config.php';
require_once '../includes/food-admin-helper.php';

if (!isAdmin()) {
    redirect('login.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect('food.php');
}

requireCsrfToken();

$foodId = (int)($_POST['food_id'] ?? 0);
if ($foodId <= 0) {
    redirect('food.php');
}

$fail = function ($message) use ($foodId) {
    redirect('food-edit.php?id=' . $foodId . '&error=' . urlencode($message));
};

try {
    $schema = foodAdminSchema($pdo);
    $existing = foodAdminFindById($pdo, $foodId);
    if (!$existing) {
        redirect('food.php');
    }

    $payload = [
        'food_name' => trim((string)($_POST['food_name'] ?? '')),
        'category' => trim((string)($_POST['category'] ?? 'Main Course')),
        'type' => foodAdminNormalizeType($_POST['type'] ?? 'VEG'),
        'price' => trim((string)($_POST['price'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'status' => strtolower(trim((string)($_POST['status'] ?? 'available')))
    ];

    $errors = foodAdminValidationErrors($payload);

    $upload = foodAdminHandleImageUpload('image', $existing['image_path'] ?? '');
    if (!$upload['ok']) {
        $errors[] = $upload['message'];
    }

    if (!empty($errors)) {
        $fail($errors[0]);
    }

    $set = [];
    $params = [];

    $set[] = $schema['name'] . ' = :food_name';
    $params[':food_name'] = $payload['food_name'];

    if ($schema['category']) {
        $set[] = $schema['category'] . ' = :category';
        $params[':category'] = $payload['category'];
    }

    if ($schema['type']) {
        $set[] = $schema['type'] . ' = :type';
        $params[':type'] = $payload['type'];
    }

    $set[] = $schema['price'] . ' = :price';
    $params[':price'] = (float)$payload['price'];

    if ($schema['description']) {
        $set[] = $schema['description'] . ' = :description';
        $params[':description'] = $payload['description'];
    }

    if ($schema['image']) {
        $set[] = $schema['image'] . ' = :image_path';
        $params[':image_path'] = $upload['path'] ?? ($existing['image_path'] ?? '');
    }

    if ($schema['status']) {
        $set[] = $schema['status'] . ' = :status';
        $params[':status'] = foodAdminNormalizeStatus($payload['status'], $schema['status_numeric']);
    }

    if ($schema['updated_at']) {
        $set[] = $schema['updated_at'] . ' = CURRENT_TIMESTAMP';
    }

    $sql = sprintf(
        'UPDATE %s SET %s WHERE %s = :food_id LIMIT 1',
        $schema['table'],
        implode(', ', $set),
        $schema['id']
    );

    $params[':food_id'] = $foodId;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    logAdminAction('food_update', 'FOOD_DINING', (string)$foodId, 'name=' . $payload['food_name']);

    redirect('food.php?success=updated');
} catch (Throwable $e) {
    logSystemError('admin_food_update', (string)$e->getMessage(), 'food_id=' . $foodId);
    $fail('Unable to update food item right now.');
}
