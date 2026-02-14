<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../index.php');
}

$page_title = 'My Dashboard';
$additional_css = '<link rel="stylesheet" href="' . appPath('/assets/css/dashboard.css') . '">';
include '../includes/header.php';

$summary = [
    'total_bookings' => 0,
    'confirmed_bookings' => 0,
    'upcoming_stays' => 0,
    'total_spent' => 0.0
];
$reservations = [];
$trend_labels = [];
$trend_bookings = [];
$trend_spend = [];

try {
    $stmt = $pdo->prepare("\
        SELECT\
            COUNT(*) AS total_bookings,\
            SUM(CASE WHEN status IN ('Confirmed', 'Checked-in', 'Checked-out') THEN 1 ELSE 0 END) AS confirmed_bookings,\
            SUM(CASE WHEN check_in >= CURDATE() AND status IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) AS upcoming_stays,\
            COALESCE(SUM(CASE WHEN status IN ('Confirmed', 'Checked-in', 'Checked-out') THEN total_price ELSE 0 END), 0) AS total_spent\
        FROM RESERVATION\
        WHERE guest_id = ?\
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $summary = $stmt->fetch() ?: $summary;
    $summary['total_spent'] = (float)$summary['total_spent'];

    $stmt = $pdo->prepare("\
        SELECT\
            res.res_id,\
            res.check_in,\
            res.check_out,\
            res.status,\
            res.total_price,\
            r.room_no,\
            rt.name AS room_type,\
            rest.name AS reservation_type,\
            p.status AS payment_status\
        FROM RESERVATION res\
        LEFT JOIN ROOMS r ON res.room_id = r.room_id\
        LEFT JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id\
        JOIN RESERVATION_TYPE rest ON res.reservation_type_id = rest.reservation_type_id\
        LEFT JOIN PAYMENTS p ON res.res_id = p.res_id\
        WHERE res.guest_id = ?\
        ORDER BY res.created_at DESC\
        LIMIT 10\
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reservations = $stmt->fetchAll();

    $stmt = $pdo->prepare("\
        SELECT\
            DATE_FORMAT(created_at, '%b %Y') AS label,\
            COUNT(*) AS booking_count,\
            COALESCE(SUM(total_price), 0) AS monthly_spend\
        FROM RESERVATION\
        WHERE guest_id = ?\
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)\
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')\
        ORDER BY MIN(created_at) ASC\
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $trend_rows = $stmt->fetchAll();

    foreach ($trend_rows as $row) {
        $trend_labels[] = $row['label'];
        $trend_bookings[] = (int)$row['booking_count'];
        $trend_spend[] = (float)$row['monthly_spend'];
    }
} catch (PDOException $e) {
    error_log('User dashboard error: ' . $e->getMessage());
}
?>

<nav class="navbar navbar-expand-lg navbar-luxury fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../"><?php echo APP_NAME; ?></a>

        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
            <i class="fas fa-bars text-white"></i>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="../">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="rooms.php">Rooms</a></li>
                <li class="nav-item"><a class="nav-link" href="dining.php">Dining</a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="reservations.php">My Reservations</a></li>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container dashboard-shell">
    <section class="dashboard-banner mb-4" data-reveal>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 class="mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <p class="mb-0">Manage your stays, monitor your booking trend, and continue your luxury experience.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="rooms.php" class="btn btn-luxury btn-sm"><i class="fas fa-bed me-1"></i>Book Now</a>
                <a href="reservations.php" class="btn btn-outline-light btn-sm"><i class="fas fa-calendar-check me-1"></i>My Reservations</a>
            </div>
        </div>
    </section>

    <section class="dashboard-kpi-grid mb-4">
        <article class="kpi-card" data-reveal>
            <span class="kpi-icon"><i class="fas fa-calendar-check"></i></span>
            <div class="kpi-label">Bookings</div>
            <div class="kpi-value"><?php echo (int)$summary['total_bookings']; ?></div>
            <div class="kpi-meta"><?php echo (int)$summary['confirmed_bookings']; ?> confirmed stays</div>
        </article>

        <article class="kpi-card" data-reveal>
            <span class="kpi-icon"><i class="fas fa-rupee-sign"></i></span>
            <div class="kpi-label">Total Spend</div>
            <div class="kpi-value">₹<?php echo number_format($summary['total_spent'], 0); ?></div>
            <div class="kpi-meta">Across completed/active bookings</div>
        </article>

        <article class="kpi-card" data-reveal>
            <span class="kpi-icon"><i class="fas fa-suitcase-rolling"></i></span>
            <div class="kpi-label">Upcoming Stays</div>
            <div class="kpi-value"><?php echo (int)$summary['upcoming_stays']; ?></div>
            <div class="kpi-meta">Planned arrivals</div>
        </article>

        <article class="kpi-card" data-reveal>
            <span class="kpi-icon"><i class="fas fa-crown"></i></span>
            <div class="kpi-label">Loyalty Tier</div>
            <div class="kpi-value"><?php echo $summary['total_spent'] >= 50000 ? 'Royal' : ($summary['total_spent'] >= 20000 ? 'Elite' : 'Classic'); ?></div>
            <div class="kpi-meta">Auto-updated by spend</div>
        </article>
    </section>

    <section class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="dashboard-panel" data-reveal>
                <div class="panel-header">
                    <h5 class="luxury-header mb-0">Booking & Spend Trend (6 Months)</h5>
                </div>
                <div class="panel-body">
                    <canvas id="userTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="dashboard-panel h-100" data-reveal>
                <div class="panel-header">
                    <h5 class="luxury-header mb-0">Quick Actions</h5>
                </div>
                <div class="panel-body d-grid gap-2">
                    <a href="rooms.php" class="btn btn-luxury"><i class="fas fa-bed me-1"></i>Browse Rooms</a>
                    <a href="dining.php" class="btn btn-outline-primary"><i class="fas fa-utensils me-1"></i>Dining Menu</a>
                    <a href="profile.php" class="btn btn-outline-secondary"><i class="fas fa-user-cog me-1"></i>Update Profile</a>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-panel mb-5" data-reveal>
        <div class="panel-header">
            <h5 class="luxury-header mb-0">Recent Reservations</h5>
            <a href="reservations.php" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="panel-body">
            <?php if (empty($reservations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No reservations yet</h5>
                    <p class="text-muted">Book your first room to activate your travel dashboard.</p>
                    <a href="rooms.php" class="btn btn-luxury"><i class="fas fa-bed me-1"></i>Browse Rooms</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table dashboard-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Room</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <?php
                                $status = $reservation['status'];
                                $badge_class = 'badge-info';
                                if ($status === 'Confirmed' || $status === 'Checked-in') {
                                    $badge_class = 'badge-success';
                                } elseif ($status === 'Pending') {
                                    $badge_class = 'badge-warning';
                                } elseif ($status === 'Cancelled') {
                                    $badge_class = 'badge-danger';
                                }
                                ?>
                                <tr>
                                    <td>#<?php echo (int)$reservation['res_id']; ?></td>
                                    <td>
                                        <?php if (!empty($reservation['room_no'])): ?>
                                            Room <?php echo htmlspecialchars($reservation['room_no']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($reservation['room_type'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">To be assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($reservation['check_in'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($reservation['check_out'])); ?></td>
                                    <td><span class="badge-soft <?php echo $badge_class; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td>₹<?php echo number_format((float)$reservation['total_price'], 2); ?></td>
                                    <td>
                                        <a href="booking-success.php?id=<?php echo (int)$reservation['res_id']; ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
const trendLabels = <?php echo json_encode($trend_labels); ?>;
const trendBookings = <?php echo json_encode($trend_bookings); ?>;
const trendSpend = <?php echo json_encode($trend_spend); ?>;

const userTrendCtx = document.getElementById('userTrendChart').getContext('2d');
new Chart(userTrendCtx, {
    data: {
        labels: trendLabels.length ? trendLabels : ['No Data'],
        datasets: [
            {
                type: 'bar',
                label: 'Bookings',
                data: trendBookings.length ? trendBookings : [0],
                backgroundColor: 'rgba(212, 175, 55, 0.45)',
                borderRadius: 10,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Spend (₹)',
                data: trendSpend.length ? trendSpend : [0],
                borderColor: '#0F1B2D',
                backgroundColor: 'rgba(15, 27, 45, 0.12)',
                fill: true,
                tension: 0.35,
                pointRadius: 3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Bookings' }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                title: { display: true, text: 'Spend (₹)' }
            }
        },
        plugins: {
            legend: {
                labels: {
                    usePointStyle: true,
                    boxWidth: 8
                }
            }
        }
    }
});

async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            window.location.href = '../';
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
