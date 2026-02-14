<?php
require_once '../../config.php';
require_once '../../includes/analytics-engine.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Admin authentication required']);
    exit();
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'monthly';
$format = isset($_GET['format']) ? strtolower(trim((string)$_GET['format'])) : 'csv';
$format = in_array($format, ['csv', 'xls'], true) ? $format : 'csv';

$filters = analyticsNormalizeFilters($_GET);

function analyticsExportSendCsv($filename, $headers, $rows, $format = 'csv') {
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    $extension = $format === 'xls' ? 'xls' : 'csv';
    $contentType = $format === 'xls' ? 'application/vnd.ms-excel' : 'text/csv';

    header('Content-Type: ' . $contentType . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $safeName . '.' . $extension . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $headerKey) {
            $line[] = $row[$headerKey] ?? '';
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit();
}

try {
    $snapshot = analyticsSnapshot($pdo, $filters);

    switch ($type) {
        case 'food':
        case 'food_top':
            $headers = ['Rank', 'Dish', 'Category', 'Type', 'Quantity Sold', 'Revenue', 'Revenue %'];
            $rows = array_map(function ($row) {
                return [
                    'Rank' => $row['rank'] ?? '',
                    'Dish' => $row['title'] ?? '',
                    'Category' => $row['menu_category'] ?? '',
                    'Type' => $row['food_type'] ?? '',
                    'Quantity Sold' => $row['quantity_sold'] ?? 0,
                    'Revenue' => $row['revenue'] ?? 0,
                    'Revenue %' => $row['revenue_pct'] ?? 0
                ];
            }, $snapshot['food']['top_dishes'] ?? []);
            analyticsExportSendCsv('food_analytics_top_selling', $headers, $rows, $format);
            break;

        case 'food_category':
            $headers = ['Category', 'Total Orders', 'Quantity Sold', 'Revenue'];
            $rows = array_map(function ($row) {
                return [
                    'Category' => $row['category'] ?? '',
                    'Total Orders' => $row['total_orders'] ?? 0,
                    'Quantity Sold' => $row['quantity_sold'] ?? 0,
                    'Revenue' => $row['revenue'] ?? 0
                ];
            }, $snapshot['food']['category_performance'] ?? []);
            analyticsExportSendCsv('food_analytics_categories', $headers, $rows, $format);
            break;

        case 'reservations':
            $schema = analyticsGetSchema($pdo);
            $dateExpr = str_replace('res.', 'r.', $schema['reservation_date_expr']);
            $createdAtColumn = analyticsHasColumn($pdo, 'RESERVATION', 'created_at') ? 'r.created_at' : 'r.check_in';
            $where = "{$dateExpr} BETWEEN :from_date AND :to_date";
            $params = [
                ':from_date' => $filters['from_date'],
                ':to_date' => $filters['to_date']
            ];

            $allowedStatuses = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];
            $requestedStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
            if (in_array($requestedStatus, $allowedStatuses, true)) {
                $where .= " AND r.status = :status";
                $params[':status'] = $requestedStatus;
            }

            $requestedRoomType = isset($_GET['room_type']) ? trim((string)$_GET['room_type']) : '';
            if ($requestedRoomType !== '' && strtolower($requestedRoomType) !== 'all') {
                $where .= " AND LOWER(COALESCE(rt.name, '')) = LOWER(:room_type)";
                $params[':room_type'] = $requestedRoomType;
            }

            $sql = "
                SELECT
                    r.res_id,
                    g.name AS guest_name,
                    g.email AS guest_email,
                    g.phone_no,
                    rm.room_no,
                    rt.name AS room_type,
                    r.check_in,
                    r.check_out,
                    r.status,
                    r.total_price,
                    {$createdAtColumn} AS created_at
                FROM RESERVATION r
                JOIN GUEST g ON g.guest_id = r.guest_id
                LEFT JOIN ROOMS rm ON rm.room_id = r.room_id
                LEFT JOIN ROOM_TYPE rt ON rt.room_type_id = rm.room_type_id
                WHERE {$where}
                ORDER BY {$createdAtColumn} DESC, r.res_id DESC
            ";
            $stmt = $pdo->prepare($sql);
            analyticsBindParams($stmt, $params);
            $stmt->execute();
            $reservationRows = $stmt->fetchAll();

            $headers = ['Reservation ID', 'Guest Name', 'Guest Email', 'Phone', 'Room No', 'Room Type', 'Check In', 'Check Out', 'Status', 'Total Price', 'Created At'];
            $rows = array_map(function ($row) {
                return [
                    'Reservation ID' => $row['res_id'] ?? '',
                    'Guest Name' => $row['guest_name'] ?? '',
                    'Guest Email' => $row['guest_email'] ?? '',
                    'Phone' => $row['phone_no'] ?? '',
                    'Room No' => $row['room_no'] ?? '',
                    'Room Type' => $row['room_type'] ?? '',
                    'Check In' => $row['check_in'] ?? '',
                    'Check Out' => $row['check_out'] ?? '',
                    'Status' => $row['status'] ?? '',
                    'Total Price' => $row['total_price'] ?? 0,
                    'Created At' => $row['created_at'] ?? ''
                ];
            }, $reservationRows);

            analyticsExportSendCsv('reservations_report', $headers, $rows, $format);
            break;

        case 'rooms':
        case 'room_type':
            $headers = ['Room Type', 'Bookings', 'Room Nights', 'Revenue', 'Occupancy %'];
            $rows = array_map(function ($row) {
                return [
                    'Room Type' => $row['room_type'] ?? '',
                    'Bookings' => $row['total_bookings'] ?? 0,
                    'Room Nights' => $row['room_nights'] ?? 0,
                    'Revenue' => $row['revenue'] ?? 0,
                    'Occupancy %' => $row['occupancy_rate'] ?? 0
                ];
            }, $snapshot['rooms']['room_type_performance'] ?? []);
            analyticsExportSendCsv('room_analytics_room_types', $headers, $rows, $format);
            break;

        case 'floor':
            $headers = ['Floor', 'Total Rooms', 'Available Rooms', 'Booked Rooms', 'Bookings', 'Revenue', 'Utilization %'];
            $rows = array_map(function ($row) {
                return [
                    'Floor' => $row['floor'] ?? '',
                    'Total Rooms' => $row['total_rooms'] ?? 0,
                    'Available Rooms' => $row['available_rooms'] ?? 0,
                    'Booked Rooms' => $row['booked_rooms'] ?? 0,
                    'Bookings' => $row['booking_count'] ?? 0,
                    'Revenue' => $row['revenue'] ?? 0,
                    'Utilization %' => $row['utilization_pct'] ?? 0
                ];
            }, $snapshot['rooms']['floor_utilization'] ?? []);
            analyticsExportSendCsv('room_analytics_floor_utilization', $headers, $rows, $format);
            break;

        case 'financial':
        case 'monthly':
        case 'monthly_report':
            $headers = ['Month', 'Bookings', 'Total Revenue', 'Room Revenue', 'Food Revenue', 'Fixed Cost', 'Variable Cost', 'Total Cost', 'Net Profit'];
            $rows = array_map(function ($row) {
                return [
                    'Month' => $row['month_label'] ?? '',
                    'Bookings' => $row['bookings'] ?? 0,
                    'Total Revenue' => $row['total_revenue'] ?? 0,
                    'Room Revenue' => $row['room_revenue'] ?? 0,
                    'Food Revenue' => $row['food_revenue'] ?? 0,
                    'Fixed Cost' => $row['fixed_cost'] ?? 0,
                    'Variable Cost' => $row['variable_cost'] ?? 0,
                    'Total Cost' => $row['total_cost'] ?? 0,
                    'Net Profit' => $row['net_profit'] ?? 0
                ];
            }, $snapshot['financial']['monthly_trend'] ?? []);
            analyticsExportSendCsv('financial_monthly_report', $headers, $rows, $format);
            break;

        case 'quarterly':
        case 'quarterly_report':
            $headers = ['Quarter', 'Bookings', 'Revenue', 'Cost', 'Profit'];
            $rows = array_map(function ($row) {
                return [
                    'Quarter' => $row['quarter'] ?? '',
                    'Bookings' => $row['bookings'] ?? 0,
                    'Revenue' => $row['revenue'] ?? 0,
                    'Cost' => $row['cost'] ?? 0,
                    'Profit' => $row['profit'] ?? 0
                ];
            }, $snapshot['quarterly'] ?? []);
            analyticsExportSendCsv('financial_quarterly_report', $headers, $rows, $format);
            break;

        case 'charts':
        case 'chart_data':
            $dashboard = analyticsDashboardPayload($pdo, $_GET);
            $monthlyRows = $dashboard['snapshot']['financial']['monthly_trend'] ?? [];
            $headers = ['Month', 'Revenue', 'Bookings', 'Occupancy %', 'Cost'];
            $rows = [];
            foreach ($monthlyRows as $index => $row) {
                $occupancyValue = $dashboard['snapshot']['rooms']['chart']['occupancy_month_values'][$index] ?? 0;
                $revenue = (float)($row['room_revenue'] ?? 0) + (float)($row['food_revenue'] ?? 0);
                if ($revenue <= 0 && isset($row['total_revenue'])) {
                    $revenue = (float)$row['total_revenue'];
                }

                $rows[] = [
                    'Month' => $row['month_label'] ?? '',
                    'Revenue' => round($revenue, 2),
                    'Bookings' => (int)($row['bookings'] ?? 0),
                    'Occupancy %' => round((float)$occupancyValue, 2),
                    'Cost' => round((float)($row['total_cost'] ?? 0), 2)
                ];
            }
            analyticsExportSendCsv('dashboard_chart_data', $headers, $rows, $format);
            break;

        default:
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unsupported export type']);
            exit();
    }
} catch (Throwable $e) {
    error_log('Analytics export error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to export analytics']);
    exit();
}
