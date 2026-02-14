<?php
require_once '../../config.php';
require_once '../../includes/media-helper.php';

initApiRequest(['GET', 'POST', 'PUT', 'DELETE']);

if (!isAdmin()) {
    jsonResponse(['error' => 'Admin access required'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getRoomDetails();
        break;
    case 'POST':
        createRoom();
        break;
    case 'PUT':
        updateRoom();
        break;
    case 'DELETE':
        deleteRoom();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getRoomDetails() {
    global $pdo;
    
    $room_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$room_id) {
        jsonResponse(['error' => 'Room ID is required'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                rt.name as room_type_name,
                GROUP_CONCAT(DISTINCT rf.room_feature_id) as feature_ids,
                GROUP_CONCAT(DISTINCT rf.name) as features
            FROM ROOMS r
            JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
            LEFT JOIN ROOM_FEATURES_MAP rfm ON r.room_id = rfm.room_id
            LEFT JOIN ROOM_FEATURES rf ON rfm.feature_id = rf.room_feature_id
            WHERE r.room_id = ?
            GROUP BY r.room_id
        ");
        
        $stmt->execute([$room_id]);
        $room = $stmt->fetch();
        
        if (!$room) {
            jsonResponse(['error' => 'Room not found'], 404);
        }
        
        // Process data
        $room['feature_ids'] = $room['feature_ids'] ? array_map('intval', explode(',', $room['feature_ids'])) : [];
        $room['features'] = $room['features'] ? explode(',', $room['features']) : [];
        $room['rent'] = (float)$room['rent'];
        
        $room['image_url'] = resolveRoomImageUrl(
            $room['image_path'] ?? '',
            $room['room_type_name'] ?? '',
            $room['room_no'] ?? null
        );
        
        jsonResponse([
            'success' => true,
            'data' => $room
        ]);
        
    } catch (PDOException $e) {
        error_log("Get room details error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch room details'], 500);
    }
}

function createRoom() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['room_no', 'room_type_id', 'tier', 'rent'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            jsonResponse(['error' => "Field '$field' is required"], 400);
        }
    }
    
    $room_no = sanitize($input['room_no']);
    $room_type_id = (int)$input['room_type_id'];
    $tier = (int)$input['tier'];
    $rent = (float)$input['rent'];
    $description = isset($input['description']) ? sanitize($input['description']) : null;
    $image_path = isset($input['image_path']) ? sanitize($input['image_path']) : null;
    $features = isset($input['features']) ? $input['features'] : [];
    
    // Validate tier
    if (!in_array($tier, [1, 2, 3])) {
        jsonResponse(['error' => 'Invalid tier. Must be 1, 2, or 3'], 400);
    }
    
    try {
        $pdo->beginTransaction();

        $roomsHasHotelId = dbHasColumn($pdo, 'ROOMS', 'hotel_id');
        $hotelHasRoomsCount = dbHasColumn($pdo, 'HOTEL', 'rooms_count');
        
        // Check if room number already exists
        $stmt = $pdo->prepare("SELECT room_id FROM ROOMS WHERE room_no = ?");
        $stmt->execute([$room_no]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Room number already exists'], 409);
        }
        
        $hotelId = null;
        if ($roomsHasHotelId) {
            // Get hotel_id (assuming single hotel)
            $stmt = $pdo->query("SELECT hotel_id FROM HOTEL ORDER BY hotel_id ASC LIMIT 1");
            $hotel = $stmt->fetch();
            if (!$hotel) {
                $pdo->rollBack();
                jsonResponse(['error' => 'No hotel found'], 500);
            }
            $hotelId = (int)$hotel['hotel_id'];
        }

        // Insert room (ROOMS schema may or may not have hotel_id)
        if ($roomsHasHotelId) {
            $stmt = $pdo->prepare("
                INSERT INTO ROOMS (room_no, hotel_id, room_type_id, tier, rent, description, image_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Available')
            ");
            $stmt->execute([$room_no, $hotelId, $room_type_id, $tier, $rent, $description, $image_path]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ROOMS (room_no, room_type_id, tier, rent, description, image_path, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Available')
            ");
            $stmt->execute([$room_no, $room_type_id, $tier, $rent, $description, $image_path]);
        }
        $room_id = $pdo->lastInsertId();
        
        // Insert room features
        if (!empty($features)) {
            $stmt = $pdo->prepare("INSERT INTO ROOM_FEATURES_MAP (room_id, feature_id) VALUES (?, ?)");
            foreach ($features as $feature_id) {
                $stmt->execute([$room_id, (int)$feature_id]);
            }
        }
        
        // Update hotel room count if schema supports it.
        if ($roomsHasHotelId && $hotelHasRoomsCount && $hotelId !== null) {
            $stmt = $pdo->prepare("UPDATE HOTEL SET rooms_count = (SELECT COUNT(*) FROM ROOMS WHERE hotel_id = ?) WHERE hotel_id = ?");
            $stmt->execute([$hotelId, $hotelId]);
        }
        
        $pdo->commit();
        logAdminAction('room_create', 'ROOMS', (string)$room_id, 'room_no=' . $room_no);
        
        jsonResponse([
            'success' => true,
            'message' => 'Room created successfully',
            'data' => ['room_id' => $room_id]
        ], 201);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        logSystemError('admin_rooms_create', (string)$e->getMessage(), 'room_no=' . $room_no);
        jsonResponse(['error' => 'Failed to create room'], 500);
    }
}

function updateRoom() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['room_id'])) {
        jsonResponse(['error' => 'Room ID is required'], 400);
    }
    
    $room_id = (int)$input['room_id'];
    $room_no = sanitize($input['room_no']);
    $room_type_id = (int)$input['room_type_id'];
    $tier = (int)$input['tier'];
    $rent = (float)$input['rent'];
    $status = sanitize($input['status']);
    $description = isset($input['description']) ? sanitize($input['description']) : null;
    $image_path = isset($input['image_path']) ? sanitize($input['image_path']) : null;
    $features = isset($input['features']) ? $input['features'] : [];
    
    // Validate status
    $valid_statuses = ['Available', 'Occupied', 'Maintenance', 'Reserved'];
    if (!in_array($status, $valid_statuses)) {
        jsonResponse(['error' => 'Invalid status'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if room exists
        $stmt = $pdo->prepare("SELECT room_id FROM ROOMS WHERE room_id = ?");
        $stmt->execute([$room_id]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Room not found'], 404);
        }
        
        // Check if room number is unique (excluding current room)
        $stmt = $pdo->prepare("SELECT room_id FROM ROOMS WHERE room_no = ? AND room_id != ?");
        $stmt->execute([$room_no, $room_id]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Room number already exists'], 409);
        }
        
        // Update room
        $sql = "UPDATE ROOMS SET room_no = ?, room_type_id = ?, tier = ?, rent = ?, status = ?, description = ?";
        $params = [$room_no, $room_type_id, $tier, $rent, $status, $description];
        
        if ($image_path) {
            $sql .= ", image_path = ?";
            $params[] = $image_path;
        }
        
        $sql .= " WHERE room_id = ?";
        $params[] = $room_id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Update room features
        // First, delete existing features
        $stmt = $pdo->prepare("DELETE FROM ROOM_FEATURES_MAP WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        // Insert new features
        if (!empty($features)) {
            $stmt = $pdo->prepare("INSERT INTO ROOM_FEATURES_MAP (room_id, feature_id) VALUES (?, ?)");
            foreach ($features as $feature_id) {
                $stmt->execute([$room_id, (int)$feature_id]);
            }
        }
        
        $pdo->commit();
        logAdminAction('room_update', 'ROOMS', (string)$room_id, 'room_no=' . $room_no . '; status=' . $status);
        
        jsonResponse([
            'success' => true,
            'message' => 'Room updated successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        logSystemError('admin_rooms_update', (string)$e->getMessage(), 'room_id=' . $room_id);
        jsonResponse(['error' => 'Failed to update room'], 500);
    }
}

function deleteRoom() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['room_id'])) {
        jsonResponse(['error' => 'Room ID is required'], 400);
    }
    
    $room_id = (int)$input['room_id'];
    
    try {
        $pdo->beginTransaction();

        $roomsHasHotelId = dbHasColumn($pdo, 'ROOMS', 'hotel_id');
        $hotelHasRoomsCount = dbHasColumn($pdo, 'HOTEL', 'rooms_count');
        $hotelId = null;
        
        // Check if room has active reservations
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_reservations 
            FROM RESERVATION 
            WHERE room_id = ? AND status IN ('Confirmed', 'Checked-in')
        ");
        $stmt->execute([$room_id]);
        $result = $stmt->fetch();
        
        if ($result['active_reservations'] > 0) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Cannot delete room with active reservations'], 409);
        }
        
        if ($roomsHasHotelId) {
            // Get hotel_id before deletion
            $stmt = $pdo->prepare("SELECT hotel_id FROM ROOMS WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $room = $stmt->fetch();
            if ($room && isset($room['hotel_id'])) {
                $hotelId = (int)$room['hotel_id'];
            }
        } else {
            // Ensure room exists before deletion
            $stmt = $pdo->prepare("SELECT room_id FROM ROOMS WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $room = $stmt->fetch();
        }
        
        if (!$room) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Room not found'], 404);
        }
        
        // Delete room (cascading will handle features and reservations)
        $stmt = $pdo->prepare("DELETE FROM ROOMS WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        // Update hotel room count if schema supports it.
        if ($roomsHasHotelId && $hotelHasRoomsCount && $hotelId !== null) {
            $stmt = $pdo->prepare("UPDATE HOTEL SET rooms_count = (SELECT COUNT(*) FROM ROOMS WHERE hotel_id = ?) WHERE hotel_id = ?");
            $stmt->execute([$hotelId, $hotelId]);
        }
        
        $pdo->commit();
        logAdminAction('room_delete', 'ROOMS', (string)$room_id, 'deleted_by_admin');
        
        jsonResponse([
            'success' => true,
            'message' => 'Room deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        logSystemError('admin_rooms_delete', (string)$e->getMessage(), 'room_id=' . $room_id);
        jsonResponse(['error' => 'Failed to delete room'], 500);
    }
}
?>
