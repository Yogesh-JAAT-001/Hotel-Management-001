<?php
require_once '../../config.php';

initApiRequest(['GET', 'PUT', 'DELETE']);

if (!isAdmin()) {
    jsonResponse([
        'success' => false,
        'status' => 'error',
        'message' => 'Admin access required',
        'error' => 'Admin access required',
        'data' => null
    ], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($method) {
    case 'GET':
        adminGetReservationDetail();
        break;
    case 'PUT':
        adminUpdateReservationStatus();
        break;
    case 'DELETE':
        adminDeleteReservation();
        break;
    default:
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Method not allowed',
            'error' => 'Method not allowed',
            'data' => null
        ], 405);
}

function adminReservationDateOrderExpr(PDO $pdo) {
    if (dbHasColumn($pdo, 'RESERVATION', 'created_at')) {
        return 'res.created_at';
    }

    if (dbHasColumn($pdo, 'RESERVATION', 'r_date')) {
        return 'res.r_date';
    }

    return 'res.check_in';
}

function adminGetReservationDetail() {
    global $pdo;

    $reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($reservationId <= 0) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Reservation ID is required',
            'error' => 'Reservation ID is required',
            'data' => null
        ], 400);
    }

    try {
        $stmt = $pdo->prepare("\n            SELECT\n                res.*,\n                g.name AS guest_name,\n                g.email AS guest_email,\n                g.phone_no AS guest_phone,\n                r.room_no,\n                rt.name AS room_type,\n                rest.name AS reservation_type,\n                p.payment_id,\n                p.status AS payment_status,\n                p.payment_method,\n                p.amount AS payment_amount,\n                p.txn_id\n            FROM RESERVATION res\n            JOIN GUEST g ON g.guest_id = res.guest_id\n            LEFT JOIN ROOMS r ON r.room_id = res.room_id\n            LEFT JOIN ROOM_TYPE rt ON rt.room_type_id = r.room_type_id\n            LEFT JOIN RESERVATION_TYPE rest ON rest.reservation_type_id = res.reservation_type_id\n            LEFT JOIN PAYMENTS p ON p.res_id = res.res_id\n            WHERE res.res_id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            jsonResponse([
                'success' => false,
                'status' => 'error',
                'message' => 'Reservation not found',
                'error' => 'Reservation not found',
                'data' => null
            ], 404);
        }

        $qtyCol = dbHasColumn($pdo, 'RESERVATION_FOOD', 'quantity') ? 'quantity' : (dbHasColumn($pdo, 'RESERVATION_FOOD', 'qty') ? 'qty' : null);
        $qtyExpr = $qtyCol ? "COALESCE(rf.{$qtyCol}, 1)" : '1';
        $foodNameCol = dbFirstExistingColumn($pdo, 'FOOD_DINING', ['title', 'food_name', 'name']) ?? 'title';

        $foodStmt = $pdo->prepare("\n            SELECT\n                rf.food_id,\n                fd.{$foodNameCol} AS item_name,\n                {$qtyExpr} AS quantity,\n                COALESCE(rf.price, fd.price, 0) AS unit_price,\n                ({$qtyExpr} * COALESCE(rf.price, fd.price, 0)) AS line_total\n            FROM RESERVATION_FOOD rf\n            JOIN FOOD_DINING fd ON fd.food_id = rf.food_id\n            WHERE rf.res_id = ?\n            ORDER BY rf.food_id ASC\n        ");
        $foodStmt->execute([$reservationId]);
        $foodItems = $foodStmt->fetchAll();

        foreach ($foodItems as &$item) {
            $item['quantity'] = (int)$item['quantity'];
            $item['unit_price'] = round((float)$item['unit_price'], 2);
            $item['line_total'] = round((float)$item['line_total'], 2);
        }
        unset($item);

        $reservation['total_price'] = round((float)($reservation['total_price'] ?? 0), 2);

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Reservation details loaded successfully',
            'data' => [
                'reservation' => $reservation,
                'food_items' => $foodItems
            ]
        ]);
    } catch (Throwable $e) {
        logSystemError('admin_reservation_detail', (string)$e->getMessage(), 'reservation_id=' . $reservationId);
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to fetch reservation details',
            'error' => 'Failed to fetch reservation details',
            'data' => null
        ], 500);
    }
}

function adminUpdateReservationStatus() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Invalid request payload',
            'error' => 'Invalid request payload',
            'data' => null
        ], 400);
    }

    $reservationId = isset($input['reservation_id']) ? (int)$input['reservation_id'] : 0;
    $status = trim((string)($input['status'] ?? ''));
    $allowed = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];

    if ($reservationId <= 0 || !in_array($status, $allowed, true)) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Valid reservation_id and status are required',
            'error' => 'Valid reservation_id and status are required',
            'data' => null
        ], 422);
    }

    try {
        $pdo->beginTransaction();

        $detailStmt = $pdo->prepare('SELECT res_id, room_id, status FROM RESERVATION WHERE res_id = ? LIMIT 1');
        $detailStmt->execute([$reservationId]);
        $reservation = $detailStmt->fetch();

        if (!$reservation) {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'status' => 'error',
                'message' => 'Reservation not found',
                'error' => 'Reservation not found',
                'data' => null
            ], 404);
        }

        if ((string)$reservation['status'] === 'Cancelled' && $status === 'Confirmed') {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'status' => 'error',
                'message' => 'Cancelled reservation cannot be confirmed directly',
                'error' => 'Cancelled reservation cannot be confirmed directly',
                'data' => null
            ], 409);
        }

        $updateSql = 'UPDATE RESERVATION SET status = :status';
        if (dbHasColumn($pdo, 'RESERVATION', 'updated_at')) {
            $updateSql .= ', updated_at = CURRENT_TIMESTAMP';
        }
        $updateSql .= ' WHERE res_id = :res_id';

        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([
            ':status' => $status,
            ':res_id' => $reservationId
        ]);

        $roomId = isset($reservation['room_id']) ? (int)$reservation['room_id'] : 0;
        if ($roomId > 0) {
            if ($status === 'Cancelled' || $status === 'Checked-out') {
                $activeStmt = $pdo->prepare("\n                    SELECT COUNT(*) AS total\n                    FROM RESERVATION\n                    WHERE room_id = ?\n                      AND res_id != ?\n                      AND status IN ('Confirmed', 'Checked-in')\n                ");
                $activeStmt->execute([$roomId, $reservationId]);
                $otherActive = (int)($activeStmt->fetch()['total'] ?? 0);

                if ($otherActive === 0) {
                    $pdo->prepare("UPDATE ROOMS SET status = 'Available' WHERE room_id = ?")->execute([$roomId]);
                }
            } elseif ($status === 'Confirmed') {
                $pdo->prepare("UPDATE ROOMS SET status = 'Reserved' WHERE room_id = ? AND status = 'Available'")->execute([$roomId]);
            } elseif ($status === 'Checked-in') {
                $pdo->prepare("UPDATE ROOMS SET status = 'Occupied' WHERE room_id = ?")->execute([$roomId]);
            }
        }

        $pdo->commit();
        logAdminAction('reservation_status_update', 'RESERVATION', (string)$reservationId, 'status=' . $status);

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Reservation status updated successfully',
            'data' => [
                'reservation_id' => $reservationId,
                'status' => $status
            ]
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logSystemError('admin_reservation_update', (string)$e->getMessage(), 'reservation_id=' . $reservationId);
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to update reservation status',
            'error' => 'Failed to update reservation status',
            'data' => null
        ], 500);
    }
}

function adminDeleteReservation() {
    global $pdo;

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Invalid request payload',
            'error' => 'Invalid request payload',
            'data' => null
        ], 400);
    }

    $reservationId = isset($input['reservation_id']) ? (int)$input['reservation_id'] : 0;
    if ($reservationId <= 0) {
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Reservation ID is required',
            'error' => 'Reservation ID is required',
            'data' => null
        ], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT room_id, status FROM RESERVATION WHERE res_id = ? LIMIT 1');
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        if (!$reservation) {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'status' => 'error',
                'message' => 'Reservation not found',
                'error' => 'Reservation not found',
                'data' => null
            ], 404);
        }

        if ((string)$reservation['status'] === 'Checked-in') {
            $pdo->rollBack();
            jsonResponse([
                'success' => false,
                'status' => 'error',
                'message' => 'Cannot delete a checked-in reservation',
                'error' => 'Cannot delete a checked-in reservation',
                'data' => null
            ], 409);
        }

        $pdo->prepare('DELETE FROM RESERVATION WHERE res_id = ?')->execute([$reservationId]);

        $roomId = isset($reservation['room_id']) ? (int)$reservation['room_id'] : 0;
        if ($roomId > 0) {
            $activeStmt = $pdo->prepare("\n                SELECT COUNT(*) AS total\n                FROM RESERVATION\n                WHERE room_id = ?\n                  AND status IN ('Confirmed', 'Checked-in')\n            ");
            $activeStmt->execute([$roomId]);
            $active = (int)($activeStmt->fetch()['total'] ?? 0);
            if ($active === 0) {
                $pdo->prepare("UPDATE ROOMS SET status = 'Available' WHERE room_id = ?")->execute([$roomId]);
            }
        }

        $pdo->commit();
        logAdminAction('reservation_delete', 'RESERVATION', (string)$reservationId, 'deleted_by_admin');

        jsonResponse([
            'success' => true,
            'status' => 'success',
            'message' => 'Reservation deleted successfully',
            'data' => [
                'reservation_id' => $reservationId
            ]
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logSystemError('admin_reservation_delete', (string)$e->getMessage(), 'reservation_id=' . $reservationId);
        jsonResponse([
            'success' => false,
            'status' => 'error',
            'message' => 'Failed to delete reservation',
            'error' => 'Failed to delete reservation',
            'data' => null
        ], 500);
    }
}
