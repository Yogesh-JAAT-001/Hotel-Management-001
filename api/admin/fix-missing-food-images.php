<?php
require_once '../../config.php';
require_once '../../includes/food-image-fixer.php';

initApiRequest(['GET', 'POST'], false);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }
}

if (!isAdmin()) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin access required',
        'error' => 'Admin access required',
        'data' => null
    ], 403);
}

$foodId = isset($_GET['food_id']) ? (int)$_GET['food_id'] : (isset($input['food_id']) ? (int)$input['food_id'] : 0);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (isset($input['limit']) ? (int)$input['limit'] : 0);
$format = strtolower(trim((string)($_GET['format'] ?? ($input['format'] ?? 'json'))));

try {
    $report = foodImageFixMissing($pdo, [
        'food_id' => $foodId > 0 ? $foodId : null,
        'limit' => $limit > 0 ? $limit : null
    ]);

    logAdminAction(
        'fix_missing_food_images',
        'FOOD_DINING',
        $foodId > 0 ? (string)$foodId : null,
        'fixed=' . (int)$report['fixed'] . '; failed=' . (int)$report['failed'] . '; total=' . (int)$report['total']
    );

    if ($format === 'text') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Food Image Fix Report\n";
        echo "Total: " . (int)$report['total'] . "\n";
        echo "Fixed: " . (int)$report['fixed'] . "\n";
        echo "Failed: " . (int)$report['failed'] . "\n\n";

        foreach ($report['items'] as $item) {
            $status = (($item['status'] ?? '') === 'fixed') ? 'Fixed' : 'Failed';
            $name = (string)($item['food_name'] ?? 'Unknown');
            $msg = (string)($item['message'] ?? '');
            echo $status . ': ' . $name . ($msg !== '' ? (' -> ' . $msg) : '') . "\n";
        }
        exit;
    }

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Local dining image mapping completed',
        'data' => $report
    ]);
} catch (Throwable $e) {
    logSystemError('admin_fix_missing_food_images', (string)$e->getMessage(), 'food_id=' . $foodId);
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to fix missing food images',
        'error' => 'Failed to fix missing food images',
        'data' => null
    ], 500);
}
