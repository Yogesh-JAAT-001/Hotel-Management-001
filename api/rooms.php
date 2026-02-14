<?php
require_once '../config.php';
require_once '../includes/media-helper.php';

initApiRequest(['GET'], false);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        getRooms();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getRooms() {
    global $pdo;
    
    // Get query parameters for filtering
    $tier = isset($_GET['tier']) ? (int)$_GET['tier'] : null;
    $room_type = isset($_GET['room_type']) ? sanitize($_GET['room_type']) : null;
    $min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $features = isset($_GET['features']) ? explode(',', $_GET['features']) : [];
    $check_in = isset($_GET['check_in']) ? $_GET['check_in'] : null;
    $check_out = isset($_GET['check_out']) ? $_GET['check_out'] : null;
    $available_only = isset($_GET['available_only']) ? (bool)$_GET['available_only'] : true;
    
    try {
        $hasFeatureIcons = dbHasColumn($pdo, 'ROOM_FEATURES', 'icon');
        $sql = "
            SELECT DISTINCT
                r.room_id,
                r.room_no,
                r.tier,
                r.rent,
                r.status,
                r.description,
                r.image_path,
                rt.name as room_type_name,
                rt.description as room_type_description,
                GROUP_CONCAT(DISTINCT rf.name) as features,
                " . ($hasFeatureIcons ? "GROUP_CONCAT(DISTINCT rf.icon)" : "''") . " as feature_icons
            FROM ROOMS r
            JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
            LEFT JOIN ROOM_FEATURES_MAP rfm ON r.room_id = rfm.room_id
            LEFT JOIN ROOM_FEATURES rf ON rfm.feature_id = rf.room_feature_id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Apply filters
        if ($tier) {
            $sql .= " AND r.tier = ?";
            $params[] = $tier;
        }
        
        if ($room_type) {
            $sql .= " AND rt.name = ?";
            $params[] = $room_type;
        }
        
        if ($min_price) {
            $sql .= " AND r.rent >= ?";
            $params[] = $min_price;
        }
        
        if ($max_price) {
            $sql .= " AND r.rent <= ?";
            $params[] = $max_price;
        }
        
        if ($available_only) {
            $sql .= " AND r.status = 'Available'";
        }
        
        // Check availability for specific dates
        if ($check_in && $check_out) {
            $sql .= " AND r.room_id NOT IN (
                SELECT DISTINCT room_id 
                FROM RESERVATION 
                WHERE room_id IS NOT NULL 
                AND status IN ('Confirmed', 'Checked-in')
                AND (
                    (check_in <= ? AND check_out > ?) OR
                    (check_in < ? AND check_out >= ?) OR
                    (check_in >= ? AND check_out <= ?)
                )
            )";
            $params = array_merge($params, [$check_in, $check_in, $check_out, $check_out, $check_in, $check_out]);
        }
        
        $sql .= " GROUP BY r.room_id ORDER BY r.tier, r.rent";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rooms = $stmt->fetchAll();
        
        // Process rooms data
        foreach ($rooms as &$room) {
            $room['features'] = $room['features'] ? explode(',', $room['features']) : [];
            $room['feature_icons'] = $room['feature_icons'] ? explode(',', $room['feature_icons']) : [];
            $room['rent'] = (float)$room['rent'];
            $room['is_available'] = $room['status'] === 'Available';
            
            $room['image_url'] = resolveRoomImageUrl(
                $room['image_path'] ?? '',
                $room['room_type_name'] ?? '',
                $room['room_no'] ?? null
            );
            $room['image_fallback_url'] = appUrl(
                roomFallbackPath($room['room_type_name'] ?? '', $room['room_no'] ?? null)
            );
        }
        
        // Filter by features if specified
        if (!empty($features)) {
            $rooms = array_filter($rooms, function($room) use ($features) {
                return !empty(array_intersect($features, $room['features']));
            });
            $rooms = array_values($rooms); // Re-index array
        }
        
        jsonResponse([
            'success' => true,
            'data' => $rooms,
            'count' => count($rooms),
            'filters_applied' => [
                'tier' => $tier,
                'room_type' => $room_type,
                'min_price' => $min_price,
                'max_price' => $max_price,
                'features' => $features,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'available_only' => $available_only
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Get rooms error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch rooms'], 500);
    }
}
?>
