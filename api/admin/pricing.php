<?php
require_once '../../config.php';
require_once '../../includes/pricing-engine.php';

initApiRequest(['GET', 'POST', 'PUT', 'DELETE']);

if (!isAdmin()) {
    jsonResponse(['error' => 'Admin access required'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        getPricingData();
        break;
    case 'POST':
        createSeason();
        break;
    case 'PUT':
        updatePricingResource();
        break;
    case 'DELETE':
        deleteSeason();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getPricingData() {
    global $pdo;

    try {
        $settings = getDynamicPricingSettings($pdo);
        $seasons = getDynamicPricingSeasons($pdo);
        $diagnostics = getPricingDiagnostics($pdo);

        jsonResponse([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'seasons' => $seasons,
                'diagnostics' => $diagnostics
            ]
        ]);
    } catch (Exception $e) {
        error_log('Get pricing data error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch pricing data'], 500);
    }
}

function createSeason() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request payload'], 400);
    }

    $name = sanitize($input['name'] ?? '');
    $startMmdd = sanitize($input['start_mmdd'] ?? '');
    $endMmdd = sanitize($input['end_mmdd'] ?? '');
    $multiplier = isset($input['multiplier']) ? (float)$input['multiplier'] : 1.0;
    $description = sanitize($input['description'] ?? '');
    $priority = isset($input['priority']) ? (int)$input['priority'] : 1;
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    if ($name === '' || !isValidMmdd($startMmdd) || !isValidMmdd($endMmdd)) {
        jsonResponse(['error' => 'Valid season name, start_mmdd, and end_mmdd are required'], 400);
    }
    if (strlen($name) > 100) {
        jsonResponse(['error' => 'Season name cannot exceed 100 characters'], 400);
    }
    if (strlen($description) > 255) {
        jsonResponse(['error' => 'Season description cannot exceed 255 characters'], 400);
    }
    if ($multiplier < 0.5 || $multiplier > 3.0) {
        jsonResponse(['error' => 'Multiplier must be between 0.50 and 3.00'], 400);
    }
    if ($priority < 1 || $priority > 10) {
        jsonResponse(['error' => 'Priority must be between 1 and 10'], 400);
    }

    try {
        ensurePricingSchema($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO pricing_seasons (name, start_mmdd, end_mmdd, multiplier, description, priority, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $startMmdd, $endMmdd, $multiplier, $description, $priority, $isActive]);

        jsonResponse([
            'success' => true,
            'message' => 'Season added successfully',
            'data' => ['season_id' => (int)$pdo->lastInsertId()]
        ], 201);
    } catch (Exception $e) {
        error_log('Create pricing season error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to create season'], 500);
    }
}

function updatePricingResource() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request payload'], 400);
    }

    $type = sanitize($input['type'] ?? '');

    if ($type === 'settings') {
        updateSettings($input);
        return;
    }

    if ($type === 'season') {
        updateSeason($input);
        return;
    }

    jsonResponse(['error' => 'Invalid update type'], 400);
}

function updateSettings(array $input) {
    global $pdo;

    try {
        ensurePricingSchema($pdo);
        $current = getDynamicPricingSettings($pdo);

        $fields = [
            'is_enabled' => (int)(bool)($input['is_enabled'] ?? $current['is_enabled']),
            'min_multiplier' => (float)($input['min_multiplier'] ?? $current['min_multiplier']),
            'max_multiplier' => (float)($input['max_multiplier'] ?? $current['max_multiplier']),
            'occupancy_low_threshold' => (float)($input['occupancy_low_threshold'] ?? $current['occupancy_low_threshold']),
            'occupancy_high_threshold' => (float)($input['occupancy_high_threshold'] ?? $current['occupancy_high_threshold']),
            'occupancy_low_adjustment' => (float)($input['occupancy_low_adjustment'] ?? $current['occupancy_low_adjustment']),
            'occupancy_high_adjustment' => (float)($input['occupancy_high_adjustment'] ?? $current['occupancy_high_adjustment']),
            'demand_window_days' => (int)($input['demand_window_days'] ?? $current['demand_window_days']),
            'demand_low_threshold' => (int)($input['demand_low_threshold'] ?? $current['demand_low_threshold']),
            'demand_high_threshold' => (int)($input['demand_high_threshold'] ?? $current['demand_high_threshold']),
            'demand_low_adjustment' => (float)($input['demand_low_adjustment'] ?? $current['demand_low_adjustment']),
            'demand_high_adjustment' => (float)($input['demand_high_adjustment'] ?? $current['demand_high_adjustment']),
            'lead_time_last_minute_days' => (int)($input['lead_time_last_minute_days'] ?? $current['lead_time_last_minute_days']),
            'lead_time_early_bird_days' => (int)($input['lead_time_early_bird_days'] ?? $current['lead_time_early_bird_days']),
            'lead_time_last_minute_adjustment' => (float)($input['lead_time_last_minute_adjustment'] ?? $current['lead_time_last_minute_adjustment']),
            'lead_time_early_bird_adjustment' => (float)($input['lead_time_early_bird_adjustment'] ?? $current['lead_time_early_bird_adjustment']),
            'manual_global_adjustment' => (float)($input['manual_global_adjustment'] ?? $current['manual_global_adjustment'])
        ];

        validateSettingsPayload($fields);

        $stmt = $pdo->prepare("
            UPDATE pricing_settings
            SET is_enabled = ?, min_multiplier = ?, max_multiplier = ?,
                occupancy_low_threshold = ?, occupancy_high_threshold = ?,
                occupancy_low_adjustment = ?, occupancy_high_adjustment = ?,
                demand_window_days = ?, demand_low_threshold = ?, demand_high_threshold = ?,
                demand_low_adjustment = ?, demand_high_adjustment = ?,
                lead_time_last_minute_days = ?, lead_time_early_bird_days = ?,
                lead_time_last_minute_adjustment = ?, lead_time_early_bird_adjustment = ?,
                manual_global_adjustment = ?
            WHERE id = 1
        ");
        $stmt->execute([
            $fields['is_enabled'],
            $fields['min_multiplier'],
            $fields['max_multiplier'],
            $fields['occupancy_low_threshold'],
            $fields['occupancy_high_threshold'],
            $fields['occupancy_low_adjustment'],
            $fields['occupancy_high_adjustment'],
            $fields['demand_window_days'],
            $fields['demand_low_threshold'],
            $fields['demand_high_threshold'],
            $fields['demand_low_adjustment'],
            $fields['demand_high_adjustment'],
            $fields['lead_time_last_minute_days'],
            $fields['lead_time_early_bird_days'],
            $fields['lead_time_last_minute_adjustment'],
            $fields['lead_time_early_bird_adjustment'],
            $fields['manual_global_adjustment']
        ]);

        jsonResponse([
            'success' => true,
            'message' => 'Pricing settings updated successfully'
        ]);
    } catch (Exception $e) {
        error_log('Update pricing settings error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to update pricing settings'], 500);
    }
}

function updateSeason(array $input) {
    global $pdo;

    $seasonId = isset($input['season_id']) ? (int)$input['season_id'] : 0;
    if ($seasonId <= 0) {
        jsonResponse(['error' => 'season_id is required'], 400);
    }

    $name = sanitize($input['name'] ?? '');
    $startMmdd = sanitize($input['start_mmdd'] ?? '');
    $endMmdd = sanitize($input['end_mmdd'] ?? '');
    $multiplier = isset($input['multiplier']) ? (float)$input['multiplier'] : 1.0;
    $description = sanitize($input['description'] ?? '');
    $priority = isset($input['priority']) ? (int)$input['priority'] : 1;
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;

    if ($name === '' || !isValidMmdd($startMmdd) || !isValidMmdd($endMmdd)) {
        jsonResponse(['error' => 'Valid season name, start_mmdd, and end_mmdd are required'], 400);
    }
    if (strlen($name) > 100) {
        jsonResponse(['error' => 'Season name cannot exceed 100 characters'], 400);
    }
    if (strlen($description) > 255) {
        jsonResponse(['error' => 'Season description cannot exceed 255 characters'], 400);
    }
    if ($multiplier < 0.5 || $multiplier > 3.0) {
        jsonResponse(['error' => 'Multiplier must be between 0.50 and 3.00'], 400);
    }
    if ($priority < 1 || $priority > 10) {
        jsonResponse(['error' => 'Priority must be between 1 and 10'], 400);
    }

    try {
        ensurePricingSchema($pdo);
        $stmt = $pdo->prepare("SELECT season_id FROM pricing_seasons WHERE season_id = ?");
        $stmt->execute([$seasonId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonResponse(['error' => 'Season not found'], 404);
        }

        $stmt = $pdo->prepare("
            UPDATE pricing_seasons
            SET name = ?, start_mmdd = ?, end_mmdd = ?, multiplier = ?, description = ?, priority = ?, is_active = ?
            WHERE season_id = ?
        ");
        $stmt->execute([$name, $startMmdd, $endMmdd, $multiplier, $description, $priority, $isActive, $seasonId]);

        jsonResponse([
            'success' => true,
            'message' => 'Season updated successfully'
        ]);
    } catch (Exception $e) {
        error_log('Update pricing season error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to update season'], 500);
    }
}

function deleteSeason() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request payload'], 400);
    }

    $seasonId = isset($input['season_id']) ? (int)$input['season_id'] : 0;
    if ($seasonId <= 0) {
        jsonResponse(['error' => 'season_id is required'], 400);
    }

    try {
        ensurePricingSchema($pdo);
        $stmt = $pdo->prepare("SELECT season_id FROM pricing_seasons WHERE season_id = ?");
        $stmt->execute([$seasonId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            jsonResponse(['error' => 'Season not found'], 404);
        }

        $stmt = $pdo->prepare("DELETE FROM pricing_seasons WHERE season_id = ?");
        $stmt->execute([$seasonId]);

        jsonResponse([
            'success' => true,
            'message' => 'Season deleted successfully'
        ]);
    } catch (Exception $e) {
        error_log('Delete pricing season error: ' . $e->getMessage());
        jsonResponse(['error' => 'Failed to delete season'], 500);
    }
}

function isValidMmdd($value) {
    if (!preg_match('/^\d{2}-\d{2}$/', $value)) {
        return false;
    }

    [$month, $day] = explode('-', $value);
    return checkdate((int)$month, (int)$day, 2024);
}

function validateSettingsPayload(array $fields) {
    if ($fields['min_multiplier'] < 0.5 || $fields['min_multiplier'] > 3.0) {
        jsonResponse(['error' => 'min_multiplier must be between 0.50 and 3.00'], 400);
    }
    if ($fields['max_multiplier'] < $fields['min_multiplier'] || $fields['max_multiplier'] > 3.0) {
        jsonResponse(['error' => 'max_multiplier must be between min_multiplier and 3.00'], 400);
    }
    if ($fields['occupancy_low_threshold'] < 0 || $fields['occupancy_low_threshold'] > 1) {
        jsonResponse(['error' => 'occupancy_low_threshold must be between 0 and 1'], 400);
    }
    if ($fields['occupancy_high_threshold'] < 0 || $fields['occupancy_high_threshold'] > 1) {
        jsonResponse(['error' => 'occupancy_high_threshold must be between 0 and 1'], 400);
    }
    if ($fields['occupancy_high_threshold'] <= $fields['occupancy_low_threshold']) {
        jsonResponse(['error' => 'occupancy_high_threshold must be greater than occupancy_low_threshold'], 400);
    }
    if ($fields['demand_window_days'] < 1 || $fields['demand_window_days'] > 60) {
        jsonResponse(['error' => 'demand_window_days must be between 1 and 60'], 400);
    }
    if ($fields['demand_low_threshold'] < 0 || $fields['demand_high_threshold'] < $fields['demand_low_threshold']) {
        jsonResponse(['error' => 'Invalid demand threshold configuration'], 400);
    }
    if ($fields['lead_time_last_minute_days'] < 0 || $fields['lead_time_early_bird_days'] < $fields['lead_time_last_minute_days']) {
        jsonResponse(['error' => 'Invalid lead-time day configuration'], 400);
    }

    $adjustmentFields = [
        'occupancy_low_adjustment',
        'occupancy_high_adjustment',
        'demand_low_adjustment',
        'demand_high_adjustment',
        'lead_time_last_minute_adjustment',
        'lead_time_early_bird_adjustment',
        'manual_global_adjustment'
    ];

    foreach ($adjustmentFields as $field) {
        $value = (float)$fields[$field];
        if ($value < -0.50 || $value > 0.50) {
            jsonResponse(['error' => $field . ' must be between -0.50 and 0.50'], 400);
        }
    }
}
