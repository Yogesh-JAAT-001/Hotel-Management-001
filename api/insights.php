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
    $payload = analyticsDashboardPayload($pdo, $_GET);

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Business insights loaded successfully',
        'filters' => $payload['filters'],
        'data' => [
            'insights' => $payload['insights'],
            'economics_advanced' => $payload['economics_advanced'],
            'master' => $payload['master'],
            'kpi' => $payload['kpi']
        ]
    ]);
} catch (Throwable $e) {
    error_log('Insights API error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to load business insights',
        'error' => 'Failed to load business insights',
        'data' => null
    ], 500);
}
