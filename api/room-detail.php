<?php
require_once '../config.php';
require_once '../includes/media-helper.php';

initApiRequest(['GET'], false);

$room_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$room_id) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Room ID is required',
        'error' => 'Room ID is required',
        'data' => null
    ], 400);
}

try {
    $hasFeatureIcons = dbHasColumn($pdo, 'ROOM_FEATURES', 'icon');

    // Get room details with features
    $sql = "
        SELECT 
            r.*,
            rt.name as room_type_name,
            rt.description as room_type_description,
            GROUP_CONCAT(DISTINCT rf.name) as features,
            " . ($hasFeatureIcons ? "GROUP_CONCAT(DISTINCT rf.icon)" : "''") . " as feature_icons,
            GROUP_CONCAT(DISTINCT rf.room_feature_id) as feature_ids
        FROM ROOMS r
        JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN ROOM_FEATURES_MAP rfm ON r.room_id = rfm.room_id
        LEFT JOIN ROOM_FEATURES rf ON rfm.feature_id = rf.room_feature_id
        WHERE r.room_id = ?
        GROUP BY r.room_id
    ";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Room not found',
            'error' => 'Room not found',
            'data' => null
        ], 404);
    }

    // Attach hotel info (schema variant: ROOMS may not have hotel_id; HOTEL columns vary)
    $hotel = null;
    if (dbHasTable($pdo, 'HOTEL')) {
        $roomsHasHotelId = dbHasColumn($pdo, 'ROOMS', 'hotel_id');
        if ($roomsHasHotelId && !empty($room['hotel_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM HOTEL WHERE hotel_id = ? LIMIT 1");
            $stmt->execute([(int)$room['hotel_id']]);
            $hotel = $stmt->fetch();
        }
        if (!$hotel) {
            $stmt = $pdo->query("SELECT * FROM HOTEL ORDER BY hotel_id ASC LIMIT 1");
            $hotel = $stmt->fetch();
        }
    }

    $room['hotel_name'] = $hotel['name'] ?? APP_NAME;
    $room['hotel_location'] = $hotel['location'] ?? ($hotel['address'] ?? '');
    $room['star_rating'] = isset($hotel['star_rating']) ? (int)$hotel['star_rating'] : 5;
    
    // Process room data
    $room['features'] = $room['features'] ? explode(',', $room['features']) : [];
    $room['feature_icons'] = $room['feature_icons'] ? explode(',', $room['feature_icons']) : [];
    $room['feature_ids'] = $room['feature_ids'] ? array_map('intval', explode(',', $room['feature_ids'])) : [];
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

    // API aliases for cross-module compatibility.
    $room['room_number'] = $room['room_no'] ?? '';
    $room['room_type'] = $room['room_type_name'] ?? '';
    $room['price'] = (float)($room['rent'] ?? 0);
    $room['amenities'] = $room['features'];
    
    // Get room availability for next 30 days
    $stmt = $pdo->prepare("
        SELECT 
            DATE(check_in) as date,
            status
        FROM RESERVATION 
        WHERE room_id = ? 
        AND status IN ('Confirmed', 'Checked-in')
        AND check_in >= CURDATE() 
        AND check_in <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY check_in
    ");
    
    $stmt->execute([$room_id]);
    $bookings = $stmt->fetchAll();
    
    // Get similar rooms (same tier)
    $stmt = $pdo->prepare("
        SELECT 
            r.room_id,
            r.room_no,
            r.tier,
            r.rent,
            r.status,
            r.image_path,
            rt.name as room_type_name
        FROM ROOMS r
        JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
        WHERE r.tier = ? AND r.room_id != ? AND r.status = 'Available'
        LIMIT 4
    ");
    
    $stmt->execute([$room['tier'], $room_id]);
    $similar_rooms = $stmt->fetchAll();
    
    // Process similar rooms
    foreach ($similar_rooms as &$similar_room) {
        $similar_room['rent'] = (float)$similar_room['rent'];
        $similar_room['image_url'] = resolveRoomImageUrl(
            $similar_room['image_path'] ?? '',
            $similar_room['room_type_name'] ?? '',
            $similar_room['room_no'] ?? null
        );
        $similar_room['image_fallback_url'] = appUrl(
            roomFallbackPath($similar_room['room_type_name'] ?? '', $similar_room['room_no'] ?? null)
        );
    }
    
    jsonResponse([
        'success' => true,
        'status' => 'success',
        'message' => 'Room details loaded successfully',
        'data' => $room,
        'bookings' => $bookings,
        'similar_rooms' => $similar_rooms
    ]);
    
} catch (PDOException $e) {
    error_log("Get room detail error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Failed to fetch room details',
        'error' => 'Failed to fetch room details',
        'data' => null
    ], 500);
}
?>
