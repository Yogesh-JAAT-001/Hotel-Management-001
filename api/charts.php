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
    $charts = [
        'revenue_booking' => $payload['chart']['revenue_booking'],
        // Backward compatible alias kept for existing clients.
        'revenue_booking_trend' => $payload['chart']['revenue_booking'],
        'room_occupancy' => $payload['chart']['occupancy'],
        'demand_trend' => $payload['chart']['demand'],
        'cost_distribution' => $payload['chart']['cost_distribution'],
        'food' => $payload['snapshot']['food']['chart'] ?? [],
        'rooms' => $payload['snapshot']['rooms']['chart'] ?? [],
        'financial' => $payload['snapshot']['financial']['chart'] ?? []
    ];

    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Charts analytics loaded successfully',
        'filters' => $payload['filters'],
        'data' => $charts
    ]);
} catch (Throwable $e) {
    error_log('Charts API error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to load chart analytics',
        'error' => 'Failed to load chart analytics',
        'data' => null
    ], 500);
}
