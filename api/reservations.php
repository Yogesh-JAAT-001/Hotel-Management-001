<?php
require_once '../config.php';
require_once '../includes/pricing-engine.php';

initApiRequest(['GET', 'POST', 'PUT']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        getReservations();
        break;
    case 'POST':
        createReservation();
        break;
    case 'PUT':
        updateReservation();
        break;
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}

function getReservations() {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
    
    $guest_id = $_SESSION['user_id'];
    $reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    try {
        $reservationOrderExpr = dbHasColumn($pdo, 'RESERVATION', 'created_at') ? 'res.created_at' : 'res.check_in';

        if ($reservation_id) {
            // Get specific reservation
            $stmt = $pdo->prepare("
                SELECT 
                    res.*,
                    g.name as guest_name,
                    g.phone_no,
                    g.email,
                    r.room_no,
                    r.rent as room_rent,
                    rt.name as room_type,
                    rest.name as reservation_type,
                    rest.payment_rule,
                    p.status as payment_status,
                    p.payment_method,
                    p.txn_id,
                    p.created_at as payment_date
                FROM RESERVATION res
                JOIN GUEST g ON res.guest_id = g.guest_id
                LEFT JOIN ROOMS r ON res.room_id = r.room_id
                LEFT JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
                JOIN RESERVATION_TYPE rest ON res.reservation_type_id = rest.reservation_type_id
                LEFT JOIN PAYMENTS p ON res.res_id = p.res_id
                WHERE res.res_id = ? AND res.guest_id = ?
            ");
            
            $stmt->execute([$reservation_id, $guest_id]);
            $reservation = $stmt->fetch();
            
            if (!$reservation) {
                jsonResponse(['error' => 'Reservation not found'], 404);
            }
            
            // Get food orders for this reservation
            $foodNameCol = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['title', 'food_name', 'name']) ?? 'title';
            $foodTypeCol = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['food_type', 'type']) ?? 'food_type';
            $stmt = $pdo->prepare("
                SELECT 
                    rf.*,
                    fd.{$foodNameCol} AS title,
                    " . ($foodTypeCol ? "fd.{$foodTypeCol}" : "'VEG'") . " AS food_type,
                    fd.description as food_description
                FROM RESERVATION_FOOD rf
                JOIN FOOD_DINING fd ON rf.food_id = fd.food_id
                WHERE rf.res_id = ?
            ");
            
            $stmt->execute([$reservation_id]);
            $food_orders = $stmt->fetchAll();
            
            $reservation['food_orders'] = $food_orders;
            $reservation['total_price'] = (float)$reservation['total_price'];
            
            jsonResponse([
                'success' => true,
                'data' => $reservation
            ]);
            
        } else {
            // Get all reservations for the guest
            $stmt = $pdo->prepare("
                SELECT 
                    res.*,
                    r.room_no,
                    rt.name as room_type,
                    rest.name as reservation_type,
                    p.status as payment_status
                FROM RESERVATION res
                LEFT JOIN ROOMS r ON res.room_id = r.room_id
                LEFT JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
                JOIN RESERVATION_TYPE rest ON res.reservation_type_id = rest.reservation_type_id
                LEFT JOIN PAYMENTS p ON res.res_id = p.res_id
                WHERE res.guest_id = ?
                ORDER BY {$reservationOrderExpr} DESC, res.res_id DESC
            ");
            
            $stmt->execute([$guest_id]);
            $reservations = $stmt->fetchAll();
            
            foreach ($reservations as &$reservation) {
                $reservation['total_price'] = (float)$reservation['total_price'];
            }
            
            jsonResponse([
                'success' => true,
                'data' => $reservations,
                'count' => count($reservations)
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Get reservations error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch reservations'], 500);
    }
}

function createReservation() {
    global $pdo;
    
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request payload'], 400);
    }
    
    // Validate required fields
    $required_fields = ['room_id', 'check_in', 'check_out', 'reservation_type_id'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            jsonResponse(['error' => "Field '$field' is required"], 400);
        }
    }
    
    $guest_id = $_SESSION['user_id'];
    $room_id = (int)$input['room_id'];
    $check_in = $input['check_in'];
    $check_out = $input['check_out'];
    $reservation_type_id = (int)$input['reservation_type_id'];
    $special_requests = isset($input['special_requests']) ? sanitize($input['special_requests']) : null;
    $coupon_code = isset($input['coupon_code']) ? sanitize($input['coupon_code']) : null;
    $food_items = isset($input['food_items']) ? $input['food_items'] : [];
    
    // Validate dates
    try {
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Invalid check-in or check-out date'], 400);
    }
    $today = new DateTime('today');
    
    if ($check_in_date < $today) {
        jsonResponse(['error' => 'Check-in date cannot be in the past'], 400);
    }
    
    if ($check_out_date <= $check_in_date) {
        jsonResponse(['error' => 'Check-out date must be after check-in date'], 400);
    }
    
    try {
        // Ensure pricing metadata exists before starting reservation transaction.
        ensurePricingSchema($pdo);
        $pdo->beginTransaction();
        
        // Check room availability
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as conflicts
            FROM RESERVATION 
            WHERE room_id = ? 
            AND status IN ('Confirmed', 'Checked-in')
            AND (
                (check_in <= ? AND check_out > ?) OR
                (check_in < ? AND check_out >= ?) OR
                (check_in >= ? AND check_out <= ?)
            )
        ");
        
        $stmt->execute([$room_id, $check_in, $check_in, $check_out, $check_out, $check_in, $check_out]);
        $conflicts = $stmt->fetch()['conflicts'];
        
        if ($conflicts > 0) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Room is not available for the selected dates'], 409);
        }
        
        // Get room details
        $stmt = $pdo->prepare("SELECT room_id, rent, status FROM ROOMS WHERE room_id = ?");
        $stmt->execute([$room_id]);
        $room = $stmt->fetch();
        
        if (!$room || $room['status'] !== 'Available') {
            $pdo->rollBack();
            jsonResponse(['error' => 'Room is not available'], 409);
        }
        
        // Calculate dynamic room price
        $pricingQuote = getDynamicPriceQuote($pdo, $room_id, $check_in, $check_out);
        $nights = (int)$pricingQuote['nights'];
        $room_total = (float)$pricingQuote['dynamic_total'];
        $food_total = 0;
        
        // Calculate food total
        foreach ($food_items as $food_item) {
            $stmt = $pdo->prepare("SELECT price FROM FOOD_DINING WHERE food_id = ?");
            $stmt->execute([$food_item['food_id']]);
            $food = $stmt->fetch();
            if ($food) {
                $food_total += $food['price'] * $food_item['qty'];
            }
        }
        
        $total_price = $room_total + $food_total;
        
        // Apply coupon if provided
        $discount = 0;
        if ($coupon_code && dbHasTable($pdo, 'COUPON')) {
            $hasLegacyCoupon = dbHasColumn($pdo, 'COUPON', 'expiry') && dbHasColumn($pdo, 'COUPON', 'type') && dbHasColumn($pdo, 'COUPON', 'value');
            $hasNewCoupon = dbHasColumn($pdo, 'COUPON', 'valid_to') && dbHasColumn($pdo, 'COUPON', 'discount_type') && dbHasColumn($pdo, 'COUPON', 'discount_value');

            $coupon = null;
            if ($hasLegacyCoupon) {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM COUPON
                    WHERE code = ?
                      AND is_active = 1
                      AND expiry >= CURDATE()
                      AND (usage_limit IS NULL OR used_count < usage_limit)
                    LIMIT 1
                ");
                $stmt->execute([$coupon_code]);
                $coupon = $stmt->fetch();
            } elseif ($hasNewCoupon) {
                $hasValidFrom = dbHasColumn($pdo, 'COUPON', 'valid_from');
                $sql = "
                    SELECT *
                    FROM COUPON
                    WHERE code = ?
                      AND is_active = 1
                ";
                if ($hasValidFrom) {
                    $sql .= " AND valid_from <= CURDATE() ";
                }
                $sql .= "
                      AND valid_to >= CURDATE()
                      AND (usage_limit IS NULL OR used_count < usage_limit)
                    LIMIT 1
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$coupon_code]);
                $coupon = $stmt->fetch();
            }

            if ($coupon) {
                if ($hasLegacyCoupon) {
                    if (($coupon['type'] ?? '') === 'Flat') {
                        $discount = min((float)$coupon['value'], $total_price);
                    } else {
                        $discount = ($total_price * (float)$coupon['value']) / 100;
                    }
                } else {
                    $minAmount = isset($coupon['min_amount']) ? (float)$coupon['min_amount'] : 0.0;
                    if ($total_price >= $minAmount) {
                        $discountType = strtoupper((string)($coupon['discount_type'] ?? ''));
                        $discountValue = (float)($coupon['discount_value'] ?? 0);
                        if ($discountType === 'FIXED') {
                            $discount = min($discountValue, $total_price);
                        } else {
                            $discount = ($total_price * $discountValue) / 100;
                            if (isset($coupon['max_discount']) && $coupon['max_discount'] !== null) {
                                $discount = min($discount, (float)$coupon['max_discount']);
                            }
                        }
                    }
                }

                $discount = max(0.0, round((float)$discount, 2));
                if ($discount > 0) {
                    $total_price = max(0.0, $total_price - $discount);
                }

                // Update coupon usage
                $stmt = $pdo->prepare("UPDATE COUPON SET used_count = used_count + 1 WHERE code = ?");
                $stmt->execute([$coupon_code]);
            }
        }
        
        // Create reservation
        $reservationHasRDate = dbHasColumn($pdo, 'RESERVATION', 'r_date');
        if ($reservationHasRDate) {
            $stmt = $pdo->prepare("
                INSERT INTO RESERVATION (
                    guest_id, room_id, r_date, check_in, check_out,
                    reservation_type_id, status, total_price, special_requests
                ) VALUES (?, ?, CURDATE(), ?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->execute([
                $guest_id, $room_id, $check_in, $check_out,
                $reservation_type_id, $total_price, $special_requests
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO RESERVATION (
                    guest_id, room_id, check_in, check_out,
                    reservation_type_id, status, total_price, special_requests
                ) VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)
            ");
            $stmt->execute([
                $guest_id, $room_id, $check_in, $check_out,
                $reservation_type_id, $total_price, $special_requests
            ]);
        }
        
        $reservation_id = $pdo->lastInsertId();
        
        // Add food items to reservation
        $foodQtyColumn = dbFirstExistingColumn($pdo, 'RESERVATION_FOOD', ['qty', 'quantity']) ?? 'qty';
        foreach ($food_items as $food_item) {
            $stmt = $pdo->prepare("SELECT price FROM FOOD_DINING WHERE food_id = ?");
            $stmt->execute([$food_item['food_id']]);
            $food = $stmt->fetch();
            
            if ($food) {
                $stmt = $pdo->prepare("INSERT INTO RESERVATION_FOOD (res_id, food_id, {$foodQtyColumn}, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $reservation_id, 
                    $food_item['food_id'], 
                    $food_item['qty'], 
                    $food['price']
                ]);
            }
        }
        
        // Update room status to Reserved
        $stmt = $pdo->prepare("UPDATE ROOMS SET status = 'Reserved' WHERE room_id = ?");
        $stmt->execute([$room_id]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Reservation created successfully',
            'data' => [
                'reservation_id' => $reservation_id,
                'total_price' => $total_price,
                'discount_applied' => $discount,
                'nights' => $nights,
                'room_total' => $room_total,
                'food_total' => $food_total,
                'pricing_breakdown' => $pricingQuote
            ]
        ], 201);
    
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(['error' => $e->getMessage()], 400);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Create reservation error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to create reservation'], 500);
    }
}

function updateReservation() {
    global $pdo;

    if (!isLoggedIn() && !isAdmin()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(['error' => 'Invalid request payload'], 400);
    }

    $reservation_id = isset($input['reservation_id']) ? (int)$input['reservation_id'] : 0;
    $status = isset($input['status']) ? sanitize($input['status']) : '';

    if ($reservation_id <= 0) {
        jsonResponse(['error' => 'Reservation ID is required'], 400);
    }

    if ($status !== 'Cancelled') {
        jsonResponse(['error' => 'Only cancellation is supported'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT res_id, guest_id, room_id, status
            FROM RESERVATION
            WHERE res_id = ?
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            jsonResponse(['error' => 'Reservation not found'], 404);
        }

        $adminRequest = isAdmin();
        if (!$adminRequest && (int)$reservation['guest_id'] !== (int)$_SESSION['user_id']) {
            jsonResponse(['error' => 'You are not authorized to modify this reservation'], 403);
        }

        if ($reservation['status'] === 'Cancelled') {
            jsonResponse([
                'success' => true,
                'message' => 'Reservation already cancelled',
                'data' => ['reservation_id' => $reservation_id, 'status' => 'Cancelled']
            ]);
        }

        if ($reservation['status'] === 'Checked-out') {
            jsonResponse(['error' => 'Checked-out reservations cannot be cancelled'], 409);
        }

        if (!$adminRequest && !in_array($reservation['status'], ['Pending', 'Confirmed'], true)) {
            jsonResponse(['error' => 'This reservation cannot be cancelled at current stage'], 409);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE RESERVATION SET status = 'Cancelled' WHERE res_id = ?");
        $stmt->execute([$reservation_id]);

        $room_id = isset($reservation['room_id']) ? (int)$reservation['room_id'] : 0;
        if ($room_id > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS active_count
                FROM RESERVATION
                WHERE room_id = ?
                AND res_id != ?
                AND status IN ('Pending', 'Confirmed', 'Checked-in')
            ");
            $stmt->execute([$room_id, $reservation_id]);
            $activeCount = (int)$stmt->fetch()['active_count'];

            if ($activeCount === 0) {
                $stmt = $pdo->prepare("
                    UPDATE ROOMS
                    SET status = 'Available'
                    WHERE room_id = ?
                    AND status = 'Reserved'
                ");
                $stmt->execute([$room_id]);
            }
        }

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Reservation cancelled successfully',
            'data' => [
                'reservation_id' => $reservation_id,
                'status' => 'Cancelled'
            ]
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Update reservation error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to update reservation'], 500);
    }
}
?>
