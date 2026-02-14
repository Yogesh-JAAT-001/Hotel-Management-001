<?php

function analyticsHasTable(PDO $pdo, $tableName) {
    static $cache = [];
    $key = strtoupper((string)$tableName);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$key]);
    $cache[$key] = ((int)$stmt->fetch()['total']) > 0;
    return $cache[$key];
}

function analyticsHasColumn(PDO $pdo, $tableName, $columnName) {
    static $cache = [];
    $tableKey = strtoupper((string)$tableName);
    $columnKey = strtolower((string)$columnName);

    if (isset($cache[$tableKey][$columnKey])) {
        return $cache[$tableKey][$columnKey];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$tableKey, $columnKey]);

    if (!isset($cache[$tableKey])) {
        $cache[$tableKey] = [];
    }
    $cache[$tableKey][$columnKey] = ((int)$stmt->fetch()['total']) > 0;

    return $cache[$tableKey][$columnKey];
}

function analyticsGetSchema(PDO $pdo) {
    static $schema = null;
    if ($schema !== null) {
        return $schema;
    }

    $hasRDate = analyticsHasColumn($pdo, 'RESERVATION', 'r_date');
    $hasCreatedAt = analyticsHasColumn($pdo, 'RESERVATION', 'created_at');

    $reservationDateColumn = $hasRDate ? 'r_date' : ($hasCreatedAt ? 'created_at' : 'check_in');
    $reservationDateExpr = 'DATE(res.' . $reservationDateColumn . ')';

    if ($hasCreatedAt) {
        $reservationDateTimeExpr = 'res.created_at';
    } elseif ($hasRDate) {
        $reservationDateTimeExpr = "CAST(CONCAT(res.r_date, ' 12:00:00') AS DATETIME)";
    } else {
        $reservationDateTimeExpr = "CAST(CONCAT(res.check_in, ' 12:00:00') AS DATETIME)";
    }

    $qtyColumn = null;
    if (analyticsHasColumn($pdo, 'RESERVATION_FOOD', 'quantity')) {
        $qtyColumn = 'quantity';
    } elseif (analyticsHasColumn($pdo, 'RESERVATION_FOOD', 'qty')) {
        $qtyColumn = 'qty';
    }
    $qtyExpr = $qtyColumn ? ('COALESCE(rf.' . $qtyColumn . ', 1)') : '1';

    $foodTitleColumn = analyticsHasColumn($pdo, 'FOOD_DINING', 'title')
        ? 'title'
        : (analyticsHasColumn($pdo, 'FOOD_DINING', 'food_name')
            ? 'food_name'
            : (analyticsHasColumn($pdo, 'FOOD_DINING', 'name') ? 'name' : null));
    $foodTypeColumn = analyticsHasColumn($pdo, 'FOOD_DINING', 'food_type')
        ? 'food_type'
        : (analyticsHasColumn($pdo, 'FOOD_DINING', 'type') ? 'type' : null);
    $foodCategoryColumn = analyticsHasColumn($pdo, 'FOOD_DINING', 'menu_category')
        ? 'menu_category'
        : (analyticsHasColumn($pdo, 'FOOD_DINING', 'category') ? 'category' : null);

    $foodTitleExpr = $foodTitleColumn
        ? "COALESCE(NULLIF(TRIM(fd.{$foodTitleColumn}), ''), CONCAT('Dish #', fd.food_id))"
        : "CONCAT('Dish #', fd.food_id)";
    $foodTypeExpr = $foodTypeColumn
        ? "COALESCE(NULLIF(TRIM(fd.{$foodTypeColumn}), ''), 'VEG')"
        : "'VEG'";
    $hasFoodCategory = $foodCategoryColumn !== null;
    $foodCategoryExpr = $hasFoodCategory
        ? "COALESCE(NULLIF(TRIM(fd.{$foodCategoryColumn}), ''), 'Main Course')"
        : "'Main Course'";

    $schema = [
        'reservation_date_column' => $reservationDateColumn,
        'reservation_date_expr' => $reservationDateExpr,
        'reservation_datetime_expr' => $reservationDateTimeExpr,
        'reservation_food_qty_column' => $qtyColumn,
        'reservation_food_qty_expr' => $qtyExpr,
        'food_title_column' => $foodTitleColumn,
        'food_title_expr' => $foodTitleExpr,
        'food_type_column' => $foodTypeColumn,
        'food_type_expr' => $foodTypeExpr,
        'food_category_column' => $foodCategoryColumn,
        'has_food_category' => $hasFoodCategory,
        'food_category_expr' => $foodCategoryExpr,
        'has_operating_costs' => analyticsHasTable($pdo, 'OPERATING_COSTS'),
        'has_payments' => analyticsHasTable($pdo, 'PAYMENTS'),
        'has_pricing_seasons' => analyticsHasTable($pdo, 'pricing_seasons')
    ];

    return $schema;
}

function analyticsNormalizeFilters($input) {
    $today = new DateTimeImmutable('today');
    $defaultFrom = $today->modify('first day of this month')->modify('-11 months');

    $month = isset($input['month']) ? (int)$input['month'] : 0;
    $year = isset($input['year']) ? (int)$input['year'] : 0;

    $fromDate = isset($input['from_date']) ? trim((string)$input['from_date']) : '';
    $toDate = isset($input['to_date']) ? trim((string)$input['to_date']) : '';

    if ($fromDate === '' && $toDate === '' && $year >= 2000 && $year <= 2100) {
        if ($month >= 1 && $month <= 12) {
            $monthStart = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
            if ($monthStart instanceof DateTimeImmutable) {
                $fromDate = $monthStart->format('Y-m-d');
                $toDate = $monthStart->modify('last day of this month')->format('Y-m-d');
            }
        } else {
            $fromDate = sprintf('%04d-01-01', $year);
            $toDate = sprintf('%04d-12-31', $year);
        }
    }

    $fromObj = DateTimeImmutable::createFromFormat('Y-m-d', $fromDate) ?: $defaultFrom;
    $toObj = DateTimeImmutable::createFromFormat('Y-m-d', $toDate) ?: $today;

    if ($fromObj > $toObj) {
        $temp = $fromObj;
        $fromObj = $toObj;
        $toObj = $temp;
    }

    $allowedCategories = ['all', 'Starters', 'Soups', 'Main Course', 'Breads', 'Rice', 'Desserts', 'Beverages', 'Combos'];
    $category = isset($input['category']) ? trim((string)$input['category']) : 'all';
    if (!in_array($category, $allowedCategories, true)) {
        $category = 'all';
    }

    $topLimit = isset($input['top_limit']) ? (int)$input['top_limit'] : 10;
    if (!in_array($topLimit, [5, 10, 15, 20], true)) {
        $topLimit = 10;
    }

    $page = isset($input['page']) ? max(1, (int)$input['page']) : 1;
    $perPage = isset($input['per_page']) ? (int)$input['per_page'] : $topLimit;
    $perPage = max(5, min(50, $perPage));

    return [
        'from_date' => $fromObj->format('Y-m-d'),
        'to_date' => $toObj->format('Y-m-d'),
        'month' => $month,
        'year' => $year,
        'category' => $category,
        'top_limit' => $topLimit,
        'page' => $page,
        'per_page' => $perPage
    ];
}

function analyticsFloorExpression($alias = 'r') {
    return "CASE
        WHEN {$alias}.room_no REGEXP '^[0-9]+' THEN CAST(SUBSTRING({$alias}.room_no, 1, 1) AS UNSIGNED)
        WHEN {$alias}.room_no REGEXP '^[A-Za-z][0-9]+' THEN CAST(SUBSTRING({$alias}.room_no, 2, 1) AS UNSIGNED)
        ELSE NULL
    END";
}

function analyticsFloorWingLabel($floorNo) {
    $floorNo = (int)$floorNo;
    $wingMap = [
        1 => 'Standard Wing',
        2 => 'Deluxe Wing',
        3 => 'Executive Wing',
        4 => 'Royal Wing',
        5 => 'Presidential Wing'
    ];

    if (!isset($wingMap[$floorNo])) {
        return $floorNo > 0 ? ('Floor ' . $floorNo) : 'N/A';
    }

    return 'Floor ' . $floorNo . ' - ' . $wingMap[$floorNo];
}

function analyticsBindParams(PDOStatement $stmt, $params) {
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
}

function analyticsMoney($value) {
    return round((float)$value, 2);
}

function analyticsSeriesMonths($fromDate, $toDate) {
    $series = [];
    $cursor = (new DateTimeImmutable($fromDate))->modify('first day of this month');
    $end = (new DateTimeImmutable($toDate))->modify('first day of this month');

    while ($cursor <= $end) {
        $series[$cursor->format('Y-m')] = [
            'month_key' => $cursor->format('Y-m'),
            'month_label' => $cursor->format('M Y')
        ];
        $cursor = $cursor->modify('+1 month');
    }

    return $series;
}

function analyticsFoodData(PDO $pdo, $filters) {
    $schema = analyticsGetSchema($pdo);
    $dateExpr = $schema['reservation_date_expr'];
    $dateTimeExpr = $schema['reservation_datetime_expr'];
    $qtyExpr = $schema['reservation_food_qty_expr'];
    $foodTitleExpr = $schema['food_title_expr'];
    $foodTypeExpr = $schema['food_type_expr'];
    $categoryExpr = $schema['food_category_expr'];

    $where = "res.status IN ('Confirmed', 'Checked-in', 'Checked-out')
              AND {$dateExpr} BETWEEN :from_date AND :to_date";
    $params = [
        ':from_date' => $filters['from_date'],
        ':to_date' => $filters['to_date']
    ];

    if ($schema['has_food_category'] && $filters['category'] !== 'all') {
        $where .= " AND {$categoryExpr} = :category";
        $params[':category'] = $filters['category'];
    }

    $summarySql = "
        SELECT
            COUNT(DISTINCT rf.res_id) AS total_orders,
            COALESCE(SUM({$qtyExpr}), 0) AS total_quantity,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS total_revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$where}
    ";
    $stmt = $pdo->prepare($summarySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $summaryRow = $stmt->fetch() ?: ['total_orders' => 0, 'total_quantity' => 0, 'total_revenue' => 0];

    $totalRevenue = analyticsMoney($summaryRow['total_revenue']);

    $countSql = "
        SELECT COUNT(*) AS total_rows
        FROM (
            SELECT fd.food_id
            FROM RESERVATION_FOOD rf
            JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
            JOIN RESERVATION res ON res.res_id = rf.res_id
            WHERE {$where}
            GROUP BY fd.food_id
        ) ranked
    ";
    $stmt = $pdo->prepare($countSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $totalRows = (int)($stmt->fetch()['total_rows'] ?? 0);

    $limit = max(1, (int)$filters['per_page']);
    $offset = max(0, ((int)$filters['page'] - 1) * $limit);

    $topSql = "
        SELECT
            fd.food_id,
            {$foodTitleExpr} AS title,
            {$foodTypeExpr} AS food_type,
            {$categoryExpr} AS menu_category,
            COALESCE(SUM({$qtyExpr}), 0) AS quantity_sold,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$where}
        GROUP BY fd.food_id, title, food_type, menu_category
        ORDER BY quantity_sold DESC, revenue DESC, title ASC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($topSql);
    analyticsBindParams($stmt, $params);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $topDishes = $stmt->fetchAll();

    foreach ($topDishes as $idx => &$dish) {
        $dish['rank'] = $offset + $idx + 1;
        $dish['quantity_sold'] = (int)$dish['quantity_sold'];
        $dish['revenue'] = analyticsMoney($dish['revenue']);
        $dish['revenue_pct'] = $totalRevenue > 0 ? round(($dish['revenue'] / $totalRevenue) * 100, 2) : 0;
    }
    unset($dish);

    $categorySql = "
        SELECT
            {$categoryExpr} AS category_name,
            COUNT(DISTINCT rf.res_id) AS total_orders,
            COALESCE(SUM({$qtyExpr}), 0) AS quantity_sold,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$where}
        GROUP BY category_name
        ORDER BY revenue DESC
    ";
    $stmt = $pdo->prepare($categorySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $categoryRows = $stmt->fetchAll();

    $orderedCategories = ['Starters', 'Soups', 'Main Course', 'Breads', 'Rice', 'Desserts', 'Beverages', 'Combos'];
    $categoryMap = [];
    foreach ($orderedCategories as $categoryName) {
        $categoryMap[$categoryName] = [
            'category' => $categoryName,
            'total_orders' => 0,
            'quantity_sold' => 0,
            'revenue' => 0.0
        ];
    }
    foreach ($categoryRows as $row) {
        $key = trim((string)$row['category_name']);
        if (!isset($categoryMap[$key])) {
            $categoryMap[$key] = [
                'category' => $key,
                'total_orders' => 0,
                'quantity_sold' => 0,
                'revenue' => 0.0
            ];
        }
        $categoryMap[$key]['total_orders'] = (int)$row['total_orders'];
        $categoryMap[$key]['quantity_sold'] = (int)$row['quantity_sold'];
        $categoryMap[$key]['revenue'] = analyticsMoney($row['revenue']);
    }
    $categoryPerformance = array_values($categoryMap);

    $vegSql = "
        SELECT
            {$foodTypeExpr} AS food_type,
            COALESCE(SUM({$qtyExpr}), 0) AS quantity_sold,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$where}
        GROUP BY food_type
    ";
    $stmt = $pdo->prepare($vegSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $vegRows = $stmt->fetchAll();

    $vegSplit = [
        'VEG' => ['quantity_sold' => 0, 'revenue' => 0.0],
        'NON-VEG' => ['quantity_sold' => 0, 'revenue' => 0.0]
    ];
    foreach ($vegRows as $row) {
        $type = strtoupper((string)$row['food_type']) === 'NON-VEG' ? 'NON-VEG' : 'VEG';
        $vegSplit[$type]['quantity_sold'] = (int)$row['quantity_sold'];
        $vegSplit[$type]['revenue'] = analyticsMoney($row['revenue']);
    }

    $totalVegQty = $vegSplit['VEG']['quantity_sold'] + $vegSplit['NON-VEG']['quantity_sold'];

    $peakHourSql = "
        SELECT
            HOUR({$dateTimeExpr}) AS hour_of_day,
            COUNT(DISTINCT rf.res_id) AS order_count,
            COALESCE(SUM({$qtyExpr}), 0) AS quantity_sold
        FROM RESERVATION_FOOD rf
        JOIN RESERVATION res ON res.res_id = rf.res_id
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        WHERE {$where}
        GROUP BY hour_of_day
        ORDER BY hour_of_day
    ";
    $stmt = $pdo->prepare($peakHourSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $hourRows = $stmt->fetchAll();

    $hourly = array_fill(0, 24, 0);
    foreach ($hourRows as $row) {
        $hour = (int)$row['hour_of_day'];
        if ($hour >= 0 && $hour <= 23) {
            $hourly[$hour] = (int)$row['order_count'];
        }
    }

    $daySql = "
        SELECT
            DAYOFWEEK({$dateTimeExpr}) AS weekday_no,
            COUNT(DISTINCT rf.res_id) AS order_count
        FROM RESERVATION_FOOD rf
        JOIN RESERVATION res ON res.res_id = rf.res_id
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        WHERE {$where}
        GROUP BY weekday_no
        ORDER BY weekday_no
    ";
    $stmt = $pdo->prepare($daySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $dayRows = $stmt->fetchAll();

    $weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $weekdayData = array_fill(0, 7, 0);
    foreach ($dayRows as $row) {
        $idx = ((int)$row['weekday_no']) - 1;
        if ($idx >= 0 && $idx < 7) {
            $weekdayData[$idx] = (int)$row['order_count'];
        }
    }

    $peakHourValue = max($hourly);
    $peakHour = $peakHourValue > 0 ? array_search($peakHourValue, $hourly, true) : null;
    $peakDayValue = max($weekdayData);
    $peakDayIndex = $peakDayValue > 0 ? array_search($peakDayValue, $weekdayData, true) : null;

    return [
        'summary' => [
            'total_orders' => (int)$summaryRow['total_orders'],
            'total_quantity' => (int)$summaryRow['total_quantity'],
            'total_revenue' => $totalRevenue,
            'veg_order_pct' => $totalVegQty > 0 ? round(($vegSplit['VEG']['quantity_sold'] / $totalVegQty) * 100, 2) : 0,
            'non_veg_order_pct' => $totalVegQty > 0 ? round(($vegSplit['NON-VEG']['quantity_sold'] / $totalVegQty) * 100, 2) : 0,
            'peak_hour' => $peakHour,
            'peak_day' => $peakDayIndex !== null ? $weekdayLabels[$peakDayIndex] : 'N/A'
        ],
        'top_dishes' => $topDishes,
        'pagination' => [
            'page' => (int)$filters['page'],
            'per_page' => $limit,
            'total_rows' => $totalRows,
            'total_pages' => $limit > 0 ? (int)ceil($totalRows / $limit) : 1
        ],
        'category_performance' => $categoryPerformance,
        'veg_split' => $vegSplit,
        'peak_dining_time' => [
            'hourly_orders' => $hourly,
            'weekday_labels' => $weekdayLabels,
            'weekday_orders' => $weekdayData
        ],
        'chart' => [
            'top_labels' => array_map(fn($row) => $row['title'], $topDishes),
            'top_qty' => array_map(fn($row) => (int)$row['quantity_sold'], $topDishes),
            'top_revenue' => array_map(fn($row) => analyticsMoney($row['revenue']), $topDishes),
            'category_labels' => array_map(fn($row) => $row['category'], $categoryPerformance),
            'category_orders' => array_map(fn($row) => (int)$row['total_orders'], $categoryPerformance),
            'category_revenue' => array_map(fn($row) => analyticsMoney($row['revenue']), $categoryPerformance),
            'veg_labels' => ['VEG', 'NON-VEG'],
            'veg_qty' => [(int)$vegSplit['VEG']['quantity_sold'], (int)$vegSplit['NON-VEG']['quantity_sold']],
            'veg_revenue' => [analyticsMoney($vegSplit['VEG']['revenue']), analyticsMoney($vegSplit['NON-VEG']['revenue'])],
            'hour_labels' => array_map(fn($hour) => sprintf('%02d:00', $hour), range(0, 23)),
            'hour_orders' => $hourly,
            'weekday_labels' => $weekdayLabels,
            'weekday_orders' => $weekdayData
        ],
        'empty' => ((int)$summaryRow['total_orders'] === 0)
    ];
}

function analyticsRoomData(PDO $pdo, $filters) {
    $schema = analyticsGetSchema($pdo);
    $dateExpr = $schema['reservation_date_expr'];

    $totalRoomsStmt = $pdo->query("SELECT COUNT(*) AS total_rooms FROM ROOMS");
    $totalRooms = (int)($totalRoomsStmt->fetch()['total_rooms'] ?? 0);

    $where = "res.status IN ('Pending', 'Confirmed', 'Checked-in', 'Checked-out')
              AND {$dateExpr} BETWEEN :from_date AND :to_date";
    $params = [
        ':from_date' => $filters['from_date'],
        ':to_date' => $filters['to_date']
    ];

    $periodDays = max(1, (int)((new DateTimeImmutable($filters['to_date']))->diff(new DateTimeImmutable($filters['from_date']))->days + 1));

    $roomTypeSql = "
        SELECT
            rt.name AS room_type,
            COUNT(res.res_id) AS total_bookings,
            COALESCE(SUM(GREATEST(DATEDIFF(res.check_out, res.check_in), 1)), 0) AS room_nights,
            COALESCE(SUM(res.total_price), 0) AS revenue,
            COUNT(DISTINCT r.room_id) AS room_count
        FROM ROOM_TYPE rt
        LEFT JOIN ROOMS r ON r.room_type_id = rt.room_type_id
        LEFT JOIN RESERVATION res
            ON res.room_id = r.room_id
           AND {$where}
        GROUP BY rt.room_type_id, rt.name
        ORDER BY total_bookings DESC, revenue DESC, rt.name ASC
    ";
    $stmt = $pdo->prepare($roomTypeSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $roomTypeRows = $stmt->fetchAll();

    $roomTypePerformance = [];
    foreach ($roomTypeRows as $row) {
        $roomCount = max(1, (int)$row['room_count']);
        $roomNights = (int)$row['room_nights'];
        $occupancyRate = round(($roomNights / ($roomCount * $periodDays)) * 100, 2);
        $roomTypePerformance[] = [
            'room_type' => $row['room_type'],
            'total_bookings' => (int)$row['total_bookings'],
            'room_nights' => $roomNights,
            'revenue' => analyticsMoney($row['revenue']),
            'occupancy_rate' => min(100, $occupancyRate)
        ];
    }

    $floorExprRooms = analyticsFloorExpression('r');
    $floorInventorySql = "
        SELECT
            {$floorExprRooms} AS floor_no,
            COUNT(*) AS total_rooms,
            SUM(CASE WHEN r.status = 'Available' THEN 1 ELSE 0 END) AS available_rooms,
            SUM(CASE WHEN r.status IN ('Reserved', 'Occupied') THEN 1 ELSE 0 END) AS booked_rooms
        FROM ROOMS r
        GROUP BY floor_no
        HAVING floor_no BETWEEN 1 AND 5
        ORDER BY floor_no
    ";
    $floorInventoryRows = $pdo->query($floorInventorySql)->fetchAll();

    $floorExprReservations = analyticsFloorExpression('r');
    $floorBookingSql = "
        SELECT
            {$floorExprReservations} AS floor_no,
            COUNT(res.res_id) AS booking_count,
            COALESCE(SUM(res.total_price), 0) AS revenue
        FROM RESERVATION res
        JOIN ROOMS r ON r.room_id = res.room_id
        WHERE {$where}
        GROUP BY floor_no
        HAVING floor_no BETWEEN 1 AND 5
    ";
    $stmt = $pdo->prepare($floorBookingSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $floorBookingRows = $stmt->fetchAll();

    $floorMap = [];
    for ($floor = 1; $floor <= 5; $floor++) {
        $floorMap[$floor] = [
            'floor' => $floor,
            'wing_label' => analyticsFloorWingLabel($floor),
            'total_rooms' => 0,
            'available_rooms' => 0,
            'booked_rooms' => 0,
            'booking_count' => 0,
            'revenue' => 0.0,
            'utilization_pct' => 0.0
        ];
    }

    foreach ($floorInventoryRows as $row) {
        $floor = (int)$row['floor_no'];
        if (!isset($floorMap[$floor])) {
            continue;
        }
        $floorMap[$floor]['total_rooms'] = (int)$row['total_rooms'];
        $floorMap[$floor]['available_rooms'] = (int)$row['available_rooms'];
        $floorMap[$floor]['booked_rooms'] = (int)$row['booked_rooms'];
    }

    foreach ($floorBookingRows as $row) {
        $floor = (int)$row['floor_no'];
        if (!isset($floorMap[$floor])) {
            continue;
        }
        $floorMap[$floor]['booking_count'] = (int)$row['booking_count'];
        $floorMap[$floor]['revenue'] = analyticsMoney($row['revenue']);
    }

    foreach ($floorMap as &$floorRow) {
        if ($floorRow['total_rooms'] > 0) {
            $floorRow['utilization_pct'] = round(($floorRow['booked_rooms'] / $floorRow['total_rooms']) * 100, 2);
        }
    }
    unset($floorRow);

    $monthlySql = "
        SELECT
            DATE_FORMAT(res.check_in, '%Y-%m') AS month_key,
            COUNT(res.res_id) AS booking_count,
            COALESCE(SUM(GREATEST(DATEDIFF(res.check_out, res.check_in), 1)), 0) AS room_nights
        FROM RESERVATION res
        WHERE {$where}
        GROUP BY month_key
        ORDER BY month_key
    ";
    $stmt = $pdo->prepare($monthlySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $monthlyRows = $stmt->fetchAll();

    $monthSeries = analyticsSeriesMonths($filters['from_date'], $filters['to_date']);
    foreach ($monthSeries as $monthKey => &$monthRow) {
        $monthRow['booking_count'] = 0;
        $monthRow['room_nights'] = 0;
        $monthRow['occupancy_pct'] = 0;
    }
    unset($monthRow);

    foreach ($monthlyRows as $row) {
        $monthKey = (string)$row['month_key'];
        if (!isset($monthSeries[$monthKey])) {
            continue;
        }
        $monthSeries[$monthKey]['booking_count'] = (int)$row['booking_count'];
        $monthSeries[$monthKey]['room_nights'] = (int)$row['room_nights'];
    }

    foreach ($monthSeries as $monthKey => &$monthRow) {
        $monthDate = DateTimeImmutable::createFromFormat('Y-m-d', $monthKey . '-01');
        $daysInMonth = $monthDate ? (int)$monthDate->format('t') : 30;
        $denominator = max(1, $totalRooms * $daysInMonth);
        $monthRow['occupancy_pct'] = round(($monthRow['room_nights'] / $denominator) * 100, 2);
    }
    unset($monthRow);

    $yearlySql = "
        SELECT
            YEAR(res.check_in) AS year_key,
            COUNT(res.res_id) AS booking_count,
            COALESCE(SUM(GREATEST(DATEDIFF(res.check_out, res.check_in), 1)), 0) AS room_nights
        FROM RESERVATION res
        WHERE {$where}
        GROUP BY year_key
        ORDER BY year_key
    ";
    $stmt = $pdo->prepare($yearlySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $yearlyRows = $stmt->fetchAll();

    $yearlyComparison = [];
    foreach ($yearlyRows as $row) {
        $year = (int)$row['year_key'];
        $daysInYear = (($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0)) ? 366 : 365;
        $denominator = max(1, $totalRooms * $daysInYear);
        $yearlyComparison[] = [
            'year' => $year,
            'booking_count' => (int)$row['booking_count'],
            'room_nights' => (int)$row['room_nights'],
            'occupancy_pct' => round((((int)$row['room_nights']) / $denominator) * 100, 2)
        ];
    }

    $avgStaySql = "
        SELECT
            COUNT(*) AS total_bookings,
            COALESCE(AVG(GREATEST(DATEDIFF(res.check_out, res.check_in), 1)), 0) AS avg_stay_nights
        FROM RESERVATION res
        WHERE {$where}
    ";
    $stmt = $pdo->prepare($avgStaySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $avgStayRow = $stmt->fetch() ?: ['total_bookings' => 0, 'avg_stay_nights' => 0];

    $repeatStaySql = "
        SELECT
            COALESCE(AVG(repeat_data.avg_nights), 0) AS repeat_avg_nights
        FROM (
            SELECT
                res.guest_id,
                AVG(GREATEST(DATEDIFF(res.check_out, res.check_in), 1)) AS avg_nights,
                COUNT(*) AS booking_count
            FROM RESERVATION res
            WHERE {$where}
            GROUP BY res.guest_id
            HAVING COUNT(*) > 1
        ) repeat_data
    ";
    $stmt = $pdo->prepare($repeatStaySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $repeatStayRow = $stmt->fetch() ?: ['repeat_avg_nights' => 0];

    $topRoomType = !empty($roomTypePerformance) ? $roomTypePerformance[0]['room_type'] : 'N/A';
    $bestFloorRow = array_reduce(array_values($floorMap), function ($carry, $row) {
        if ($carry === null || $row['booking_count'] > $carry['booking_count']) {
            return $row;
        }
        return $carry;
    }, null);

    return [
        'summary' => [
            'total_rooms' => $totalRooms,
            'total_bookings' => (int)$avgStayRow['total_bookings'],
            'avg_stay_nights' => round((float)$avgStayRow['avg_stay_nights'], 2),
            'repeat_avg_stay_nights' => round((float)$repeatStayRow['repeat_avg_nights'], 2),
            'most_booked_room_type' => $topRoomType,
            'best_performing_floor' => $bestFloorRow ? analyticsFloorWingLabel($bestFloorRow['floor']) : 'N/A'
        ],
        'room_type_performance' => $roomTypePerformance,
        'floor_utilization' => array_values($floorMap),
        'occupancy_trends' => array_values($monthSeries),
        'yearly_comparison' => $yearlyComparison,
        'chart' => [
            'room_type_labels' => array_map(fn($row) => $row['room_type'], $roomTypePerformance),
            'room_type_bookings' => array_map(fn($row) => (int)$row['total_bookings'], $roomTypePerformance),
            'room_type_revenue' => array_map(fn($row) => analyticsMoney($row['revenue']), $roomTypePerformance),
            'room_type_occupancy' => array_map(fn($row) => (float)$row['occupancy_rate'], $roomTypePerformance),
            'floor_labels' => array_map(fn($row) => analyticsFloorWingLabel($row['floor']), array_values($floorMap)),
            'floor_available' => array_map(fn($row) => (int)$row['available_rooms'], array_values($floorMap)),
            'floor_booked' => array_map(fn($row) => (int)$row['booked_rooms'], array_values($floorMap)),
            'floor_bookings' => array_map(fn($row) => (int)$row['booking_count'], array_values($floorMap)),
            'occupancy_month_labels' => array_map(fn($row) => $row['month_label'], array_values($monthSeries)),
            'occupancy_month_values' => array_map(fn($row) => (float)$row['occupancy_pct'], array_values($monthSeries)),
            'year_labels' => array_map(fn($row) => (string)$row['year'], $yearlyComparison),
            'year_occupancy' => array_map(fn($row) => (float)$row['occupancy_pct'], $yearlyComparison)
        ],
        'empty' => ((int)$avgStayRow['total_bookings'] === 0)
    ];
}

function analyticsMatchSeason($mmdd, $seasonRows) {
    foreach ($seasonRows as $season) {
        $start = (string)$season['start_mmdd'];
        $end = (string)$season['end_mmdd'];

        if ($start <= $end) {
            if ($mmdd >= $start && $mmdd <= $end) {
                return $season;
            }
        } else {
            if ($mmdd >= $start || $mmdd <= $end) {
                return $season;
            }
        }
    }

    return null;
}

function analyticsFinancialData(PDO $pdo, $filters) {
    $schema = analyticsGetSchema($pdo);
    $dateExpr = $schema['reservation_date_expr'];
    $qtyExpr = $schema['reservation_food_qty_expr'];
    $foodTitleExpr = $schema['food_title_expr'];

    $params = [
        ':from_date' => $filters['from_date'],
        ':to_date' => $filters['to_date']
    ];

    $completedWhere = "res.status IN ('Confirmed', 'Checked-in', 'Checked-out')
                       AND {$dateExpr} BETWEEN :from_date AND :to_date";

    $reservationSummarySql = "
        SELECT
            COUNT(*) AS total_bookings,
            COALESCE(SUM(res.total_price), 0) AS reservation_revenue
        FROM RESERVATION res
        WHERE {$completedWhere}
    ";
    $stmt = $pdo->prepare($reservationSummarySql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $reservationSummary = $stmt->fetch() ?: ['total_bookings' => 0, 'reservation_revenue' => 0];

    $foodRevenueSql = "
        SELECT
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS food_revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$completedWhere}
    ";
    $stmt = $pdo->prepare($foodRevenueSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $foodRevenue = analyticsMoney($stmt->fetch()['food_revenue'] ?? 0);

    $paymentRevenue = 0.0;
    if ($schema['has_payments']) {
        $paymentDateExpr = analyticsHasColumn($pdo, 'PAYMENTS', 'created_at')
            ? 'DATE(p.created_at)'
            : $dateExpr;

        $paymentSql = "
            SELECT COALESCE(SUM(p.amount), 0) AS payment_revenue
            FROM PAYMENTS p
            LEFT JOIN RESERVATION res ON res.res_id = p.res_id
            WHERE {$paymentDateExpr} BETWEEN :from_date AND :to_date
              AND LOWER(COALESCE(p.status, '')) IN ('success', 'completed', 'paid')
        ";
        $stmt = $pdo->prepare($paymentSql);
        analyticsBindParams($stmt, $params);
        $stmt->execute();
        $paymentRevenue = analyticsMoney($stmt->fetch()['payment_revenue'] ?? 0);
    }

    $reservationRevenue = analyticsMoney($reservationSummary['reservation_revenue']);
    $roomsRevenue = max(0, analyticsMoney($reservationRevenue - $foodRevenue));
    $otherRevenue = max(0, analyticsMoney($paymentRevenue - $reservationRevenue));

    $costMonthlyRows = [];
    $totalCost = 0.0;
    if ($schema['has_operating_costs']) {
        $costSql = "
            SELECT
                DATE_FORMAT(cost_month, '%Y-%m') AS month_key,
                COALESCE(SUM(amount), 0) AS total_cost,
                COALESCE(SUM(CASE WHEN category IN ('Staff', 'Maintenance') THEN amount ELSE 0 END), 0) AS fixed_cost,
                COALESCE(SUM(CASE WHEN category IN ('Electricity', 'Water') THEN amount ELSE 0 END), 0) AS variable_cost
            FROM OPERATING_COSTS
            WHERE DATE(cost_month) BETWEEN :from_date AND :to_date
            GROUP BY month_key
            ORDER BY month_key
        ";
        $stmt = $pdo->prepare($costSql);
        analyticsBindParams($stmt, $params);
        $stmt->execute();
        $costMonthlyRows = $stmt->fetchAll();

        $totalCostSql = "
            SELECT COALESCE(SUM(amount), 0) AS total_cost
            FROM OPERATING_COSTS
            WHERE DATE(cost_month) BETWEEN :from_date AND :to_date
        ";
        $stmt = $pdo->prepare($totalCostSql);
        analyticsBindParams($stmt, $params);
        $stmt->execute();
        $totalCost = analyticsMoney($stmt->fetch()['total_cost'] ?? 0);
    }

    $monthSeries = analyticsSeriesMonths($filters['from_date'], $filters['to_date']);
    foreach ($monthSeries as &$row) {
        $row['bookings'] = 0;
        $row['total_revenue'] = 0.0;
        $row['room_revenue'] = 0.0;
        $row['food_revenue'] = 0.0;
        $row['fixed_cost'] = 0.0;
        $row['variable_cost'] = 0.0;
        $row['total_cost'] = 0.0;
        $row['net_profit'] = 0.0;
    }
    unset($row);

    $monthlyRevenueSql = "
        SELECT
            DATE_FORMAT({$dateExpr}, '%Y-%m') AS month_key,
            COUNT(*) AS bookings,
            COALESCE(SUM(res.total_price), 0) AS total_revenue
        FROM RESERVATION res
        WHERE {$completedWhere}
        GROUP BY month_key
        ORDER BY month_key
    ";
    $stmt = $pdo->prepare($monthlyRevenueSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $monthlyRevenueRows = $stmt->fetchAll();

    foreach ($monthlyRevenueRows as $row) {
        $key = (string)$row['month_key'];
        if (!isset($monthSeries[$key])) {
            continue;
        }
        $monthSeries[$key]['bookings'] = (int)$row['bookings'];
        $monthSeries[$key]['total_revenue'] = analyticsMoney($row['total_revenue']);
    }

    $monthlyFoodSql = "
        SELECT
            DATE_FORMAT({$dateExpr}, '%Y-%m') AS month_key,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS food_revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$completedWhere}
        GROUP BY month_key
        ORDER BY month_key
    ";
    $stmt = $pdo->prepare($monthlyFoodSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $monthlyFoodRows = $stmt->fetchAll();

    foreach ($monthlyFoodRows as $row) {
        $key = (string)$row['month_key'];
        if (!isset($monthSeries[$key])) {
            continue;
        }
        $monthSeries[$key]['food_revenue'] = analyticsMoney($row['food_revenue']);
    }

    foreach ($costMonthlyRows as $row) {
        $key = (string)$row['month_key'];
        if (!isset($monthSeries[$key])) {
            continue;
        }
        $monthSeries[$key]['fixed_cost'] = analyticsMoney($row['fixed_cost']);
        $monthSeries[$key]['variable_cost'] = analyticsMoney($row['variable_cost']);
        $monthSeries[$key]['total_cost'] = analyticsMoney($row['total_cost']);
    }

    foreach ($monthSeries as &$row) {
        $row['room_revenue'] = max(0, analyticsMoney($row['total_revenue'] - $row['food_revenue']));
        $row['net_profit'] = analyticsMoney($row['total_revenue'] - $row['total_cost']);
    }
    unset($row);

    $roomDriverSql = "
        SELECT
            rt.name AS room_type,
            COUNT(res.res_id) AS booking_count,
            COALESCE(SUM(res.total_price), 0) AS revenue
        FROM RESERVATION res
        JOIN ROOMS r ON r.room_id = res.room_id
        JOIN ROOM_TYPE rt ON rt.room_type_id = r.room_type_id
        WHERE {$completedWhere}
        GROUP BY rt.room_type_id, rt.name
        ORDER BY revenue DESC, booking_count DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($roomDriverSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $roomDrivers = $stmt->fetchAll();

    foreach ($roomDrivers as &$driver) {
        $driver['booking_count'] = (int)$driver['booking_count'];
        $driver['revenue'] = analyticsMoney($driver['revenue']);
    }
    unset($driver);

    $foodDriverSql = "
        SELECT
            {$foodTitleExpr} AS title,
            COALESCE(SUM({$qtyExpr}), 0) AS quantity_sold,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$completedWhere}
        GROUP BY fd.food_id, title
        ORDER BY revenue DESC, quantity_sold DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($foodDriverSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $foodDrivers = $stmt->fetchAll();

    foreach ($foodDrivers as &$driver) {
        $driver['quantity_sold'] = (int)$driver['quantity_sold'];
        $driver['revenue'] = analyticsMoney($driver['revenue']);
    }
    unset($driver);

    $seasonPerformance = [];
    if ($schema['has_pricing_seasons']) {
        $seasonStmt = $pdo->query("SELECT name, start_mmdd, end_mmdd, multiplier FROM pricing_seasons WHERE is_active = 1 ORDER BY priority DESC, multiplier DESC");
        $seasonRows = $seasonStmt->fetchAll();

        $resStmt = $pdo->prepare("SELECT check_in, total_price FROM RESERVATION res WHERE {$completedWhere}");
        analyticsBindParams($resStmt, $params);
        $resStmt->execute();
        $resRows = $resStmt->fetchAll();

        $seasonBuckets = [];
        foreach ($resRows as $row) {
            $checkIn = (string)$row['check_in'];
            $mmdd = date('m-d', strtotime($checkIn));
            $season = analyticsMatchSeason($mmdd, $seasonRows);
            $seasonName = $season ? (string)$season['name'] : 'Regular';

            if (!isset($seasonBuckets[$seasonName])) {
                $seasonBuckets[$seasonName] = [
                    'season' => $seasonName,
                    'booking_count' => 0,
                    'revenue' => 0.0
                ];
            }

            $seasonBuckets[$seasonName]['booking_count'] += 1;
            $seasonBuckets[$seasonName]['revenue'] += (float)$row['total_price'];
        }

        foreach ($seasonBuckets as $bucket) {
            $seasonPerformance[] = [
                'season' => $bucket['season'],
                'booking_count' => (int)$bucket['booking_count'],
                'revenue' => analyticsMoney($bucket['revenue'])
            ];
        }

        usort($seasonPerformance, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
    }

    if (empty($seasonPerformance)) {
        $fallbackSeasonSql = "
            SELECT
                CASE
                    WHEN MONTH(res.check_in) IN (12, 1, 2) THEN 'Winter Peak'
                    WHEN MONTH(res.check_in) IN (3, 4) THEN 'Spring Shoulder'
                    WHEN MONTH(res.check_in) IN (5, 6) THEN 'Summer High Demand'
                    WHEN MONTH(res.check_in) IN (7, 8, 9) THEN 'Monsoon Value'
                    ELSE 'Festive Season'
                END AS season_name,
                COUNT(res.res_id) AS booking_count,
                COALESCE(SUM(res.total_price), 0) AS revenue
            FROM RESERVATION res
            WHERE {$completedWhere}
            GROUP BY season_name
            ORDER BY revenue DESC, booking_count DESC
        ";
        $stmt = $pdo->prepare($fallbackSeasonSql);
        analyticsBindParams($stmt, $params);
        $stmt->execute();
        $seasonRows = $stmt->fetchAll();

        foreach ($seasonRows as $row) {
            $seasonPerformance[] = [
                'season' => (string)$row['season_name'],
                'booking_count' => (int)$row['booking_count'],
                'revenue' => analyticsMoney($row['revenue'])
            ];
        }
    }

    $monthlyTrend = array_values($monthSeries);
    $historyRevenue = array_values(array_map(fn($row) => (float)$row['total_revenue'], $monthlyTrend));
    $historyRevenue = array_values(array_filter($historyRevenue, fn($value) => $value > 0));
    $windowRevenue = array_slice($historyRevenue, -6);

    $averageRevenue = !empty($windowRevenue) ? (array_sum($windowRevenue) / count($windowRevenue)) : 0;

    $forecast = [];
    $forecastStart = (new DateTimeImmutable($filters['to_date']))->modify('first day of next month');
    for ($i = 0; $i < 3; $i++) {
        $forecastMonth = $forecastStart->modify('+' . $i . ' month');
        $forecast[] = [
            'month_key' => $forecastMonth->format('Y-m'),
            'month_label' => $forecastMonth->format('M Y'),
            'predicted_revenue' => analyticsMoney($averageRevenue)
        ];
    }

    $netProfit = analyticsMoney($reservationRevenue - $totalCost);

    $roomTypeTop = !empty($roomDrivers) ? $roomDrivers[0]['room_type'] : 'N/A';
    $foodItemTop = !empty($foodDrivers) ? $foodDrivers[0]['title'] : 'N/A';
    $seasonTop = !empty($seasonPerformance) ? $seasonPerformance[0]['season'] : 'Regular';

    return [
        'summary' => [
            'total_bookings' => (int)$reservationSummary['total_bookings'],
            'reservation_revenue' => $reservationRevenue,
            'room_revenue' => $roomsRevenue,
            'food_revenue' => $foodRevenue,
            'other_revenue' => $otherRevenue,
            'payment_revenue' => $paymentRevenue,
            'total_cost' => $totalCost,
            'net_profit' => $netProfit,
            'profit_margin_pct' => $reservationRevenue > 0 ? round(($netProfit / $reservationRevenue) * 100, 2) : 0,
            'top_room_profit_driver' => $roomTypeTop,
            'top_food_profit_driver' => $foodItemTop,
            'top_season_profit_driver' => $seasonTop
        ],
        'monthly_trend' => $monthlyTrend,
        'forecast' => $forecast,
        'profit_drivers' => [
            'room_types' => $roomDrivers,
            'food_items' => $foodDrivers,
            'seasons' => $seasonPerformance
        ],
        'chart' => [
            'monthly_labels' => array_map(fn($row) => $row['month_label'], $monthlyTrend),
            'monthly_revenue' => array_map(fn($row) => analyticsMoney($row['total_revenue']), $monthlyTrend),
            'monthly_fixed_cost' => array_map(fn($row) => analyticsMoney($row['fixed_cost']), $monthlyTrend),
            'monthly_variable_cost' => array_map(fn($row) => analyticsMoney($row['variable_cost']), $monthlyTrend),
            'monthly_total_cost' => array_map(fn($row) => analyticsMoney($row['total_cost']), $monthlyTrend),
            'monthly_profit' => array_map(fn($row) => analyticsMoney($row['net_profit']), $monthlyTrend),
            'revenue_breakdown_labels' => ['Rooms', 'Food', 'Other Services'],
            'revenue_breakdown_values' => [$roomsRevenue, $foodRevenue, $otherRevenue],
            'forecast_labels' => array_map(fn($row) => $row['month_label'], $forecast),
            'forecast_values' => array_map(fn($row) => analyticsMoney($row['predicted_revenue']), $forecast),
            'driver_room_labels' => array_map(fn($row) => $row['room_type'], $roomDrivers),
            'driver_room_revenue' => array_map(fn($row) => analyticsMoney($row['revenue']), $roomDrivers),
            'driver_food_labels' => array_map(fn($row) => $row['title'], $foodDrivers),
            'driver_food_revenue' => array_map(fn($row) => analyticsMoney($row['revenue']), $foodDrivers),
            'driver_season_labels' => array_map(fn($row) => $row['season'], $seasonPerformance),
            'driver_season_revenue' => array_map(fn($row) => analyticsMoney($row['revenue']), $seasonPerformance)
        ],
        'empty' => ((int)$reservationSummary['total_bookings'] === 0)
    ];
}

function analyticsQuarterlyRows($monthlyTrend) {
    $quarterMap = [];

    foreach ($monthlyTrend as $row) {
        $monthKey = (string)($row['month_key'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})$/', $monthKey, $matches)) {
            continue;
        }
        $year = (int)$matches[1];
        $month = (int)$matches[2];
        $quarter = (int)ceil($month / 3);
        $key = sprintf('%04d-Q%d', $year, $quarter);

        if (!isset($quarterMap[$key])) {
            $quarterMap[$key] = [
                'quarter' => $key,
                'bookings' => 0,
                'revenue' => 0.0,
                'cost' => 0.0,
                'profit' => 0.0
            ];
        }

        $quarterMap[$key]['bookings'] += (int)($row['bookings'] ?? 0);
        $quarterMap[$key]['revenue'] += (float)($row['total_revenue'] ?? 0);
        $quarterMap[$key]['cost'] += (float)($row['total_cost'] ?? 0);
        $quarterMap[$key]['profit'] += (float)($row['net_profit'] ?? 0);
    }

    foreach ($quarterMap as &$row) {
        $row['revenue'] = analyticsMoney($row['revenue']);
        $row['cost'] = analyticsMoney($row['cost']);
        $row['profit'] = analyticsMoney($row['profit']);
    }
    unset($row);

    return array_values($quarterMap);
}

function analyticsBuildMasterStats($foodData, $roomData, $financialData) {
    $topFood = (!empty($foodData['top_dishes']) && !empty($foodData['top_dishes'][0]['title']))
        ? (string)$foodData['top_dishes'][0]['title']
        : 'N/A';

    $topRoomType = (!empty($roomData['room_type_performance']) && !empty($roomData['room_type_performance'][0]['room_type']))
        ? (string)$roomData['room_type_performance'][0]['room_type']
        : 'N/A';

    $highestRevenueMonthLabel = 'N/A';
    $highestRevenueMonthValue = 0.0;
    $peakBookingMonthLabel = 'N/A';
    $peakBookingCount = 0;

    foreach ($financialData['monthly_trend'] ?? [] as $row) {
        $monthLabel = (string)($row['month_label'] ?? ($row['month_key'] ?? 'N/A'));
        $combinedRevenue = (float)($row['room_revenue'] ?? 0) + (float)($row['food_revenue'] ?? 0);
        if ($combinedRevenue <= 0 && isset($row['total_revenue'])) {
            $combinedRevenue = (float)$row['total_revenue'];
        }

        if ($combinedRevenue > $highestRevenueMonthValue) {
            $highestRevenueMonthValue = $combinedRevenue;
            $highestRevenueMonthLabel = $monthLabel;
        }

        $bookings = (int)($row['bookings'] ?? 0);
        if ($bookings > $peakBookingCount) {
            $peakBookingCount = $bookings;
            $peakBookingMonthLabel = $monthLabel;
        }
    }

    $bestFloor = 'N/A';
    $bestFloorBookings = -1;
    foreach ($roomData['floor_utilization'] ?? [] as $floorRow) {
        if ((int)$floorRow['booking_count'] > $bestFloorBookings) {
            $bestFloorBookings = (int)$floorRow['booking_count'];
            $bestFloor = analyticsFloorWingLabel((int)$floorRow['floor']);
        }
    }

    $roomsRevenue = (float)($financialData['summary']['room_revenue'] ?? 0);
    $foodRevenue = (float)($financialData['summary']['food_revenue'] ?? 0);
    if ($roomsRevenue <= 0 && !empty($financialData['monthly_trend'])) {
        $roomsRevenue = array_sum(array_map(fn($row) => (float)($row['room_revenue'] ?? 0), $financialData['monthly_trend']));
    }
    if ($foodRevenue <= 0 && !empty($financialData['monthly_trend'])) {
        $foodRevenue = array_sum(array_map(fn($row) => (float)($row['food_revenue'] ?? 0), $financialData['monthly_trend']));
    }

    $profitCategory = 'N/A';
    if ($roomsRevenue > 0 || $foodRevenue > 0) {
        $profitCategory = $roomsRevenue >= $foodRevenue ? 'Rooms' : 'Food';
    }

    return [
        'top_food_item' => $topFood,
        'most_booked_room_type' => $topRoomType,
        'highest_revenue_month' => $highestRevenueMonthLabel,
        'highest_revenue_month_value' => analyticsMoney($highestRevenueMonthValue),
        'peak_booking_season' => $peakBookingMonthLabel,
        'best_performing_floor' => $bestFloor,
        'highest_profit_category' => $profitCategory
    ];
}

function analyticsSnapshot(PDO $pdo, $filters) {
    $normalized = analyticsNormalizeFilters($filters);
    $food = analyticsFoodData($pdo, $normalized);
    $rooms = analyticsRoomData($pdo, $normalized);
    $financial = analyticsFinancialData($pdo, $normalized);

    return [
        'filters' => $normalized,
        'food' => $food,
        'rooms' => $rooms,
        'financial' => $financial,
        'master' => analyticsBuildMasterStats($food, $rooms, $financial),
        'quarterly' => analyticsQuarterlyRows($financial['monthly_trend'] ?? [])
    ];
}

function analyticsPaginationMeta($page, $perPage, $totalRows) {
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage));
    $totalRows = max(0, (int)$totalRows);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}

function analyticsRecentReservations(PDO $pdo, $page = 1, $perPage = 10, $filters = []) {
    $schema = analyticsGetSchema($pdo);
    $normalizedFilters = analyticsNormalizeFilters($filters);
    $dateExpr = str_replace('res.', 'r.', $schema['reservation_date_expr']);
    $createdAtColumn = analyticsHasColumn($pdo, 'RESERVATION', 'created_at') ? 'r.created_at' : 'r.check_in';

    $perPage = max(5, min(50, (int)$perPage));
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $perPage;

    $allowedStatuses = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];
    $requestedStatus = isset($filters['status']) ? trim((string)$filters['status']) : '';
    if (!in_array($requestedStatus, $allowedStatuses, true)) {
        $requestedStatus = '';
    }

    $requestedRoomType = isset($filters['room_type']) ? trim((string)$filters['room_type']) : '';
    if (strtolower($requestedRoomType) === 'all') {
        $requestedRoomType = '';
    }

    $where = "{$dateExpr} BETWEEN :from_date AND :to_date";
    $params = [
        ':from_date' => $normalizedFilters['from_date'],
        ':to_date' => $normalizedFilters['to_date']
    ];

    if ($requestedStatus !== '') {
        $where .= " AND r.status = :status";
        $params[':status'] = $requestedStatus;
    }

    if ($requestedRoomType !== '') {
        $where .= " AND LOWER(COALESCE(rt.name, '')) = LOWER(:room_type)";
        $params[':room_type'] = $requestedRoomType;
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM RESERVATION r
        LEFT JOIN ROOMS rm ON rm.room_id = r.room_id
        LEFT JOIN ROOM_TYPE rt ON rt.room_type_id = rm.room_type_id
        WHERE {$where}
    ";
    $stmt = $pdo->prepare($countSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $totalRows = (int)($stmt->fetch()['total'] ?? 0);

    $sql = "
        SELECT
            r.res_id,
            g.name AS guest_name,
            g.email AS guest_email,
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
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    analyticsBindParams($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['total_price'] = analyticsMoney($row['total_price'] ?? 0);
    }
    unset($row);

    return [
        'rows' => $rows,
        'pagination' => analyticsPaginationMeta($page, $perPage, $totalRows)
    ];
}

function analyticsRecentFoodOrders(PDO $pdo, $page = 1, $perPage = 10, $filters = []) {
    $schema = analyticsGetSchema($pdo);
    $normalizedFilters = analyticsNormalizeFilters($filters);
    $qtyExpr = $schema['reservation_food_qty_expr'];
    $foodTitleExpr = $schema['food_title_expr'];
    $foodTypeExpr = $schema['food_type_expr'];
    $categoryExpr = $schema['food_category_expr'];
    $foodOrderPk = analyticsHasColumn($pdo, 'RESERVATION_FOOD', 'id')
        ? 'id'
        : (analyticsHasColumn($pdo, 'RESERVATION_FOOD', 'res_food_id') ? 'res_food_id' : 'id');
    $hasFoodOrderCreatedAt = analyticsHasColumn($pdo, 'RESERVATION_FOOD', 'created_at');
    $orderDateExpr = $hasFoodOrderCreatedAt ? 'DATE(rf.created_at)' : $schema['reservation_date_expr'];
    $orderDateTimeExpr = $hasFoodOrderCreatedAt ? 'rf.created_at' : $schema['reservation_datetime_expr'];

    $perPage = max(5, min(50, (int)$perPage));
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $perPage;

    $where = "{$orderDateExpr} BETWEEN :from_date AND :to_date";
    $params = [
        ':from_date' => $normalizedFilters['from_date'],
        ':to_date' => $normalizedFilters['to_date']
    ];

    if ($schema['has_food_category'] && $normalizedFilters['category'] !== 'all') {
        $where .= " AND {$categoryExpr} = :category";
        $params[':category'] = $normalizedFilters['category'];
    }

    $countSql = "
        SELECT COUNT(*) AS total
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        LEFT JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$where}
    ";
    $stmt = $pdo->prepare($countSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $totalRows = (int)($stmt->fetch()['total'] ?? 0);

    $sql = "
        SELECT
            rf.{$foodOrderPk} AS order_id,
            rf.res_id,
            {$foodTitleExpr} AS item_name,
            {$categoryExpr} AS menu_category,
            {$foodTypeExpr} AS food_type,
            {$qtyExpr} AS quantity,
            COALESCE(rf.price, fd.price, 0) AS unit_price,
            ({$qtyExpr} * COALESCE(rf.price, fd.price, 0)) AS line_total,
            {$orderDateTimeExpr} AS ordered_at
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        LEFT JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE {$where}
        ORDER BY ordered_at DESC, order_id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    analyticsBindParams($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['quantity'] = (int)$row['quantity'];
        $row['unit_price'] = analyticsMoney($row['unit_price'] ?? 0);
        $row['line_total'] = analyticsMoney($row['line_total'] ?? 0);
    }
    unset($row);

    return [
        'rows' => $rows,
        'pagination' => analyticsPaginationMeta($page, $perPage, $totalRows)
    ];
}

function analyticsRecentPayments(PDO $pdo, $page = 1, $perPage = 10, $filters = []) {
    if (!analyticsHasTable($pdo, 'PAYMENTS')) {
        return [
            'rows' => [],
            'pagination' => analyticsPaginationMeta(1, max(5, min(50, (int)$perPage)), 0)
        ];
    }

    $normalizedFilters = analyticsNormalizeFilters($filters);
    $perPage = max(5, min(50, (int)$perPage));
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $perPage;
    $paymentDateColumn = analyticsHasColumn($pdo, 'PAYMENTS', 'payment_date') ? 'payment_date' : 'created_at';
    $dateExpr = "DATE(p.{$paymentDateColumn})";

    $where = "{$dateExpr} BETWEEN :from_date AND :to_date";
    $params = [
        ':from_date' => $normalizedFilters['from_date'],
        ':to_date' => $normalizedFilters['to_date']
    ];

    $countSql = "SELECT COUNT(*) AS total FROM PAYMENTS p WHERE {$where}";
    $stmt = $pdo->prepare($countSql);
    analyticsBindParams($stmt, $params);
    $stmt->execute();
    $totalRows = (int)($stmt->fetch()['total'] ?? 0);

    $sql = "
        SELECT
            p.payment_id,
            p.res_id,
            COALESCE(g.name, 'Guest') AS guest_name,
            p.amount,
            p.status,
            p.payment_method,
            p.txn_id,
            p.{$paymentDateColumn} AS paid_at
        FROM PAYMENTS p
        LEFT JOIN RESERVATION r ON r.res_id = p.res_id
        LEFT JOIN GUEST g ON g.guest_id = r.guest_id
        WHERE {$where}
        ORDER BY p.{$paymentDateColumn} DESC, p.payment_id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    analyticsBindParams($stmt, $params);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['amount'] = analyticsMoney($row['amount'] ?? 0);
    }
    unset($row);

    return [
        'rows' => $rows,
        'pagination' => analyticsPaginationMeta($page, $perPage, $totalRows)
    ];
}

function analyticsRecentStaffActivities(PDO $pdo, $page = 1, $perPage = 10, $filters = []) {
    $normalizedFilters = analyticsNormalizeFilters($filters);
    $perPage = max(5, min(50, (int)$perPage));
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $perPage;

    $staffDeptColumn = analyticsHasColumn($pdo, 'STAFF', 'dept_id') ? 'dept_id' : 'dep_id';
    $staffRoleColumn = analyticsHasColumn($pdo, 'STAFF', 'role') ? 'role' : (analyticsHasColumn($pdo, 'STAFF', 'position') ? 'position' : 'name');
    $staffStatusExpr = analyticsHasColumn($pdo, 'STAFF', 'status') ? 's.status' : "'Active'";
    $deptPkColumn = analyticsHasColumn($pdo, 'DEPARTMENT', 'dept_id') ? 'dept_id' : 'dep_id';
    $deptNameColumn = analyticsHasColumn($pdo, 'DEPARTMENT', 'name') ? 'name' : 'dep_name';
    $params = [
        ':from_date' => $normalizedFilters['from_date'],
        ':to_date' => $normalizedFilters['to_date']
    ];

    if (analyticsHasTable($pdo, 'STAFF_ATTENDANCE')) {
        $countSql = "
            SELECT COUNT(*) AS total
            FROM STAFF_ATTENDANCE
            WHERE attendance_date BETWEEN :from_date AND :to_date
        ";
        $stmt = $pdo->prepare($countSql);
        analyticsBindParams($stmt, $params);
        $stmt->execute();
        $totalRows = (int)($stmt->fetch()['total'] ?? 0);

        $sql = "
            SELECT
                sa.attendance_id AS activity_id,
                s.staff_id,
                s.name AS staff_name,
                COALESCE(s.{$staffRoleColumn}, 'Staff') AS role_name,
                COALESCE(d.{$deptNameColumn}, 'General') AS department_name,
                sa.attendance_date AS activity_date,
                sa.status AS activity_status,
                sa.hours_worked,
                sa.notes
            FROM STAFF_ATTENDANCE sa
            JOIN STAFF s ON s.staff_id = sa.staff_id
            LEFT JOIN DEPARTMENT d ON d.{$deptPkColumn} = s.{$staffDeptColumn}
            WHERE sa.attendance_date BETWEEN :from_date AND :to_date
            ORDER BY sa.attendance_date DESC, sa.attendance_id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        analyticsBindParams($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } else {
        $activityDateExpr = analyticsHasColumn($pdo, 'STAFF', 'last_activity_at') ? 's.last_activity_at' : 's.hire_date';
        $countSql = "
            SELECT COUNT(*) AS total
            FROM STAFF s
            WHERE DATE({$activityDateExpr}) BETWEEN :from_date AND :to_date
        ";
        $stmt = $pdo->prepare($countSql);
        analyticsBindParams($stmt, $params);
        $stmt->execute();
        $totalRows = (int)($stmt->fetch()['total'] ?? 0);

        $sql = "
            SELECT
                s.staff_id AS activity_id,
                s.staff_id,
                s.name AS staff_name,
                COALESCE(s.{$staffRoleColumn}, 'Staff') AS role_name,
                COALESCE(d.{$deptNameColumn}, 'General') AS department_name,
                {$activityDateExpr} AS activity_date,
                COALESCE({$staffStatusExpr}, 'Active') AS activity_status,
                NULL AS hours_worked,
                'Staff profile activity' AS notes
            FROM STAFF s
            LEFT JOIN DEPARTMENT d ON d.{$deptPkColumn} = s.{$staffDeptColumn}
            WHERE DATE({$activityDateExpr}) BETWEEN :from_date AND :to_date
            ORDER BY {$activityDateExpr} DESC, s.staff_id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($sql);
        analyticsBindParams($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    }

    return [
        'rows' => $rows,
        'pagination' => analyticsPaginationMeta($page, $perPage, $totalRows)
    ];
}

function analyticsRecentActivityBundle(PDO $pdo, $input = []) {
    $perPage = isset($input['per_page']) ? (int)$input['per_page'] : 10;
    $perPage = max(5, min(50, $perPage));

    return [
        'reservations' => analyticsRecentReservations(
            $pdo,
            isset($input['reservations_page']) ? (int)$input['reservations_page'] : 1,
            $perPage,
            $input
        ),
        'food_orders' => analyticsRecentFoodOrders(
            $pdo,
            isset($input['food_page']) ? (int)$input['food_page'] : 1,
            $perPage,
            $input
        ),
        'payments' => analyticsRecentPayments(
            $pdo,
            isset($input['payments_page']) ? (int)$input['payments_page'] : 1,
            $perPage,
            $input
        ),
        'staff_activities' => analyticsRecentStaffActivities(
            $pdo,
            isset($input['staff_page']) ? (int)$input['staff_page'] : 1,
            $perPage,
            $input
        )
    ];
}

function analyticsCalculateIrr($cashFlows, $maxIterations = 120, $tolerance = 0.000001) {
    if (count($cashFlows) < 2) {
        return 0.0;
    }

    $rate = 0.1;
    for ($i = 0; $i < $maxIterations; $i++) {
        $npv = 0.0;
        $derivative = 0.0;

        foreach ($cashFlows as $period => $cashFlow) {
            $denominator = pow(1 + $rate, $period);
            if ($denominator == 0.0) {
                continue;
            }
            $npv += $cashFlow / $denominator;
            if ($period > 0) {
                $derivative -= ($period * $cashFlow) / pow(1 + $rate, $period + 1);
            }
        }

        if (abs($derivative) < $tolerance) {
            break;
        }

        $nextRate = $rate - ($npv / $derivative);
        if (abs($nextRate - $rate) < $tolerance) {
            $rate = $nextRate;
            break;
        }
        $rate = $nextRate;
    }

    if (!is_finite($rate) || $rate < -0.99 || $rate > 10) {
        return 0.0;
    }

    return round($rate * 100, 2);
}

function analyticsEconomicsAdvancedMetrics($snapshot, $input = []) {
    $financial = $snapshot['financial'] ?? [];
    $monthlyTrend = $financial['monthly_trend'] ?? [];
    $summary = $financial['summary'] ?? [];

    $initialInvestment = isset($input['initial_investment']) ? max(1.0, (float)$input['initial_investment']) : 2500000.0;
    $discountRateAnnual = isset($input['discount_rate']) ? max(0.0, (float)$input['discount_rate']) : 12.0;
    $discountRateMonthly = $discountRateAnnual / 12 / 100;

    $cashFlows = [-$initialInvestment];
    foreach ($monthlyTrend as $row) {
        $cashFlows[] = (float)($row['net_profit'] ?? 0);
    }

    $npv = 0.0;
    foreach ($cashFlows as $period => $cashFlow) {
        $npv += $cashFlow / pow(1 + $discountRateMonthly, $period);
    }

    $irr = analyticsCalculateIrr($cashFlows);
    $totalRevenue = (float)($summary['reservation_revenue'] ?? 0);
    $totalCost = (float)($summary['total_cost'] ?? 0);
    $costBenefitRatio = $totalCost > 0 ? round($totalRevenue / $totalCost, 3) : 0.0;

    $historyRevenue = array_values(array_map(fn($row) => (float)($row['total_revenue'] ?? 0), $monthlyTrend));
    $historyRevenue = array_values(array_filter($historyRevenue, fn($v) => $v > 0));
    $avgRevenue = !empty($historyRevenue) ? (array_sum(array_slice($historyRevenue, -6)) / min(6, count($historyRevenue))) : 0.0;

    $forecast = [];
    $baseMonth = new DateTimeImmutable('first day of next month');
    for ($i = 0; $i < 6; $i++) {
        $monthObj = $baseMonth->modify('+' . $i . ' month');
        $seasonalFactor = [1.06, 1.03, 1.0, 0.98, 1.01, 1.05][$i % 6];
        $predicted = analyticsMoney($avgRevenue * $seasonalFactor);
        $forecast[] = [
            'month_key' => $monthObj->format('Y-m'),
            'month_label' => $monthObj->format('M Y'),
            'predicted_revenue' => $predicted
        ];
    }

    $currentProfit = (float)($summary['net_profit'] ?? 0);
    $sensitivity = [
        'occupancy_minus_10' => analyticsMoney($currentProfit * 0.82),
        'occupancy_base' => analyticsMoney($currentProfit),
        'occupancy_plus_10' => analyticsMoney($currentProfit * 1.18)
    ];

    return [
        'initial_investment' => analyticsMoney($initialInvestment),
        'discount_rate_annual_pct' => round($discountRateAnnual, 2),
        'npv' => analyticsMoney($npv),
        'irr_pct' => $irr,
        'cost_benefit_ratio' => $costBenefitRatio,
        'forecast_6_months' => $forecast,
        'sensitivity_analysis' => $sensitivity
    ];
}

function analyticsBusinessInsights(PDO $pdo, $snapshot, $filters) {
    $financial = $snapshot['financial'] ?? [];
    $rooms = $snapshot['rooms'] ?? [];
    $food = $snapshot['food'] ?? [];
    $monthly = $financial['monthly_trend'] ?? [];
    $roomDrivers = $financial['profit_drivers']['room_types'] ?? [];

    $mostProfitableRoom = !empty($roomDrivers) ? (string)$roomDrivers[0]['room_type'] : 'N/A';

    $schema = analyticsGetSchema($pdo);
    $qtyExpr = $schema['reservation_food_qty_expr'];
    $foodTitleExpr = $schema['food_title_expr'];
    $dateExpr = $schema['reservation_date_expr'];
    $leastFoodSql = "
        SELECT
            {$foodTitleExpr} AS title,
            COALESCE(SUM({$qtyExpr} * COALESCE(rf.price, fd.price, 0)), 0) AS revenue
        FROM RESERVATION_FOOD rf
        JOIN FOOD_DINING fd ON fd.food_id = rf.food_id
        JOIN RESERVATION res ON res.res_id = rf.res_id
        WHERE res.status IN ('Confirmed', 'Checked-in', 'Checked-out')
          AND {$dateExpr} BETWEEN :from_date AND :to_date
        GROUP BY fd.food_id, title
        ORDER BY revenue ASC, title ASC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($leastFoodSql);
    analyticsBindParams($stmt, [
        ':from_date' => $filters['from_date'],
        ':to_date' => $filters['to_date']
    ]);
    $stmt->execute();
    $leastFoodRow = $stmt->fetch();
    $leastFood = $leastFoodRow ? (string)$leastFoodRow['title'] : 'N/A';

    $peakSeason = (string)(($financial['profit_drivers']['seasons'][0]['season'] ?? 'N/A'));

    $highestMonth = ['month_label' => 'N/A', 'booking_count' => -1];
    $lowestMonth = ['month_label' => 'N/A', 'booking_count' => PHP_INT_MAX];
    foreach ($rooms['occupancy_trends'] ?? [] as $row) {
        $count = (int)($row['booking_count'] ?? 0);
        if ($count > $highestMonth['booking_count']) {
            $highestMonth = ['month_label' => (string)$row['month_label'], 'booking_count' => $count];
        }
        if ($count < $lowestMonth['booking_count']) {
            $lowestMonth = ['month_label' => (string)$row['month_label'], 'booking_count' => $count];
        }
    }

    $occupancyValues = array_map(fn($r) => (float)($r['occupancy_pct'] ?? 0), $rooms['occupancy_trends'] ?? []);
    $avgOccupancy = !empty($occupancyValues) ? array_sum($occupancyValues) / count($occupancyValues) : 0.0;
    $priceAction = 'Hold pricing with periodic A/B testing';
    if ($avgOccupancy >= 78) {
        $priceAction = 'Increase dynamic base price by 7%-10% for high-demand windows';
    } elseif ($avgOccupancy <= 45) {
        $priceAction = 'Decrease base price by 5%-8% and push bundled offers';
    }

    $costDrivers = [];
    if (analyticsHasTable($pdo, 'OPERATING_COSTS')) {
        $costSql = "
            SELECT category, COALESCE(SUM(amount), 0) AS total_cost
            FROM OPERATING_COSTS
            WHERE DATE(cost_month) BETWEEN :from_date AND :to_date
            GROUP BY category
            ORDER BY total_cost DESC
            LIMIT 3
        ";
        $stmt = $pdo->prepare($costSql);
        analyticsBindParams($stmt, [
            ':from_date' => $filters['from_date'],
            ':to_date' => $filters['to_date']
        ]);
        $stmt->execute();
        $costDrivers = $stmt->fetchAll();
    }

    $highCostLabels = [];
    foreach ($costDrivers as $driver) {
        $highCostLabels[] = (string)$driver['category'] . ' (' . number_format((float)$driver['total_cost'], 0) . ')';
    }

    return [
        'insights' => [
            [
                'title' => 'Most Profitable Room Category',
                'value' => $mostProfitableRoom,
                'note' => 'Based on revenue contribution across room categories in selected period.'
            ],
            [
                'title' => 'Least Performing Food Item',
                'value' => $leastFood,
                'note' => 'Candidate for menu redesign, combo inclusion, or targeted promotions.'
            ],
            [
                'title' => 'Seasonal Demand Trend',
                'value' => $peakSeason,
                'note' => 'Peak month: ' . ($highestMonth['month_label'] ?? 'N/A') . '; low-demand month: ' . ($lowestMonth['month_label'] ?? 'N/A')
            ],
            [
                'title' => 'Suggested Price Action',
                'value' => $priceAction,
                'note' => 'Average occupancy in selected window: ' . round($avgOccupancy, 2) . '%'
            ],
            [
                'title' => 'High-Cost Departments',
                'value' => !empty($highCostLabels) ? implode(', ', $highCostLabels) : 'No cost records',
                'note' => 'Focus cost controls on top contributors without reducing service quality.'
            ]
        ]
    ];
}

function analyticsDashboardPayload(PDO $pdo, $input = []) {
    $filters = analyticsNormalizeFilters($input);
    $snapshot = analyticsSnapshot($pdo, $filters);
    $recent = analyticsRecentActivityBundle($pdo, $input);
    $insights = analyticsBusinessInsights($pdo, $snapshot, $filters);
    $economicsAdvanced = analyticsEconomicsAdvancedMetrics($snapshot, $input);
    $schema = analyticsGetSchema($pdo);
    $dateExpr = $schema['reservation_date_expr'];

    $master = $snapshot['master'];

    // Most profitable floor (revenue-based).
    $profitFloor = 'N/A';
    $bestFloorRevenue = -1.0;
    foreach (($snapshot['rooms']['floor_utilization'] ?? []) as $floorRow) {
        $rev = (float)($floorRow['revenue'] ?? 0);
        if ($rev > $bestFloorRevenue) {
            $bestFloorRevenue = $rev;
            $profitFloor = analyticsFloorWingLabel((int)($floorRow['floor'] ?? 0));
        }
    }
    if ($bestFloorRevenue < 0) {
        $profitFloor = (string)($master['best_performing_floor'] ?? 'N/A');
    }
    $master['most_profitable_floor'] = $profitFloor;

    // Best customer in selected period.
    $master['best_customer'] = 'N/A';
    try {
        $bestCustomerSql = "
            SELECT
                g.guest_id,
                g.name,
                COUNT(res.res_id) AS bookings,
                COALESCE(SUM(res.total_price), 0) AS spending
            FROM RESERVATION res
            JOIN GUEST g ON g.guest_id = res.guest_id
            WHERE {$dateExpr} BETWEEN :from_date AND :to_date
              AND res.status IN ('Confirmed', 'Checked-in', 'Checked-out')
            GROUP BY g.guest_id, g.name
            ORDER BY spending DESC, bookings DESC, g.name ASC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($bestCustomerSql);
        analyticsBindParams($stmt, [
            ':from_date' => $filters['from_date'],
            ':to_date' => $filters['to_date']
        ]);
        $stmt->execute();
        $bestCustomerRow = $stmt->fetch();
        if ($bestCustomerRow) {
            $master['best_customer'] = (string)$bestCustomerRow['name'];
            $master['best_customer_spend'] = analyticsMoney($bestCustomerRow['spending'] ?? 0);
            $master['best_customer_bookings'] = (int)($bestCustomerRow['bookings'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('Analytics best customer error: ' . $e->getMessage());
    }

    // Cancellation rate in selected period.
    $master['cancellation_rate_pct'] = 0.0;
    try {
        $cancelSql = "
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN res.status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count
            FROM RESERVATION res
            WHERE {$dateExpr} BETWEEN :from_date AND :to_date
        ";
        $stmt = $pdo->prepare($cancelSql);
        analyticsBindParams($stmt, [
            ':from_date' => $filters['from_date'],
            ':to_date' => $filters['to_date']
        ]);
        $stmt->execute();
        $cancelRow = $stmt->fetch() ?: ['total_count' => 0, 'cancelled_count' => 0];
        $totalCount = (int)($cancelRow['total_count'] ?? 0);
        $cancelledCount = (int)($cancelRow['cancelled_count'] ?? 0);
        $master['cancellation_rate_pct'] = $totalCount > 0 ? round(($cancelledCount / $totalCount) * 100, 2) : 0.0;
        $master['total_reservations_in_period'] = $totalCount;
        $master['cancelled_reservations_in_period'] = $cancelledCount;
    } catch (Throwable $e) {
        error_log('Analytics cancellation rate error: ' . $e->getMessage());
    }

    $kpi = [
        'total_bookings' => (int)($snapshot['financial']['summary']['total_bookings'] ?? 0),
        'total_revenue' => analyticsMoney($snapshot['financial']['summary']['reservation_revenue'] ?? 0),
        'total_rooms' => (int)($snapshot['rooms']['summary']['total_rooms'] ?? 0),
        'occupancy_avg_pct' => !empty($snapshot['rooms']['chart']['occupancy_month_values'])
            ? round(array_sum($snapshot['rooms']['chart']['occupancy_month_values']) / max(1, count($snapshot['rooms']['chart']['occupancy_month_values'])), 2)
            : 0,
        'net_profit' => analyticsMoney($snapshot['financial']['summary']['net_profit'] ?? 0),
        'profit_margin_pct' => round((float)($snapshot['financial']['summary']['profit_margin_pct'] ?? 0), 2)
    ];

    $costDistributionValues = [0.0, 0.0, 0.0, 0.0];
    if (analyticsHasTable($pdo, 'OPERATING_COSTS')) {
        $costSql = "
            SELECT
                COALESCE(SUM(CASE WHEN category = 'Staff' THEN amount ELSE 0 END), 0) AS staff_cost,
                COALESCE(SUM(CASE WHEN category = 'Electricity' THEN amount ELSE 0 END), 0) AS electricity_cost,
                COALESCE(SUM(CASE WHEN category = 'Maintenance' THEN amount ELSE 0 END), 0) AS maintenance_cost,
                COALESCE(SUM(CASE WHEN category = 'Water' THEN amount ELSE 0 END), 0) AS water_cost
            FROM OPERATING_COSTS
            WHERE DATE(cost_month) BETWEEN :from_date AND :to_date
        ";
        $stmt = $pdo->prepare($costSql);
        analyticsBindParams($stmt, [
            ':from_date' => $filters['from_date'],
            ':to_date' => $filters['to_date']
        ]);
        $stmt->execute();
        $costRow = $stmt->fetch() ?: [];

        $costDistributionValues = [
            analyticsMoney($costRow['staff_cost'] ?? 0),
            analyticsMoney($costRow['electricity_cost'] ?? 0),
            analyticsMoney($costRow['maintenance_cost'] ?? 0),
            analyticsMoney($costRow['water_cost'] ?? 0)
        ];
    } else {
        $costDistributionValues = [
            analyticsMoney(array_sum(array_map(fn($m) => (float)($m['fixed_cost'] ?? 0), $snapshot['financial']['monthly_trend'] ?? []))),
            analyticsMoney(array_sum(array_map(fn($m) => (float)($m['variable_cost'] ?? 0) * 0.45, $snapshot['financial']['monthly_trend'] ?? []))),
            analyticsMoney(array_sum(array_map(fn($m) => (float)($m['variable_cost'] ?? 0) * 0.35, $snapshot['financial']['monthly_trend'] ?? []))),
            analyticsMoney(array_sum(array_map(fn($m) => (float)($m['variable_cost'] ?? 0) * 0.20, $snapshot['financial']['monthly_trend'] ?? [])))
        ];
    }

    $combinedRevenueSeries = array_map(function ($row) {
        $combined = (float)($row['room_revenue'] ?? 0) + (float)($row['food_revenue'] ?? 0);
        if ($combined <= 0 && isset($row['total_revenue'])) {
            $combined = (float)$row['total_revenue'];
        }
        return analyticsMoney($combined);
    }, $snapshot['financial']['monthly_trend'] ?? []);

    return [
        'filters' => $filters,
        'master' => $master,
        'kpi' => $kpi,
        'chart' => [
            'revenue_booking' => [
                'labels' => $snapshot['financial']['chart']['monthly_labels'] ?? [],
                'revenue' => $combinedRevenueSeries,
                'bookings' => array_map(fn($row) => (int)($row['bookings'] ?? 0), $snapshot['financial']['monthly_trend'] ?? [])
            ],
            'occupancy' => [
                'labels' => $snapshot['rooms']['chart']['floor_labels'] ?? [],
                'booked' => $snapshot['rooms']['chart']['floor_booked'] ?? [],
                'available' => $snapshot['rooms']['chart']['floor_available'] ?? [],
                'booked_pct' => array_map(fn($row) => (float)($row['utilization_pct'] ?? 0), $snapshot['rooms']['floor_utilization'] ?? [])
            ],
            'demand' => [
                'labels' => $snapshot['rooms']['chart']['occupancy_month_labels'] ?? [],
                'bookings' => array_map(fn($row) => (int)($row['booking_count'] ?? 0), $snapshot['rooms']['occupancy_trends'] ?? [])
            ],
            'cost_distribution' => [
                'labels' => ['Staff', 'Electricity', 'Maintenance', 'Water'],
                'values' => $costDistributionValues
            ]
        ],
        'top_performers' => $master,
        'recent_activity' => $recent,
        'insights' => $insights['insights'],
        'economics_advanced' => $economicsAdvanced,
        'snapshot' => $snapshot
    ];
}
