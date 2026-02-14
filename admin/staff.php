<?php
require_once '../config.php';
require_once '../includes/analytics-engine.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Staff Intelligence';
$additional_css = ''
    . '<link rel="stylesheet" href="' . appPath('/assets/css/dashboard.css') . '">' 
    . '<link rel="stylesheet" href="' . appPath('/assets/css/analytics.css') . '">';
include '../includes/header.php';

$staffDeptColumn = analyticsHasColumn($pdo, 'STAFF', 'dept_id') ? 'dept_id' : 'dep_id';
$deptPkColumn = analyticsHasColumn($pdo, 'DEPARTMENT', 'dept_id') ? 'dept_id' : 'dep_id';
$deptNameColumn = analyticsHasColumn($pdo, 'DEPARTMENT', 'name') ? 'name' : 'dep_name';
$staffRoleColumn = analyticsHasColumn($pdo, 'STAFF', 'role') ? 'role' : (analyticsHasColumn($pdo, 'STAFF', 'position') ? 'position' : 'name');
$staffPhoneColumn = analyticsHasColumn($pdo, 'STAFF', 'phone_no') ? 'phone_no' : 'phone';
$staffStatusColumn = analyticsHasColumn($pdo, 'STAFF', 'status') ? 'status' : null;
$attendanceTableExists = analyticsHasTable($pdo, 'STAFF_ATTENDANCE');
$hasAttendanceRate = analyticsHasColumn($pdo, 'STAFF', 'attendance_rate');
$hasPerformanceScore = analyticsHasColumn($pdo, 'STAFF', 'performance_score');
$hasLastActivity = analyticsHasColumn($pdo, 'STAFF', 'last_activity_at');

$staffRows = [];
$roleRows = [];
$topPerformers = [];
$activityRows = [];
$stats = [
    'total_staff' => 0,
    'active_staff' => 0,
    'monthly_salary_cost' => 0,
    'avg_performance' => 0,
    'avg_attendance' => 0
];

try {
    $statusExpr = $staffStatusColumn ? ("COALESCE(s.$staffStatusColumn, 'Active')") : "'Active'";
    $performanceExpr = $hasPerformanceScore ? 'COALESCE(s.performance_score, 75)' : '75';
    $attendanceExpr = $hasAttendanceRate ? 'COALESCE(s.attendance_rate, 90)' : '90';
    $lastActivityExpr = $hasLastActivity ? 's.last_activity_at' : 's.created_at';

    $sql = "
        SELECT
            s.staff_id,
            s.name,
            s.email,
            s.{$staffPhoneColumn} AS phone,
            COALESCE(s.{$staffRoleColumn}, 'Staff') AS role_name,
            COALESCE(d.{$deptNameColumn}, 'General') AS department_name,
            COALESCE(s.salary, 0) AS salary,
            {$statusExpr} AS status,
            {$performanceExpr} AS performance_score,
            {$attendanceExpr} AS attendance_rate,
            {$lastActivityExpr} AS last_activity_at,
            s.hire_date
        FROM STAFF s
        LEFT JOIN DEPARTMENT d ON d.{$deptPkColumn} = s.{$staffDeptColumn}
        ORDER BY s.staff_id DESC
    ";
    $staffRows = $pdo->query($sql)->fetchAll();

    $stats['total_staff'] = count($staffRows);
    $stats['active_staff'] = count(array_filter($staffRows, fn($row) => strtolower((string)$row['status']) === 'active'));
    $stats['monthly_salary_cost'] = array_sum(array_map(fn($row) => (float)$row['salary'], $staffRows));

    if (!empty($staffRows)) {
        $stats['avg_performance'] = round(array_sum(array_map(fn($row) => (float)$row['performance_score'], $staffRows)) / count($staffRows), 2);
        $stats['avg_attendance'] = round(array_sum(array_map(fn($row) => (float)$row['attendance_rate'], $staffRows)) / count($staffRows), 2);
    }

    $roleSql = "
        SELECT
            COALESCE(s.{$staffRoleColumn}, 'Staff') AS role_name,
            COUNT(*) AS total_staff,
            COALESCE(SUM(s.salary), 0) AS salary_cost
        FROM STAFF s
        GROUP BY role_name
        ORDER BY total_staff DESC
    ";
    $roleRows = $pdo->query($roleSql)->fetchAll();

    $topPerformers = $staffRows;
    usort($topPerformers, fn($a, $b) => ((float)$b['performance_score']) <=> ((float)$a['performance_score']));
    $topPerformers = array_slice($topPerformers, 0, 8);

    $activityData = analyticsRecentStaffActivities($pdo, 1, 10);
    $activityRows = $activityData['rows'] ?? [];
} catch (Throwable $e) {
    error_log('Staff dashboard error: ' . $e->getMessage());
}
?>

<div class="container-fluid dashboard-shell">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar-luxury collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h4 class="luxury-header text-warning"><?php echo APP_NAME; ?></h4>
                    <p class="text-light small">Admin Panel</p>
                </div>

                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="manager.php"><i class="fas fa-briefcase me-2"></i>Manager Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="rooms.php"><i class="fas fa-bed me-2"></i>Rooms</a></li>
                    <li class="nav-item"><a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check me-2"></i>Reservations</a></li>
                    <li class="nav-item"><a class="nav-link active" href="staff.php"><i class="fas fa-user-tie me-2"></i>Staff</a></li>
                    <li class="nav-item"><a class="nav-link" href="food.php"><i class="fas fa-utensils me-2"></i>Food & Dining</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Economics</a></li>
                    <li class="nav-item mt-3"><a class="nav-link text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <section class="dashboard-banner mb-4" data-reveal>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h2 class="mb-2">Staff Management Intelligence</h2>
                        <p class="mb-0">Role performance, payroll impact, attendance health, and recent team activities.</p>
                    </div>
                    <div>
                        <span class="badge badge-luxury">Roles: Manager, Receptionist, Chef, Housekeeping, Finance</span>
                    </div>
                </div>
            </section>

            <section class="dashboard-kpi-grid mb-4" data-reveal>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-users"></i></span>
                    <div class="kpi-label">Total Staff</div>
                    <div class="kpi-value"><?php echo number_format($stats['total_staff']); ?></div>
                    <div class="kpi-meta"><?php echo number_format($stats['active_staff']); ?> active</div>
                </article>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-money-check-dollar"></i></span>
                    <div class="kpi-label">Salary Cost</div>
                    <div class="kpi-value">₹<?php echo number_format($stats['monthly_salary_cost'], 0); ?></div>
                    <div class="kpi-meta">Estimated monthly payroll</div>
                </article>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-star"></i></span>
                    <div class="kpi-label">Avg Performance</div>
                    <div class="kpi-value"><?php echo number_format($stats['avg_performance'], 2); ?></div>
                    <div class="kpi-meta">Performance score / 100</div>
                </article>
                <article class="kpi-card">
                    <span class="kpi-icon"><i class="fas fa-user-check"></i></span>
                    <div class="kpi-label">Avg Attendance</div>
                    <div class="kpi-value"><?php echo number_format($stats['avg_attendance'], 2); ?>%</div>
                    <div class="kpi-meta"><?php echo $attendanceTableExists ? 'Simulated from attendance logs' : 'Derived from staff profile'; ?></div>
                </article>
            </section>

            <section class="analytics-grid cols-2 mb-4" data-reveal>
                <article class="analytics-card">
                    <div class="card-head"><h6 class="mb-0">Role Distribution</h6></div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="staffRoleChart"></canvas></div></div>
                </article>
                <article class="analytics-card">
                    <div class="card-head"><h6 class="mb-0">Top Performance Scores</h6></div>
                    <div class="card-body"><div class="chart-shell chart-sm"><canvas id="staffPerformanceChart"></canvas></div></div>
                </article>
            </section>

            <section class="analytics-section mb-4" data-reveal>
                <div class="section-header"><h5 class="mb-0">Recent Staff Activities</h5></div>
                <div class="analytics-grid">
                    <div class="table-responsive">
                        <table class="table analytics-table table-sm">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-end">Hours</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activityRows)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No staff activities found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($activityRows as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string)$row['staff_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['role_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['department_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$row['activity_status']); ?></td>
                                            <td><?php echo htmlspecialchars(substr((string)$row['activity_date'], 0, 10)); ?></td>
                                            <td class="text-end"><?php echo isset($row['hours_worked']) && $row['hours_worked'] !== null ? number_format((float)$row['hours_worked'], 2) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['notes'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="dashboard-panel mb-5" data-reveal>
                <div class="panel-header">
                    <h5 class="luxury-header mb-0">Staff Directory & Cost Tracking</h5>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table dashboard-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Phone</th>
                                    <th class="text-end">Salary</th>
                                    <th class="text-end">Performance</th>
                                    <th class="text-end">Attendance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staffRows)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-3">No staff records found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($staffRows as $staff): ?>
                                        <tr>
                                            <td>#<?php echo (int)$staff['staff_id']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$staff['name']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$staff['role_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$staff['department_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string)$staff['phone']); ?></td>
                                            <td class="text-end">₹<?php echo number_format((float)$staff['salary'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$staff['performance_score'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$staff['attendance_rate'], 2); ?>%</td>
                                            <td><?php echo htmlspecialchars((string)$staff['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
const roleLabels = <?php echo json_encode(array_map(fn($r) => (string)$r['role_name'], $roleRows)); ?>;
const roleCounts = <?php echo json_encode(array_map(fn($r) => (int)$r['total_staff'], $roleRows)); ?>;
const performerLabels = <?php echo json_encode(array_map(fn($r) => (string)$r['name'], $topPerformers)); ?>;
const performerScores = <?php echo json_encode(array_map(fn($r) => (float)$r['performance_score'], $topPerformers)); ?>;

new Chart(document.getElementById('staffRoleChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: roleLabels,
        datasets: [{
            data: roleCounts,
            backgroundColor: ['#0F1B2D', '#D4AF37', '#8B6F2F', '#506A8A', '#B8962E']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '58%'
    }
});

new Chart(document.getElementById('staffPerformanceChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: performerLabels,
        datasets: [{
            label: 'Performance Score',
            data: performerScores,
            backgroundColor: 'rgba(15, 27, 45, 0.82)',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});

async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            window.location.href = 'login.php';
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
