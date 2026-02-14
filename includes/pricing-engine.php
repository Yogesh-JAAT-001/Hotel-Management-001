<?php

function pricingTableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = ?
    ");
    $stmt->execute([(string)$tableName]);
    return ((int)$stmt->fetch()['total']) > 0;
}

function ensurePricingSchema(PDO $pdo) {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $hasSettings = pricingTableExists($pdo, 'pricing_settings');
    $hasSeasons = pricingTableExists($pdo, 'pricing_seasons');

    if (!$hasSettings) {
        $pdo->exec("
            CREATE TABLE pricing_settings (
                id TINYINT PRIMARY KEY,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                min_multiplier DECIMAL(5,2) NOT NULL DEFAULT 0.70,
                max_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.80,
                occupancy_low_threshold DECIMAL(5,2) NOT NULL DEFAULT 0.40,
                occupancy_high_threshold DECIMAL(5,2) NOT NULL DEFAULT 0.75,
                occupancy_low_adjustment DECIMAL(5,2) NOT NULL DEFAULT -0.10,
                occupancy_high_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.15,
                demand_window_days INT NOT NULL DEFAULT 7,
                demand_low_threshold INT NOT NULL DEFAULT 2,
                demand_high_threshold INT NOT NULL DEFAULT 8,
                demand_low_adjustment DECIMAL(5,2) NOT NULL DEFAULT -0.05,
                demand_high_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.10,
                lead_time_last_minute_days INT NOT NULL DEFAULT 3,
                lead_time_early_bird_days INT NOT NULL DEFAULT 30,
                lead_time_last_minute_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.12,
                lead_time_early_bird_adjustment DECIMAL(5,2) NOT NULL DEFAULT -0.08,
                manual_global_adjustment DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    if (!$hasSeasons) {
        $pdo->exec("
            CREATE TABLE pricing_seasons (
                season_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                start_mmdd CHAR(5) NOT NULL,
                end_mmdd CHAR(5) NOT NULL,
                multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                description VARCHAR(255) NULL,
                priority TINYINT NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pricing_settings");
    $settingsCount = (int)$stmt->fetch()['total'];
    if ($settingsCount === 0) {
        $pdo->exec("
            INSERT INTO pricing_settings (id) VALUES (1)
        ");
    }

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM pricing_seasons");
    $seasonsCount = (int)$stmt->fetch()['total'];
    if ($seasonsCount === 0) {
        $pdo->exec("
            INSERT INTO pricing_seasons (name, start_mmdd, end_mmdd, multiplier, description, priority, is_active) VALUES
            ('Peak Summer', '04-01', '06-30', 1.20, 'High travel demand during summer season', 3, 1),
            ('Festive Demand', '12-15', '01-10', 1.30, 'Festive and year-end travel peak', 4, 1),
            ('Monsoon Saver', '07-01', '09-15', 0.90, 'Promotional season for low-demand period', 2, 1)
        ");
    }

    $initialized = true;
}

function getDynamicPricingSettings(PDO $pdo) {
    ensurePricingSchema($pdo);
    $stmt = $pdo->query("SELECT * FROM pricing_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch();
    if (!$settings) {
        throw new RuntimeException('Pricing settings not found.');
    }
    return $settings;
}

function getDynamicPricingSeasons(PDO $pdo) {
    ensurePricingSchema($pdo);
    $stmt = $pdo->query("
        SELECT season_id, name, start_mmdd, end_mmdd, multiplier, description, priority, is_active
        FROM pricing_seasons
        ORDER BY priority DESC, season_id ASC
    ");
    return $stmt->fetchAll();
}

function isMmddInRange($mmdd, $startMmdd, $endMmdd) {
    if ($startMmdd <= $endMmdd) {
        return $mmdd >= $startMmdd && $mmdd <= $endMmdd;
    }
    // Wrap-around window (e.g. Dec -> Jan)
    return $mmdd >= $startMmdd || $mmdd <= $endMmdd;
}

function getSeasonForDate(array $seasons, DateTimeInterface $date) {
    $matched = null;
    $mmdd = $date->format('m-d');

    foreach ($seasons as $season) {
        if ((int)$season['is_active'] !== 1) {
            continue;
        }
        if (isMmddInRange($mmdd, $season['start_mmdd'], $season['end_mmdd'])) {
            if ($matched === null || (int)$season['priority'] > (int)$matched['priority']) {
                $matched = $season;
            }
        }
    }

    if ($matched === null) {
        return [
            'name' => 'Base Season',
            'multiplier' => 1.00
        ];
    }

    return [
        'name' => $matched['name'],
        'multiplier' => (float)$matched['multiplier']
    ];
}

function safeRatio($numerator, $denominator) {
    if ((float)$denominator <= 0.0) {
        return 0.0;
    }
    return (float)$numerator / (float)$denominator;
}

function clampValue($value, $min, $max) {
    return max((float)$min, min((float)$max, (float)$value));
}

function interpolateAdjustment($value, $lowThreshold, $highThreshold, $lowAdjustment, $highAdjustment) {
    $value = (float)$value;
    $lowThreshold = (float)$lowThreshold;
    $highThreshold = (float)$highThreshold;
    $lowAdjustment = (float)$lowAdjustment;
    $highAdjustment = (float)$highAdjustment;

    if ($value <= $lowThreshold) {
        return $lowAdjustment;
    }
    if ($value >= $highThreshold) {
        return $highAdjustment;
    }
    if ($highThreshold <= $lowThreshold) {
        return 0.0;
    }

    $ratio = ($value - $lowThreshold) / ($highThreshold - $lowThreshold);
    return $lowAdjustment + (($highAdjustment - $lowAdjustment) * $ratio);
}

function getTotalSellableRooms(PDO $pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM ROOMS WHERE status != 'Maintenance'");
    return (int)$stmt->fetch()['total'];
}

function getBookedRoomCountForDate(PDO $pdo, DateTimeInterface $nightDate) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT room_id) AS total
        FROM RESERVATION
        WHERE room_id IS NOT NULL
        AND status IN ('Pending', 'Confirmed', 'Checked-in')
        AND check_in <= ?
        AND check_out > ?
    ");
    $date = $nightDate->format('Y-m-d');
    $stmt->execute([$date, $date]);
    return (int)$stmt->fetch()['total'];
}

function getDemandScore(PDO $pdo, DateTimeInterface $checkInDate, $windowDays) {
    $windowDays = max(1, (int)$windowDays);
    $start = (clone $checkInDate)->modify('-' . $windowDays . ' days')->format('Y-m-d');
    $end = $checkInDate->format('Y-m-d');

    // Reservation date column differs across schema variants.
    $dateExpr = 'r_date';
    if (!dbHasColumn($pdo, 'RESERVATION', 'r_date')) {
        if (dbHasColumn($pdo, 'RESERVATION', 'created_at')) {
            $dateExpr = 'DATE(created_at)';
        } else {
            $dateExpr = 'check_in';
        }
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM RESERVATION
        WHERE {$dateExpr} >= ?
        AND {$dateExpr} < ?
        AND status IN ('Pending', 'Confirmed', 'Checked-in', 'Checked-out')
    ");
    $stmt->execute([$start, $end]);
    return (int)$stmt->fetch()['total'];
}

function calculateLeadAdjustment($leadDays, array $settings) {
    $leadDays = (int)$leadDays;
    $lastMinuteDays = (int)$settings['lead_time_last_minute_days'];
    $earlyBirdDays = (int)$settings['lead_time_early_bird_days'];
    $lastMinuteAdjustment = (float)$settings['lead_time_last_minute_adjustment'];
    $earlyBirdAdjustment = (float)$settings['lead_time_early_bird_adjustment'];

    if ($leadDays <= $lastMinuteDays) {
        return $lastMinuteAdjustment;
    }
    if ($leadDays >= $earlyBirdDays) {
        return $earlyBirdAdjustment;
    }
    if ($earlyBirdDays <= $lastMinuteDays) {
        return 0.0;
    }

    $ratio = ($leadDays - $lastMinuteDays) / ($earlyBirdDays - $lastMinuteDays);
    return $lastMinuteAdjustment + (($earlyBirdAdjustment - $lastMinuteAdjustment) * $ratio);
}

function getDynamicPriceQuote(PDO $pdo, $roomId, $checkIn, $checkOut) {
    ensurePricingSchema($pdo);

    $roomId = (int)$roomId;
    if ($roomId <= 0) {
        throw new InvalidArgumentException('Invalid room ID');
    }

    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    if ($checkOutDate <= $checkInDate) {
        throw new InvalidArgumentException('Check-out must be after check-in');
    }

    $stmt = $pdo->prepare("SELECT room_id, room_no, rent, status FROM ROOMS WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    if (!$room) {
        throw new InvalidArgumentException('Room not found');
    }

    $settings = getDynamicPricingSettings($pdo);
    $seasons = getDynamicPricingSeasons($pdo);
    $baseRate = (float)$room['rent'];
    $nights = (int)$checkInDate->diff($checkOutDate)->days;
    $baseTotal = round($baseRate * $nights, 2);

    if ((int)$settings['is_enabled'] !== 1) {
        return [
            'enabled' => false,
            'room_id' => $roomId,
            'room_no' => $room['room_no'],
            'nights' => $nights,
            'base_rate' => $baseRate,
            'base_total' => $baseTotal,
            'dynamic_total' => $baseTotal,
            'average_nightly_rate' => $baseRate,
            'average_multiplier' => 1.0,
            'factors' => [
                'season_multiplier_avg' => 1.0,
                'occupancy_adjustment_avg' => 0.0,
                'demand_adjustment_avg' => 0.0,
                'lead_time_adjustment_avg' => 0.0,
                'global_adjustment' => 0.0
            ],
            'demand_score' => 0,
            'nightly_breakdown' => []
        ];
    }

    $demandScore = getDemandScore($pdo, $checkInDate, (int)$settings['demand_window_days']);
    $leadDays = max(0, (int)(new DateTime('today'))->diff($checkInDate)->format('%r%a'));
    $leadAdjustment = calculateLeadAdjustment($leadDays, $settings);
    $demandAdjustment = interpolateAdjustment(
        $demandScore,
        (float)$settings['demand_low_threshold'],
        (float)$settings['demand_high_threshold'],
        (float)$settings['demand_low_adjustment'],
        (float)$settings['demand_high_adjustment']
    );
    $globalAdjustment = (float)$settings['manual_global_adjustment'];

    $sellableRooms = getTotalSellableRooms($pdo);
    $runningDate = clone $checkInDate;
    $dynamicTotal = 0.0;
    $sumMultiplier = 0.0;
    $sumSeasonMultiplier = 0.0;
    $sumOccupancyAdjustment = 0.0;
    $nightlyBreakdown = [];

    while ($runningDate < $checkOutDate) {
        $season = getSeasonForDate($seasons, $runningDate);
        $bookedRooms = getBookedRoomCountForDate($pdo, $runningDate);
        $occupancyRate = safeRatio($bookedRooms, $sellableRooms);
        $occupancyAdjustment = interpolateAdjustment(
            $occupancyRate,
            (float)$settings['occupancy_low_threshold'],
            (float)$settings['occupancy_high_threshold'],
            (float)$settings['occupancy_low_adjustment'],
            (float)$settings['occupancy_high_adjustment']
        );

        $rawMultiplier = ((float)$season['multiplier']) * (1.0 + $occupancyAdjustment + $demandAdjustment + $leadAdjustment + $globalAdjustment);
        $finalMultiplier = clampValue(
            $rawMultiplier,
            (float)$settings['min_multiplier'],
            (float)$settings['max_multiplier']
        );
        $nightRate = round($baseRate * $finalMultiplier, 2);

        $dynamicTotal += $nightRate;
        $sumMultiplier += $finalMultiplier;
        $sumSeasonMultiplier += (float)$season['multiplier'];
        $sumOccupancyAdjustment += $occupancyAdjustment;

        $nightlyBreakdown[] = [
            'date' => $runningDate->format('Y-m-d'),
            'season' => $season['name'],
            'season_multiplier' => round((float)$season['multiplier'], 4),
            'occupancy_rate' => round($occupancyRate, 4),
            'occupancy_adjustment' => round($occupancyAdjustment, 4),
            'demand_score' => $demandScore,
            'demand_adjustment' => round($demandAdjustment, 4),
            'lead_days' => $leadDays,
            'lead_time_adjustment' => round($leadAdjustment, 4),
            'global_adjustment' => round($globalAdjustment, 4),
            'final_multiplier' => round($finalMultiplier, 4),
            'night_rate' => $nightRate
        ];

        $runningDate->modify('+1 day');
    }

    $dynamicTotal = round($dynamicTotal, 2);
    $averageMultiplier = $nights > 0 ? $sumMultiplier / $nights : 1.0;
    $averageNightlyRate = $nights > 0 ? $dynamicTotal / $nights : $baseRate;

    return [
        'enabled' => true,
        'room_id' => $roomId,
        'room_no' => $room['room_no'],
        'nights' => $nights,
        'base_rate' => $baseRate,
        'base_total' => $baseTotal,
        'dynamic_total' => $dynamicTotal,
        'average_nightly_rate' => round($averageNightlyRate, 2),
        'average_multiplier' => round($averageMultiplier, 4),
        'factors' => [
            'season_multiplier_avg' => round($nights > 0 ? ($sumSeasonMultiplier / $nights) : 1.0, 4),
            'occupancy_adjustment_avg' => round($nights > 0 ? ($sumOccupancyAdjustment / $nights) : 0.0, 4),
            'demand_adjustment_avg' => round($demandAdjustment, 4),
            'lead_time_adjustment_avg' => round($leadAdjustment, 4),
            'global_adjustment' => round($globalAdjustment, 4)
        ],
        'demand_score' => $demandScore,
        'nightly_breakdown' => $nightlyBreakdown
    ];
}

function getPricingDiagnostics(PDO $pdo) {
    ensurePricingSchema($pdo);
    $settings = getDynamicPricingSettings($pdo);
    $sellableRooms = getTotalSellableRooms($pdo);
    $bookedToday = getBookedRoomCountForDate($pdo, new DateTime('today'));
    $todayOccupancy = safeRatio($bookedToday, $sellableRooms);
    $todayDemandScore = getDemandScore($pdo, new DateTime('today'), (int)$settings['demand_window_days']);

    return [
        'sellable_rooms' => $sellableRooms,
        'booked_today' => $bookedToday,
        'today_occupancy_rate' => round($todayOccupancy, 4),
        'today_demand_score' => $todayDemandScore
    ];
}
