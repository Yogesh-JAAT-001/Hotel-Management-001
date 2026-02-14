<?php
require_once '../../config.php';
require_once '../../includes/analytics-engine.php';

initApiRequest(['GET']);

if (!isAdmin()) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin authentication required',
        'error' => 'Admin authentication required',
        'data' => null
    ], 403);
}

$module = isset($_GET['module']) ? strtolower(trim((string)$_GET['module'])) : 'all';
$filters = analyticsNormalizeFilters($_GET);

try {
    switch ($module) {
        case 'food':
            $data = analyticsFoodData($pdo, $filters);
            break;

        case 'rooms':
        case 'room':
            $data = analyticsRoomData($pdo, $filters);
            break;

        case 'financial':
        case 'finance':
            $data = analyticsFinancialData($pdo, $filters);
            $data['quarterly'] = analyticsQuarterlyRows($data['monthly_trend'] ?? []);
            break;

        case 'master':
            $snapshot = analyticsSnapshot($pdo, $filters);
            $data = $snapshot['master'];
            break;

        case 'all':
        default:
            $data = analyticsSnapshot($pdo, $filters);
            break;
    }

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Analytics data loaded successfully',
        'module' => $module,
        'filters' => $filters,
        'data' => $data
    ]);
} catch (Throwable $e) {
    error_log('Analytics API error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to load analytics data',
        'error' => 'Failed to load analytics data',
        'data' => null
    ], 500);
}
