<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Reservations Management';
include '../includes/header.php';

// Reservation filters
$defaultToDate = date('Y-m-d');
$defaultFromDate = date('Y-m-d', strtotime('-6 months'));
$fromDate = isset($_GET['from_date']) ? trim((string)$_GET['from_date']) : $defaultFromDate;
$toDate = isset($_GET['to_date']) ? trim((string)$_GET['to_date']) : $defaultToDate;
$statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$roomTypeFilter = isset($_GET['room_type']) ? trim((string)$_GET['room_type']) : '';

$fromDateObj = DateTime::createFromFormat('Y-m-d', $fromDate) ?: new DateTime($defaultFromDate);
$toDateObj = DateTime::createFromFormat('Y-m-d', $toDate) ?: new DateTime($defaultToDate);
if ($fromDateObj > $toDateObj) {
    $tmp = $fromDateObj;
    $fromDateObj = $toDateObj;
    $toDateObj = $tmp;
}
$fromDate = $fromDateObj->format('Y-m-d');
$toDate = $toDateObj->format('Y-m-d');

$allowedStatuses = ['Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (strtolower($roomTypeFilter) === 'all') {
    $roomTypeFilter = '';
}

$roomTypeOptions = [];
$reservations = [];
$stats = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];

// Get reservations with guest and room details (filtered)
try {
    $roomTypeStmt = $pdo->query("
        SELECT DISTINCT rt.name
        FROM ROOM_TYPE rt
        JOIN ROOMS r ON r.room_type_id = rt.room_type_id
        ORDER BY rt.name ASC
    ");
    $roomTypeOptions = array_values(array_filter(array_map(function ($row) {
        return isset($row['name']) ? trim((string)$row['name']) : '';
    }, $roomTypeStmt->fetchAll())));

    $reservationDateExpr = dbHasColumn($pdo, 'RESERVATION', 'r_date')
        ? 'res.r_date'
        : (dbHasColumn($pdo, 'RESERVATION', 'created_at') ? 'DATE(res.created_at)' : 'res.check_in');
    $reservationOrderExpr = dbHasColumn($pdo, 'RESERVATION', 'created_at') ? 'res.created_at' : 'res.check_in';

    $where = ["{$reservationDateExpr} BETWEEN :from_date AND :to_date"];
    $params = [
        ':from_date' => $fromDate,
        ':to_date' => $toDate
    ];

    if ($statusFilter !== '') {
        $where[] = "res.status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($roomTypeFilter !== '') {
        $where[] = "LOWER(COALESCE(rt.name, '')) = LOWER(:room_type)";
        $params[':room_type'] = $roomTypeFilter;
    }

    $sql = "
        SELECT 
            res.*,
            g.name as guest_name,
            g.email as guest_email,
            g.phone_no,
            r.room_no,
            rt.name as room_type,
            rest.name as reservation_type,
            p.status as payment_status,
            p.payment_method
        FROM RESERVATION res
        JOIN GUEST g ON res.guest_id = g.guest_id
        LEFT JOIN ROOMS r ON res.room_id = r.room_id
        LEFT JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
        JOIN RESERVATION_TYPE rest ON res.reservation_type_id = rest.reservation_type_id
        LEFT JOIN PAYMENTS p ON res.res_id = p.res_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$reservationOrderExpr} DESC, res.res_id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
    
    // Get statistics
    $stats = [
        'total' => count($reservations),
        'confirmed' => count(array_filter($reservations, fn($r) => $r['status'] === 'Confirmed')),
        'pending' => count(array_filter($reservations, fn($r) => $r['status'] === 'Pending')),
        'cancelled' => count(array_filter($reservations, fn($r) => $r['status'] === 'Cancelled'))
    ];
    
} catch (PDOException $e) {
    error_log("Reservations error: " . $e->getMessage());
    $reservations = [];
    $stats = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
    $roomTypeOptions = [];
}

$exportQuery = http_build_query([
    'type' => 'reservations',
    'format' => 'csv',
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'status' => $statusFilter,
    'room_type' => $roomTypeFilter
]);
?>

<!-- Admin Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-hotel me-2"></i><?php echo APP_NAME; ?> - Admin
        </a>
        
        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#adminNav">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="reservations.php">
                        <i class="fas fa-calendar-check me-1"></i>Reservations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rooms.php">
                        <i class="fas fa-bed me-1"></i>Rooms
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="guests.php">
                        <i class="fas fa-users me-1"></i>Guests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="staff.php">
                        <i class="fas fa-user-tie me-1"></i>Staff
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="food.php">
                        <i class="fas fa-utensils me-1"></i>Food & Dining
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pricing.php">
                        <i class="fas fa-chart-line me-1"></i>Pricing Engine
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-1"></i>Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-1"></i>Settings
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['admin_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../user/" target="_blank">View Website</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container-fluid" style="margin-top: 80px;">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Reservations Management</h1>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-outline-success" href="<?php echo '../api/admin/analytics-export.php?' . htmlspecialchars($exportQuery); ?>">
                        <i class="fas fa-file-csv me-1"></i>Export CSV
                    </a>
                    <a class="btn btn-primary" href="../user/booking.php" target="_blank" rel="noopener">
                        <i class="fas fa-plus me-1"></i>Create Reservation
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label" for="fromDate">From Date</label>
                            <input type="date" class="form-control" id="fromDate" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="toDate">To Date</label>
                            <input type="date" class="form-control" id="toDate" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="statusFilter">Status</label>
                            <select class="form-select" id="statusFilter" name="status">
                                <option value="">All Statuses</option>
                                <?php foreach ($allowedStatuses as $statusOption): ?>
                                    <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($statusOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="roomTypeFilter">Room Type</label>
                            <select class="form-select" id="roomTypeFilter" name="room_type">
                                <option value="">All Types</option>
                                <?php foreach ($roomTypeOptions as $roomTypeOption): ?>
                                    <option value="<?php echo htmlspecialchars($roomTypeOption); ?>" <?php echo strcasecmp($roomTypeFilter, $roomTypeOption) === 0 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($roomTypeOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-warning w-100">Apply Filters</button>
                            <a href="reservations.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['total']; ?></h4>
                            <p class="mb-0">Total Reservations</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['confirmed']; ?></h4>
                            <p class="mb-0">Confirmed</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['pending']; ?></h4>
                            <p class="mb-0">Pending</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $stats['cancelled']; ?></h4>
                            <p class="mb-0">Cancelled</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reservations Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Reservations</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reservations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No reservations found</h5>
                            <p class="text-muted">Reservations will appear here once guests start booking.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Guest</th>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td>#<?php echo $reservation['res_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($reservation['guest_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($reservation['guest_email']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($reservation['room_no']): ?>
                                                Room <?php echo $reservation['room_no']; ?><br>
                                                <small class="text-muted"><?php echo $reservation['room_type']; ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">To be assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($reservation['check_in'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($reservation['check_out'])); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $reservation['status'] === 'Confirmed' ? 'bg-success' : 
                                                    ($reservation['status'] === 'Pending' ? 'bg-warning text-dark' : 
                                                    ($reservation['status'] === 'Cancelled' ? 'bg-danger' : 'bg-info')); 
                                            ?>">
                                                <?php echo $reservation['status']; ?>
                                            </span>
                                        </td>
                                        <td>₹<?php echo number_format($reservation['total_price'], 2); ?></td>
                                        <td>
                                            <?php if ($reservation['payment_status']): ?>
                                                <span class="badge <?php echo $reservation['payment_status'] === 'Success' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                    <?php echo $reservation['payment_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-info" onclick="viewReservation(<?php echo $reservation['res_id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($reservation['status'] === 'Pending'): ?>
                                                    <button class="btn btn-outline-success" onclick="updateReservationStatus(<?php echo $reservation['res_id']; ?>, 'Confirmed')" title="Confirm">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!in_array($reservation['status'], ['Cancelled', 'Checked-out'], true)): ?>
                                                    <button class="btn btn-outline-warning" onclick="cancelReservation(<?php echo $reservation['res_id']; ?>)" title="Cancel">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-danger" onclick="deleteReservation(<?php echo $reservation['res_id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reservationDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reservation Details</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="reservationDetailBody">
                <div class="text-center py-3 text-muted">
                    <i class="fas fa-spinner fa-spin me-2"></i>Loading reservation details...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR'
    }).format(Number(value || 0));
}

function buildFoodRows(foodItems) {
    const rows = Array.isArray(foodItems) ? foodItems : [];
    if (rows.length === 0) {
        return '<tr><td colspan="4" class="text-center text-muted">No food items</td></tr>';
    }
    return rows.map((item) => `
        <tr>
            <td>${escapeHtml(item.item_name || '-')}</td>
            <td class="text-end">${Number(item.quantity || 0)}</td>
            <td class="text-end">${formatCurrency(item.unit_price || 0)}</td>
            <td class="text-end">${formatCurrency(item.line_total || 0)}</td>
        </tr>
    `).join('');
}

async function viewReservation(reservationId) {
    const body = document.getElementById('reservationDetailBody');
    const modalEl = document.getElementById('reservationDetailModal');
    if (!body || !modalEl) {
        return;
    }

    body.innerHTML = '<div class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading reservation details...</div>';
    const modal = new mdb.Modal(modalEl);
    modal.show();

    try {
        const response = await fetch(`../api/admin/reservations.php?id=${encodeURIComponent(reservationId)}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || payload.message || 'Unable to load reservation details');
        }

        const reservation = payload.data?.reservation || {};
        const foodItems = payload.data?.food_items || [];

        body.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <div><strong>Reservation ID:</strong> #${escapeHtml(reservation.res_id || '')}</div>
                    <div><strong>Status:</strong> ${escapeHtml(reservation.status || '-')}</div>
                    <div><strong>Guest:</strong> ${escapeHtml(reservation.guest_name || '-')}</div>
                    <div><strong>Email:</strong> ${escapeHtml(reservation.guest_email || '-')}</div>
                    <div><strong>Phone:</strong> ${escapeHtml(reservation.guest_phone || '-')}</div>
                </div>
                <div class="col-md-6">
                    <div><strong>Room:</strong> ${escapeHtml(reservation.room_no || '-')} (${escapeHtml(reservation.room_type || '-')})</div>
                    <div><strong>Reservation Type:</strong> ${escapeHtml(reservation.reservation_type || '-')}</div>
                    <div><strong>Check-in:</strong> ${escapeHtml(reservation.check_in || '-')}</div>
                    <div><strong>Check-out:</strong> ${escapeHtml(reservation.check_out || '-')}</div>
                    <div><strong>Total:</strong> ${formatCurrency(reservation.total_price || 0)}</div>
                    <div><strong>Payment:</strong> ${escapeHtml(reservation.payment_status || 'Pending')} (${escapeHtml(reservation.payment_method || '-')})</div>
                </div>
            </div>
            <hr>
            <h6 class="mb-2">Food Orders</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>${buildFoodRows(foodItems)}</tbody>
                </table>
            </div>
            ${reservation.special_requests ? `<div class="mt-2"><strong>Special Requests:</strong> ${escapeHtml(reservation.special_requests)}</div>` : ''}
        `;
    } catch (error) {
        body.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Failed to load reservation details')}</div>`;
    }
}

async function updateReservationStatus(reservationId, status) {
    try {
        const response = await apiRequest('../api/admin/reservations.php', {
            method: 'PUT',
            body: JSON.stringify({
                reservation_id: reservationId,
                status
            })
        });

        if (response.success) {
            showToast(response.message || 'Reservation updated successfully', 'success');
            setTimeout(() => location.reload(), 900);
        }
    } catch (error) {
        showToast(error.message || 'Unable to update reservation', 'danger');
    }
}

async function cancelReservation(reservationId) {
    if (!confirm('Are you sure you want to cancel this reservation?')) {
        return;
    }
    await updateReservationStatus(reservationId, 'Cancelled');
}

async function deleteReservation(reservationId) {
    if (!confirm('Delete this reservation permanently? This cannot be undone.')) {
        return;
    }

    try {
        const response = await apiRequest('../api/admin/reservations.php', {
            method: 'DELETE',
            body: JSON.stringify({
                reservation_id: reservationId
            })
        });

        if (response.success) {
            showToast(response.message || 'Reservation deleted successfully', 'success');
            setTimeout(() => location.reload(), 900);
        }
    } catch (error) {
        showToast(error.message || 'Unable to delete reservation', 'danger');
    }
}

// Logout function
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
