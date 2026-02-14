<?php

function ensureEconomicsSchema(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS OPERATING_COSTS (
            cost_id INT AUTO_INCREMENT PRIMARY KEY,
            cost_month DATE NOT NULL,
            category ENUM('Staff', 'Electricity', 'Maintenance', 'Water') NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_month_category (cost_month, category),
            INDEX idx_cost_month (cost_month),
            INDEX idx_cost_category (category)
        )
    ");
}

function normalizeMonthDate($monthInput) {
    $monthInput = trim((string)$monthInput);
    if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
        return null;
    }

    [$year, $month] = explode('-', $monthInput);
    $year = (int)$year;
    $month = (int)$month;
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        return null;
    }

    return sprintf('%04d-%02d-01', $year, $month);
}

function upsertOperatingCost(PDO $pdo, $costMonth, $category, $amount, $description = null) {
    $allowedCategories = ['Staff', 'Electricity', 'Maintenance', 'Water'];
    if (!in_array($category, $allowedCategories, true)) {
        throw new InvalidArgumentException('Invalid cost category');
    }

    $normalizedMonth = normalizeMonthDate($costMonth);
    if ($normalizedMonth === null) {
        throw new InvalidArgumentException('Invalid cost month');
    }

    $amount = (float)$amount;
    if ($amount <= 0 || $amount > 100000000) {
        throw new InvalidArgumentException('Cost amount must be greater than 0');
    }

    $description = $description === null ? null : trim((string)$description);
    if ($description !== null && strlen($description) > 255) {
        $description = substr($description, 0, 255);
    }

    $stmt = $pdo->prepare("
        INSERT INTO OPERATING_COSTS (cost_month, category, amount, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            amount = VALUES(amount),
            description = VALUES(description)
    ");
    $stmt->execute([$normalizedMonth, $category, $amount, $description]);
}

function getEconomicsDashboardData(PDO $pdo, $months = 12) {
    ensureEconomicsSchema($pdo);

    $months = max(3, min(36, (int)$months));
    $start = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months');
    $end = (new DateTime('first day of next month'));
    $startDate = $start->format('Y-m-d');
    $endDate = $end->format('Y-m-d');

    // Reservation date column differs across schema variants.
    $dateExpr = 'r.r_date';
    $monthExpr = "DATE_FORMAT(r.r_date, '%Y-%m')";
    if (!dbHasColumn($pdo, 'RESERVATION', 'r_date')) {
        if (dbHasColumn($pdo, 'RESERVATION', 'created_at')) {
            $dateExpr = 'r.created_at';
            $monthExpr = "DATE_FORMAT(r.created_at, '%Y-%m')";
        } else {
            $dateExpr = 'r.check_in';
            $monthExpr = "DATE_FORMAT(r.check_in, '%Y-%m')";
        }
    }

    $revenueStmt = $pdo->prepare("
        SELECT
            {$monthExpr} AS month_key,
            COUNT(*) AS bookings,
            SUM(r.total_price) AS revenue
        FROM RESERVATION r
        WHERE r.status IN ('Confirmed', 'Checked-in', 'Checked-out')
          AND {$dateExpr} >= ?
          AND {$dateExpr} < ?
        GROUP BY {$monthExpr}
        ORDER BY month_key ASC
    ");
    $revenueStmt->execute([$startDate, $endDate]);
    $revenueRows = $revenueStmt->fetchAll();

    $costStmt = $pdo->prepare("
        SELECT
            DATE_FORMAT(c.cost_month, '%Y-%m') AS month_key,
            SUM(c.amount) AS total_cost,
            SUM(CASE WHEN c.category = 'Staff' THEN c.amount ELSE 0 END) AS staff_cost,
            SUM(CASE WHEN c.category = 'Electricity' THEN c.amount ELSE 0 END) AS electricity_cost,
            SUM(CASE WHEN c.category = 'Maintenance' THEN c.amount ELSE 0 END) AS maintenance_cost,
            SUM(CASE WHEN c.category = 'Water' THEN c.amount ELSE 0 END) AS water_cost
        FROM OPERATING_COSTS c
        WHERE c.cost_month >= ?
          AND c.cost_month < ?
        GROUP BY DATE_FORMAT(c.cost_month, '%Y-%m')
        ORDER BY month_key ASC
    ");
    $costStmt->execute([$startDate, $endDate]);
    $costRows = $costStmt->fetchAll();

    $costCategoryTotalStmt = $pdo->prepare("
        SELECT category, SUM(amount) AS total
        FROM OPERATING_COSTS
        WHERE cost_month >= ?
          AND cost_month < ?
        GROUP BY category
    ");
    $costCategoryTotalStmt->execute([$startDate, $endDate]);
    $costCategoryTotalsRaw = $costCategoryTotalStmt->fetchAll();

    $recentCostsStmt = $pdo->prepare("
        SELECT cost_id, cost_month, category, amount, description, created_at
        FROM OPERATING_COSTS
        WHERE cost_month >= ?
          AND cost_month < ?
        ORDER BY cost_month DESC, category ASC
        LIMIT 36
    ");
    $recentCostsStmt->execute([$startDate, $endDate]);
    $recentCosts = $recentCostsStmt->fetchAll();

    $monthMap = [];
    $cursor = clone $start;
    while ($cursor < $end) {
        $monthKey = $cursor->format('Y-m');
        $monthMap[$monthKey] = [
            'month_key' => $monthKey,
            'month_label' => $cursor->format('M Y'),
            'bookings' => 0,
            'revenue' => 0.0,
            'cost' => 0.0,
            'staff_cost' => 0.0,
            'electricity_cost' => 0.0,
            'maintenance_cost' => 0.0,
            'water_cost' => 0.0,
            'profit' => 0.0
        ];
        $cursor->modify('+1 month');
    }

    foreach ($revenueRows as $row) {
        $monthKey = $row['month_key'];
        if (!isset($monthMap[$monthKey])) {
            continue;
        }
        $monthMap[$monthKey]['bookings'] = (int)$row['bookings'];
        $monthMap[$monthKey]['revenue'] = round((float)$row['revenue'], 2);
    }

    foreach ($costRows as $row) {
        $monthKey = $row['month_key'];
        if (!isset($monthMap[$monthKey])) {
            continue;
        }
        $monthMap[$monthKey]['cost'] = round((float)$row['total_cost'], 2);
        $monthMap[$monthKey]['staff_cost'] = round((float)$row['staff_cost'], 2);
        $monthMap[$monthKey]['electricity_cost'] = round((float)$row['electricity_cost'], 2);
        $monthMap[$monthKey]['maintenance_cost'] = round((float)$row['maintenance_cost'], 2);
        $monthMap[$monthKey]['water_cost'] = round((float)$row['water_cost'], 2);
    }

    $totalBookings = 0;
    $totalRevenue = 0.0;
    $totalCost = 0.0;
    $totalFixedCost = 0.0;
    $totalVariableCost = 0.0;
    $monthlyRows = array_values($monthMap);

    foreach ($monthlyRows as &$row) {
        $row['profit'] = round($row['revenue'] - $row['cost'], 2);
        $totalBookings += (int)$row['bookings'];
        $totalRevenue += (float)$row['revenue'];
        $totalCost += (float)$row['cost'];
        $totalFixedCost += (float)$row['staff_cost'];
        $totalVariableCost += (float)$row['electricity_cost'] + (float)$row['maintenance_cost'] + (float)$row['water_cost'];
    }
    unset($row);

    $monthCount = count($monthlyRows);
    $totalProfit = $totalRevenue - $totalCost;
    $avgRevenuePerBooking = $totalBookings > 0 ? $totalRevenue / $totalBookings : 0.0;
    $avgVariableCostPerBooking = $totalBookings > 0 ? $totalVariableCost / $totalBookings : 0.0;
    $avgMonthlyFixedCost = $monthCount > 0 ? $totalFixedCost / $monthCount : 0.0;
    $contributionPerBooking = $avgRevenuePerBooking - $avgVariableCostPerBooking;
    $breakEvenBookings = $contributionPerBooking > 0 ? $avgMonthlyFixedCost / $contributionPerBooking : 0.0;
    $breakEvenRevenue = $breakEvenBookings * $avgRevenuePerBooking;
    $profitMarginPct = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0.0;

    $costCategoryTotals = [
        'Staff' => 0.0,
        'Electricity' => 0.0,
        'Maintenance' => 0.0,
        'Water' => 0.0
    ];
    foreach ($costCategoryTotalsRaw as $row) {
        $category = $row['category'];
        if (isset($costCategoryTotals[$category])) {
            $costCategoryTotals[$category] = round((float)$row['total'], 2);
        }
    }

    return [
        'range_start' => $startDate,
        'range_end_exclusive' => $endDate,
        'months' => $monthlyRows,
        'recent_costs' => $recentCosts,
        'cost_category_totals' => $costCategoryTotals,
        'summary' => [
            'total_bookings' => $totalBookings,
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round($totalCost, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_margin_pct' => round($profitMarginPct, 2),
            'avg_revenue_per_booking' => round($avgRevenuePerBooking, 2),
            'avg_variable_cost_per_booking' => round($avgVariableCostPerBooking, 2),
            'avg_monthly_fixed_cost' => round($avgMonthlyFixedCost, 2),
            'contribution_per_booking' => round($contributionPerBooking, 2),
            'break_even_bookings' => round($breakEvenBookings, 2),
            'break_even_revenue' => round($breakEvenRevenue, 2)
        ]
    ];
}
