<?php
require_once __DIR__ . '/food-admin-helper.php';
require_once __DIR__ . '/media-helper.php';

function foodImageProjectRoot() {
    static $root = null;
    if ($root !== null) {
        return $root;
    }

    $root = realpath(__DIR__ . '/..');
    return $root ?: '';
}

function foodImageDefaultFallbackPath() {
    $primary = 'assets/images/food/default_food.jpg';
    if (mediaPublicAssetExists('/' . $primary)) {
        return $primary;
    }
    return 'assets/images/no-food.png';
}

function foodImageEnsureDiningDir() {
    $root = foodImageProjectRoot();
    if ($root === '') {
        return '';
    }

    $dir = $root . '/assets/images/food';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function foodImageNameNormalize($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
        $value = $ascii;
    }

    $value = strtolower($value);
    $value = str_replace(['&', '+'], ' and ', $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', (string)$value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    return trim((string)$value);
}

function foodImageTokens($value) {
    $normalized = foodImageNameNormalize($value);
    if ($normalized === '') {
        return [];
    }

    $tokens = explode(' ', $normalized);
    $stop = ['and', 'with', 'style', 'special', 'fresh', 'hot', 'cold', 'indian', 'food', 'high', 'quality'];
    $tokens = array_values(array_filter($tokens, function ($token) use ($stop) {
        return $token !== '' && !in_array($token, $stop, true);
    }));

    return array_values(array_unique($tokens));
}

function foodImageLocalFilesIndex() {
    static $index = null;
    if ($index !== null) {
        return $index;
    }

    $index = [];
    $dir = foodImageEnsureDiningDir();
    if ($dir === '' || !is_dir($dir)) {
        return $index;
    }

    $entries = @scandir($dir);
    if (!is_array($entries)) {
        return $index;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $abs = $dir . '/' . $entry;
        if (!is_file($abs)) {
            continue;
        }

        $ext = strtolower((string)pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }

        $name = (string)pathinfo($entry, PATHINFO_FILENAME);
        $index[] = [
            'filename' => $entry,
            'relative' => 'assets/images/food/' . $entry,
            'normalized' => foodImageNameNormalize($name),
            'tokens' => foodImageTokens($name)
        ];
    }

    return $index;
}

function foodImageBestLocalCandidate($foodName) {
    $aliases = [
        'paneer tikka skewers' => 'panner tikka',
        'chicken clear soup' => 'chicken soup',
        'veg spring rolls' => 'spring rolls',
        'hara bhara kebab' => 'veg kebab indian',
        'chicken tikka bites' => 'chicken tikka',
        'cream of mushroom' => 'mushroom soup',
        'dal makhani' => 'dal tadka',
        'paneer makhani' => 'paneer butter masala',
        'vegetable pulao' => 'veg pulao',
        'executive veg combo' => 'family veg meal'
    ];

    $targetNormalized = foodImageNameNormalize($foodName);
    $targetTokens = foodImageTokens($foodName);
    $candidates = foodImageLocalFilesIndex();

    $best = null;
    $bestScore = 0;

    foreach ($candidates as $candidate) {
        $score = 0;

        if ($targetNormalized !== '' && $candidate['normalized'] === $targetNormalized) {
            $score += 1000;
        }

        if (isset($aliases[$targetNormalized]) && $candidate['normalized'] === $aliases[$targetNormalized]) {
            $score += 900;
        }

        $intersection = count(array_intersect($targetTokens, $candidate['tokens']));
        $union = count(array_unique(array_merge($targetTokens, $candidate['tokens'])));
        if ($union > 0) {
            $score += (int)round(($intersection / $union) * 500);
        }

        similar_text($targetNormalized, $candidate['normalized'], $percent);
        $score += (int)round($percent);

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $candidate;
        }
    }

    if ($best && $bestScore >= 280) {
        return $best;
    }

    return null;
}

function foodImageResolveLocalPath($foodName) {
    $foodName = trim((string)$foodName);
    if ($foodName === '') {
        return '';
    }

    // First: deterministic/normalized match from current food image folder index.
    $mapped = diningImagePathByDishName($foodName);
    if ($mapped !== '' && mediaPublicAssetExists($mapped)) {
        return ltrim($mapped, '/');
    }

    // Fallback: token-based best local filename in the same folder.
    $candidate = foodImageBestLocalCandidate($foodName);
    if ($candidate && !empty($candidate['relative'])) {
        return ltrim((string)$candidate['relative'], '/');
    }

    return '';
}

function foodImageUpdateDbPath(PDO $pdo, $schema, $foodId, $relativePath) {
    $sql = "UPDATE {$schema['table']} SET {$schema['image']} = :image_path";
    if (!empty($schema['updated_at'])) {
        $sql .= ", {$schema['updated_at']} = CURRENT_TIMESTAMP";
    }
    $sql .= " WHERE {$schema['id']} = :food_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':image_path' => ltrim((string)$relativePath, '/'),
        ':food_id' => (int)$foodId
    ]);
}

function foodImageFixRow(PDO $pdo, $schema, $row) {
    $foodId = (int)($row['food_id'] ?? 0);
    $foodName = trim((string)($row['food_name'] ?? ''));
    $currentPath = ltrim(trim((string)($row['image_path'] ?? '')), '/');
    $fallbackRel = foodImageDefaultFallbackPath();

    if ($foodId <= 0 || $foodName === '') {
        return [
            'food_id' => $foodId,
            'food_name' => $foodName,
            'status' => 'failed',
            'source' => 'invalid',
            'image_path' => $fallbackRel,
            'message' => 'Invalid food row'
        ];
    }

    $mappedRel = foodImageResolveLocalPath($foodName);
    if ($mappedRel !== '') {
        if ($currentPath !== $mappedRel) {
            foodImageUpdateDbPath($pdo, $schema, $foodId, $mappedRel);
        }

        return [
            'food_id' => $foodId,
            'food_name' => $foodName,
            'status' => 'fixed',
                'source' => 'local-map',
                'image_path' => $mappedRel,
                'message' => 'Mapped from local food image folder'
            ];
        }

    if ($currentPath !== $fallbackRel) {
        foodImageUpdateDbPath($pdo, $schema, $foodId, $fallbackRel);
    }

    return [
        'food_id' => $foodId,
        'food_name' => $foodName,
        'status' => 'failed',
        'source' => 'fallback',
        'image_path' => $fallbackRel,
        'message' => 'Fallback applied'
    ];
}

function foodImageFixMissing(PDO $pdo, $options = []) {
    $schema = foodAdminSchema($pdo);
    if (empty($schema['id']) || empty($schema['name']) || empty($schema['image'])) {
        throw new RuntimeException('FOOD_DINING schema mismatch for image fix');
    }

    foodImageEnsureDiningDir();

    $foodId = isset($options['food_id']) ? (int)$options['food_id'] : 0;
    $limit = 0;
    if (isset($options['limit']) && (int)$options['limit'] > 0) {
        $limit = (int)$options['limit'];
    }

    $sql = "
        SELECT
            {$schema['id']} AS food_id,
            {$schema['name']} AS food_name,
            {$schema['image']} AS image_path
        FROM {$schema['table']}
    ";
    $params = [];

    if ($foodId > 0) {
        $sql .= " WHERE {$schema['id']} = :food_id";
        $params[':food_id'] = $foodId;
    }

    $sql .= " ORDER BY {$schema['id']} ASC";

    if ($limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll();
    $results = [];
    $fixed = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $result = foodImageFixRow($pdo, $schema, $row);
        $results[] = $result;

        if (($result['status'] ?? '') === 'fixed') {
            $fixed++;
        } else {
            $failed++;
        }
    }

    return [
        'total' => count($rows),
        'fixed' => $fixed,
        'failed' => $failed,
        'items' => $results
    ];
}
