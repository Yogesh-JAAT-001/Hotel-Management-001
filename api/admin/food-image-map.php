<?php
require_once '../../config.php';
require_once '../../includes/food-admin-helper.php';

initApiRequest(['POST']);

if (!isAdmin()) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin access required',
        'error' => 'Admin access required',
        'data' => null
    ], 403);
}

function ensureDiningImagesFolder($projectRoot) {
    $targetDir = $projectRoot . '/assets/images/food';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
}

try {
    $projectRoot = realpath(__DIR__ . '/../../');
    if ($projectRoot === false) {
        throw new RuntimeException('Unable to resolve project root');
    }

    ensureDiningImagesFolder($projectRoot);

    $schema = foodAdminSchema($pdo);
    if (empty($schema['image']) || empty($schema['name']) || empty($schema['id'])) {
        throw new RuntimeException('FOOD_DINING schema does not expose required columns');
    }

    $defaultPath = 'assets/images/food/default_food.jpg';
    if (!mediaPublicAssetExists('/' . ltrim($defaultPath, '/'))) {
        $defaultPath = 'assets/images/no-food.png';
    }

    $rows = $pdo->query("\n        SELECT\n            {$schema['id']} AS food_id,\n            {$schema['name']} AS food_name,\n            {$schema['image']} AS image_path\n        FROM {$schema['table']}\n        ORDER BY {$schema['id']} ASC\n    ")->fetchAll();

    $updateSql = "UPDATE {$schema['table']} SET {$schema['image']} = :image_path";
    if (!empty($schema['updated_at'])) {
        $updateSql .= ", {$schema['updated_at']} = CURRENT_TIMESTAMP";
    }
    $updateSql .= " WHERE {$schema['id']} = :food_id";
    $updateStmt = $pdo->prepare($updateSql);

    $updated = 0;
    $mappedExact = 0;
    $mappedNormalized = 0;
    $defaulted = 0;
    $preview = [];

    foreach ($rows as $row) {
        $foodId = (int)$row['food_id'];
        $foodName = trim((string)($row['food_name'] ?? ''));
        $current = trim((string)($row['image_path'] ?? ''));

        $target = '';
        if ($foodName !== '') {
            // Required deterministic format first.
            $expectedPublic = '/assets/images/food/' . $foodName . '.jpg';
            if (mediaPublicAssetExists($expectedPublic)) {
                $target = ltrim($expectedPublic, '/');
                $mappedExact++;
            } else {
                $normalizedMatch = diningImagePathByDishName($foodName);
                if ($normalizedMatch !== '') {
                    $target = ltrim($normalizedMatch, '/');
                    $mappedNormalized++;
                }
            }
        }

        if ($target === '') {
            $target = $defaultPath;
            $defaulted++;
        }

        if ($target !== $current) {
            $updateStmt->execute([
                ':image_path' => $target,
                ':food_id' => $foodId
            ]);
            $updated++;
        }

        if (count($preview) < 20) {
            $preview[] = [
                'food_id' => $foodId,
                'food_name' => $foodName,
                'image_path' => $target
            ];
        }
    }

    logAdminAction(
        'food_image_map',
        'FOOD_DINING',
        null,
        'updated=' . $updated . '; mapped=' . ($mappedExact + $mappedNormalized) . '; defaulted=' . $defaulted
    );

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Dining image mapping completed',
        'data' => [
            'total_food_items' => count($rows),
            'updated' => $updated,
            'mapped_exact' => $mappedExact,
            'mapped_normalized' => $mappedNormalized,
            'mapped' => ($mappedExact + $mappedNormalized),
            'defaulted' => $defaulted,
            'default_path' => $defaultPath,
            'preview' => $preview
        ]
    ]);
} catch (Throwable $e) {
    logSystemError('admin_food_image_map', (string)$e->getMessage());

    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to map dining images',
        'error' => 'Failed to map dining images',
        'data' => null
    ], 500);
}
