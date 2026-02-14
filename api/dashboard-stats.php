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

$activity = isset($_GET['activity']) ? strtolower(trim((string)$_GET['activity'])) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(50, (int)$_GET['per_page'])) : 10;

try {
    if ($activity !== '') {
        switch ($activity) {
            case 'reservations':
                $data = analyticsRecentReservations($pdo, $page, $perPage, $_GET);
                break;

            case 'food':
            case 'food_orders':
                $data = analyticsRecentFoodOrders($pdo, $page, $perPage, $_GET);
                break;

            case 'payments':
                $data = analyticsRecentPayments($pdo, $page, $perPage, $_GET);
                break;

            case 'staff':
            case 'staff_activities':
                $data = analyticsRecentStaffActivities($pdo, $page, $perPage, $_GET);
                break;

            default:
                jsonResponse([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Unsupported activity type',
                    'error' => 'Unsupported activity type',
                    'data' => null
                ], 400);
        }

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Activity data loaded successfully',
            'activity' => $activity,
            'data' => $data
        ]);
    }

    $payload = analyticsDashboardPayload($pdo, $_GET);

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Dashboard statistics loaded successfully',
        'filters' => $payload['filters'],
        'data' => [
            'master' => $payload['master'],
            'kpi' => $payload['kpi'],
            'recent_activity' => $payload['recent_activity']
        ]
    ]);
} catch (Throwable $e) {
    error_log('Dashboard stats API error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to load dashboard statistics',
        'error' => 'Failed to load dashboard statistics',
        'data' => null
    ], 500);
}
