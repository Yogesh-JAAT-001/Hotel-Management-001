<?php
require_once __DIR__ . '/media-helper.php';

function foodAdminSchema(PDO $pdo) {
    static $schema = null;
    if ($schema !== null) {
        return $schema;
    }

    $table = 'FOOD_DINING';
    $idCol = dbFirstExistingColumn($pdo, $table, ['food_id', 'id']) ?? 'food_id';
    $nameCol = dbFirstExistingColumn($pdo, $table, ['food_name', 'title', 'name']) ?? 'title';
    $categoryCol = dbFirstExistingColumn($pdo, $table, ['category', 'menu_category']);
    $typeCol = dbFirstExistingColumn($pdo, $table, ['type', 'food_type']) ?? 'food_type';
    $priceCol = dbFirstExistingColumn($pdo, $table, ['price']) ?? 'price';
    $descCol = dbFirstExistingColumn($pdo, $table, ['description', 'details']) ?? 'description';
    $imageCol = dbFirstExistingColumn($pdo, $table, ['image', 'image_path']);
    $statusCol = dbFirstExistingColumn($pdo, $table, ['status', 'is_available']);
    $createdAtCol = dbFirstExistingColumn($pdo, $table, ['created_at']);
    $updatedAtCol = dbFirstExistingColumn($pdo, $table, ['updated_at']);

    $statusNumeric = false;
    if ($statusCol !== null) {
        $stmt = $pdo->prepare("
            SELECT DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$table, $statusCol]);
        $dataType = strtolower((string)($stmt->fetchColumn() ?: ''));
        $statusNumeric = in_array($dataType, ['tinyint', 'smallint', 'int', 'bigint', 'bit', 'boolean'], true);
    }

    $schema = [
        'table' => $table,
        'id' => $idCol,
        'name' => $nameCol,
        'category' => $categoryCol,
        'type' => $typeCol,
        'price' => $priceCol,
        'description' => $descCol,
        'image' => $imageCol,
        'status' => $statusCol,
        'created_at' => $createdAtCol,
        'updated_at' => $updatedAtCol,
        'status_numeric' => $statusNumeric
    ];

    return $schema;
}

function foodAdminUploadDir() {
    $dir = __DIR__ . '/../uploads/food';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function foodAdminPublicPathForUpload($fileName) {
    return '/uploads/food/' . ltrim($fileName, '/');
}

function foodAdminDeleteImageFile($storedPath) {
    $storedPath = trim((string)$storedPath);
    if ($storedPath === '' || mediaIsAbsoluteUrl($storedPath)) {
        return;
    }

    $normalized = '/' . ltrim($storedPath, '/');
    if (strpos($normalized, '/uploads/food/') !== 0) {
        return;
    }

    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        return;
    }

    $file = $root . $normalized;
    if (is_file($file)) {
        @unlink($file);
    }
}

function foodAdminHandleImageUpload($fileInputName, $oldImagePath = '') {
    if (!isset($_FILES[$fileInputName])) {
        return ['ok' => true, 'path' => $oldImagePath, 'message' => ''];
    }

    $file = $_FILES[$fileInputName];
    if (!is_array($file)) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Invalid upload payload.'];
    }

    if ((int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => $oldImagePath, 'message' => ''];
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Image upload failed.'];
    }

    $maxBytes = 2 * 1024 * 1024;
    if ((int)$file['size'] > $maxBytes) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Image size must be 2MB or less.'];
    }

    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($extension, $allowed, true)) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Only JPG, JPEG, PNG, and WEBP images are allowed.'];
    }

    $tmpPath = (string)$file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Invalid upload source.'];
    }

    $mime = (string)(mime_content_type($tmpPath) ?: '');
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMime, true)) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Invalid image file type.'];
    }

    $uploadDir = foodAdminUploadDir();
    try {
        $random = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $random = substr(md5(uniqid('', true)), 0, 8);
    }
    $newName = 'food_' . time() . '_' . $random . '.' . $extension;
    $destination = $uploadDir . '/' . $newName;

    if (!move_uploaded_file($tmpPath, $destination)) {
        return ['ok' => false, 'path' => $oldImagePath, 'message' => 'Could not save uploaded image.'];
    }

    @chmod($destination, 0644);
    foodAdminDeleteImageFile($oldImagePath);

    return [
        'ok' => true,
        'path' => foodAdminPublicPathForUpload($newName),
        'message' => ''
    ];
}

function foodAdminNormalizeType($value) {
    $type = strtoupper(trim((string)$value));
    return $type === 'NON-VEG' ? 'NON-VEG' : 'VEG';
}

function foodAdminNormalizeStatus($value, $statusNumeric) {
    $raw = strtolower(trim((string)$value));
    if ($statusNumeric) {
        return in_array($raw, ['1', 'true', 'active', 'available', 'yes'], true) ? 1 : 0;
    }

    return in_array($raw, ['1', 'true', 'active', 'available', 'yes'], true) ? 'Available' : 'Unavailable';
}

function foodAdminIsAvailable($row, $schema) {
    if (empty($schema['status'])) {
        return true;
    }

    $value = $row['status_value'] ?? null;
    if ($value === null) {
        return true;
    }

    if ($schema['status_numeric']) {
        return ((int)$value) === 1;
    }

    $text = strtolower(trim((string)$value));
    return in_array($text, ['available', 'active', 'enabled', 'yes', '1'], true);
}

function foodAdminImageUrl($imagePath, $category = 'Main Course', $dishName = '') {
    $imagePath = trim((string)$imagePath);
    $placeholder = '/assets/images/food/default_food.jpg';
    if (!mediaPublicAssetExists($placeholder)) {
        $placeholder = '/assets/images/no-food.png';
    }

    $resolved = resolveDiningImageUrl($imagePath, $category, $dishName);
    if ($resolved !== '') {
        return $resolved;
    }

    return appUrl($placeholder);
}

function foodAdminPrepareRow($row, $schema) {
    $row['food_name'] = (string)($row['food_name'] ?? '');
    $row['category'] = (string)($row['category'] ?? 'Main Course');
    $row['type'] = foodAdminNormalizeType($row['type'] ?? 'VEG');
    $row['price'] = round((float)($row['price'] ?? 0), 2);
    $row['description'] = (string)($row['description'] ?? '');
    $row['is_available'] = foodAdminIsAvailable($row, $schema);
    $row['status_label'] = $row['is_available'] ? 'Available' : 'Unavailable';
    $row['image_url'] = foodAdminImageUrl($row['image_path'] ?? '', $row['category'], $row['food_name'] ?? '');
    return $row;
}

function foodAdminFindById(PDO $pdo, $foodId) {
    $schema = foodAdminSchema($pdo);
    $id = (int)$foodId;
    if ($id <= 0) {
        return null;
    }

    $sql = "
        SELECT
            f.{$schema['id']} AS food_id,
            f.{$schema['name']} AS food_name,
            " . ($schema['category'] ? "f.{$schema['category']}" : "'Main Course'") . " AS category,
            " . ($schema['type'] ? "f.{$schema['type']}" : "'VEG'") . " AS type,
            f.{$schema['price']} AS price,
            " . ($schema['description'] ? "f.{$schema['description']}" : "''") . " AS description,
            " . ($schema['image'] ? "f.{$schema['image']}" : "''") . " AS image_path,
            " . ($schema['status'] ? "f.{$schema['status']}" : "1") . " AS status_value,
            " . ($schema['created_at'] ? "f.{$schema['created_at']}" : "NULL") . " AS created_at
        FROM {$schema['table']} f
        WHERE f.{$schema['id']} = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    return foodAdminPrepareRow($row, $schema);
}

function foodAdminList(PDO $pdo, $filters = [], $page = 1, $perPage = 12) {
    $schema = foodAdminSchema($pdo);
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage));
    $offset = ($page - 1) * $perPage;

    $search = trim((string)($filters['q'] ?? ''));
    $category = trim((string)($filters['category'] ?? ''));
    $type = strtoupper(trim((string)($filters['type'] ?? '')));
    $status = strtolower(trim((string)($filters['status'] ?? '')));

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = "(f.{$schema['name']} LIKE :search OR " . ($schema['description'] ? "f.{$schema['description']}" : "''") . " LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($category !== '' && strtolower($category) !== 'all' && $schema['category']) {
        $where[] = "f.{$schema['category']} = :category";
        $params[':category'] = $category;
    }

    if (in_array($type, ['VEG', 'NON-VEG'], true) && $schema['type']) {
        $where[] = "f.{$schema['type']} = :type";
        $params[':type'] = $type;
    }

    if (in_array($status, ['available', 'unavailable'], true) && $schema['status']) {
        $where[] = "f.{$schema['status']} = :status_value";
        $params[':status_value'] = $status === 'available'
            ? foodAdminNormalizeStatus('available', $schema['status_numeric'])
            : foodAdminNormalizeStatus('unavailable', $schema['status_numeric']);
    }

    $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
    $orderSql = ($schema['created_at'] ? "f.{$schema['created_at']} DESC, " : '') . "f.{$schema['id']} DESC";

    $countSql = "SELECT COUNT(*) FROM {$schema['table']} f {$whereSql}";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalRows = (int)$stmt->fetchColumn();

    $sql = "
        SELECT
            f.{$schema['id']} AS food_id,
            f.{$schema['name']} AS food_name,
            " . ($schema['category'] ? "f.{$schema['category']}" : "'Main Course'") . " AS category,
            " . ($schema['type'] ? "f.{$schema['type']}" : "'VEG'") . " AS type,
            f.{$schema['price']} AS price,
            " . ($schema['description'] ? "f.{$schema['description']}" : "''") . " AS description,
            " . ($schema['image'] ? "f.{$schema['image']}" : "''") . " AS image_path,
            " . ($schema['status'] ? "f.{$schema['status']}" : "1") . " AS status_value,
            " . ($schema['created_at'] ? "f.{$schema['created_at']}" : "NULL") . " AS created_at
        FROM {$schema['table']} f
        {$whereSql}
        ORDER BY {$orderSql}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $prepared = [];
    foreach ($rows as $row) {
        $prepared[] = foodAdminPrepareRow($row, $schema);
    }

    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);

    return [
        'rows' => $prepared,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ];
}

function foodAdminCategoryOptions(PDO $pdo) {
    $schema = foodAdminSchema($pdo);
    $default = ['Starters', 'Soups', 'Main Course', 'Breads', 'Rice', 'Desserts', 'Beverages', 'Combos'];
    if (!$schema['category']) {
        return $default;
    }

    $sql = "SELECT DISTINCT {$schema['category']} AS category_name FROM {$schema['table']} ORDER BY {$schema['category']} ASC";
    $stmt = $pdo->query($sql);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $value = trim((string)($row['category_name'] ?? ''));
        if ($value !== '') {
            $found[] = $value;
        }
    }

    $merged = array_values(array_unique(array_merge($default, $found)));
    return $merged;
}

function foodAdminStats(PDO $pdo) {
    $schema = foodAdminSchema($pdo);
    $statusExpr = $schema['status']
        ? "f.{$schema['status']}"
        : '1';
    $availableValue = foodAdminNormalizeStatus('available', $schema['status_numeric']);
    $typeExpr = $schema['type']
        ? "UPPER(f.{$schema['type']})"
        : "'VEG'";

    $sql = "
        SELECT
            COUNT(*) AS total_items,
            SUM(CASE WHEN {$typeExpr} = 'VEG' THEN 1 ELSE 0 END) AS veg_items,
            SUM(CASE WHEN {$typeExpr} = 'NON-VEG' THEN 1 ELSE 0 END) AS non_veg_items,
            SUM(CASE WHEN {$statusExpr} = :available_value THEN 1 ELSE 0 END) AS available_items
        FROM {$schema['table']} f
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':available_value' => $availableValue]);
    $row = $stmt->fetch() ?: [];

    return [
        'total' => (int)($row['total_items'] ?? 0),
        'veg' => (int)($row['veg_items'] ?? 0),
        'non_veg' => (int)($row['non_veg_items'] ?? 0),
        'available' => (int)($row['available_items'] ?? 0)
    ];
}

function foodAdminValidationErrors($input) {
    $errors = [];

    $name = trim((string)($input['food_name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Food name is required.';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Food name must be under 255 characters.';
    }

    $category = trim((string)($input['category'] ?? ''));
    if ($category === '') {
        $errors[] = 'Category is required.';
    }

    $price = $input['price'] ?? '';
    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        $errors[] = 'Price must be a valid non-negative number.';
    }

    return $errors;
}
