<?php
require_once '../config.php';
require_once '../includes/analytics-engine.php';

initApiRequest(['GET'], false);

if (!isAdmin()) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin authentication required',
        'error' => 'Admin authentication required',
        'data' => null
    ], 403);
}

try {
    $snapshot = analyticsSnapshot($pdo, $_GET);

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Top performer analytics loaded successfully',
        'filters' => $snapshot['filters'] ?? analyticsNormalizeFilters($_GET),
        'data' => [
            'master' => $snapshot['master'] ?? [],
            'top_food_items' => array_slice($snapshot['food']['top_dishes'] ?? [], 0, 10),
            'top_room_types' => array_slice($snapshot['rooms']['room_type_performance'] ?? [], 0, 10),
            'season_performance' => array_slice($snapshot['financial']['profit_drivers']['seasons'] ?? [], 0, 10),
            'profit_drivers' => $snapshot['financial']['profit_drivers'] ?? []
        ]
    ]);
} catch (Throwable $e) {
    error_log('Top performers API error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to load top performer analytics',
        'error' => 'Failed to load top performer analytics',
        'data' => null
    ], 500);
}
