<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Guests Management';
include '../includes/header.php';

$reservationDateColumn = dbHasColumn($pdo, 'RESERVATION', 'created_at')
    ? 'created_at'
    : (dbHasColumn($pdo, 'RESERVATION', 'r_date') ? 'r_date' : 'check_in');

try {
    $stmt = $pdo->query("\n        SELECT\n            g.*,\n            COUNT(r.res_id) AS total_reservations,\n            MAX(r.{$reservationDateColumn}) AS last_booking,\n            COALESCE(SUM(CASE WHEN r.status IN ('Confirmed', 'Checked-in', 'Checked-out') THEN r.total_price ELSE 0 END), 0) AS total_spent,\n            COALESCE(SUM(CASE WHEN r.status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_reservations,\n            CASE\n                WHEN COUNT(r.res_id) > 0 THEN 'Active'\n                WHEN g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'New'\n                ELSE 'Inactive'\n            END AS guest_status\n        FROM GUEST g\n        LEFT JOIN RESERVATION r ON r.guest_id = g.guest_id\n        GROUP BY g.guest_id\n        ORDER BY g.created_at DESC, g.guest_id DESC\n    ");
    $guests = $stmt->fetchAll();

    $stats = [
        'total' => count($guests),
        'active' => count(array_filter($guests, fn($g) => (int)$g['total_reservations'] > 0)),
        'new_this_month' => count(array_filter($guests, fn($g) => strtotime((string)$g['created_at']) > strtotime('-1 month'))),
        'high_value' => count(array_filter($guests, fn($g) => (float)$g['total_spent'] >= 50000))
    ];
} catch (Throwable $e) {
    error_log('Guests page error: ' . $e->getMessage());
    $guests = [];
    $stats = ['total' => 0, 'active' => 0, 'new_this_month' => 0, 'high_value' => 0];
}
?>

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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="reservations.php"><i class="fas fa-calendar-check me-1"></i>Reservations</a></li>
                <li class="nav-item"><a class="nav-link" href="rooms.php"><i class="fas fa-bed me-1"></i>Rooms</a></li>
                <li class="nav-item"><a class="nav-link active" href="guests.php"><i class="fas fa-users me-1"></i>Guests</a></li>
                <li class="nav-item"><a class="nav-link" href="staff.php"><i class="fas fa-user-tie me-1"></i>Staff</a></li>
                <li class="nav-item"><a class="nav-link" href="food.php"><i class="fas fa-utensils me-1"></i>Food & Dining</a></li>
                <li class="nav-item"><a class="nav-link" href="pricing.php"><i class="fas fa-chart-line me-1"></i>Pricing Engine</a></li>
                <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-1"></i>Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-1"></i>Settings</a></li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../" target="_blank" rel="noopener">View Website</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout(); return false;">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid" style="margin-top: 86px;">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="h3 mb-0">Guests Management</h1>
            <button class="btn btn-primary" data-mdb-toggle="modal" data-mdb-target="#addGuestModal">
                <i class="fas fa-plus me-1"></i>Add Guest
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100"><div class="card-body"><h4><?php echo (int)$stats['total']; ?></h4><p class="mb-0">Total Guests</p></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100"><div class="card-body"><h4><?php echo (int)$stats['active']; ?></h4><p class="mb-0">Active Guests</p></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100"><div class="card-body"><h4><?php echo (int)$stats['new_this_month']; ?></h4><p class="mb-0">New This Month</p></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark h-100"><div class="card-body"><h4><?php echo (int)$stats['high_value']; ?></h4><p class="mb-0">High Value Guests</p></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">All Guests</h5>
            <input type="text" class="form-control" id="guestSearch" placeholder="Search name/email/phone" style="max-width: 320px;">
        </div>
        <div class="card-body">
            <?php if (empty($guests)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-user-times fa-3x mb-3"></i>
                    <h5>No guests found</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="guestsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Reservations</th>
                                <th>Total Spent</th>
                                <th>Last Booking</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guests as $guest): ?>
                                <?php
                                    $statusClass = 'bg-secondary';
                                    if ($guest['guest_status'] === 'Active') {
                                        $statusClass = 'bg-success';
                                    } elseif ($guest['guest_status'] === 'New') {
                                        $statusClass = 'bg-primary';
                                    } elseif ($guest['guest_status'] === 'Inactive') {
                                        $statusClass = 'bg-warning text-dark';
                                    }
                                ?>
                                <tr data-search="<?php echo htmlspecialchars(strtolower(($guest['name'] ?? '') . ' ' . ($guest['email'] ?? '') . ' ' . ($guest['phone_no'] ?? ''))); ?>">
                                    <td>#<?php echo (int)$guest['guest_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars((string)$guest['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string)($guest['email'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($guest['phone_no'] ?? '')); ?></td>
                                    <td><span class="badge bg-info"><?php echo (int)$guest['total_reservations']; ?></span></td>
                                    <td>₹<?php echo number_format((float)$guest['total_spent'], 2); ?></td>
                                    <td><?php echo !empty($guest['last_booking']) ? htmlspecialchars(date('M d, Y', strtotime((string)$guest['last_booking']))) : '<span class="text-muted">Never</span>'; ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string)$guest['guest_status']); ?></span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info" onclick="viewGuest(<?php echo (int)$guest['guest_id']; ?>)" title="View"><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-outline-primary" onclick="editGuest(<?php echo (int)$guest['guest_id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-outline-danger" onclick="deleteGuest(<?php echo (int)$guest['guest_id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
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

<div class="modal fade" id="addGuestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="addGuestForm" onsubmit="handleAddGuest(event)">
                <div class="modal-header">
                    <h5 class="modal-title">Add Guest</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone_no" required maxlength="20"></div>
                        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div>
                        <div class="col-md-3"><label class="form-label">Age</label><input class="form-control" name="age" type="number" min="0" max="120"></div>
                        <div class="col-md-6"><label class="form-label">ID Number</label><input class="form-control" name="in_id" maxlength="20"></div>
                        <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" name="password" type="password" minlength="8"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editGuestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editGuestForm" onsubmit="handleEditGuest(event)">
                <input type="hidden" name="guest_id" id="editGuestId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Guest</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" id="editName" name="name" required maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" id="editEmail" name="email" type="email" maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" id="editPhone" name="phone_no" required maxlength="20"></div>
                        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" id="editGender" name="gender"><option value="">Select</option><option>Male</option><option>Female</option><option>Other</option></select></div>
                        <div class="col-md-3"><label class="form-label">Age</label><input class="form-control" id="editAge" name="age" type="number" min="0" max="120"></div>
                        <div class="col-md-6"><label class="form-label">ID Number</label><input class="form-control" id="editInId" name="in_id" maxlength="20"></div>
                        <div class="col-md-6"><label class="form-label">New Password (optional)</label><input class="form-control" id="editPassword" name="password" type="password" minlength="8"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" id="editAddress" name="address" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-mdb-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Guest</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewGuestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Guest Details</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewGuestBody">
                <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>
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
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(Number(value || 0));
}

function getModalInstance(id) {
    const element = document.getElementById(id);
    return element ? new mdb.Modal(element) : null;
}

async function handleAddGuest(event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target).entries());

    try {
        const response = await apiRequest('../api/admin/guests.php', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        if (response.success) {
            showToast('Guest added successfully', 'success');
            setTimeout(() => location.reload(), 900);
        }
    } catch (error) {
        showToast(error.message || 'Failed to add guest', 'danger');
    }
}

async function editGuest(guestId) {
    try {
        const response = await fetch(`../api/admin/guests.php?id=${encodeURIComponent(guestId)}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || payload.message || 'Unable to load guest');
        }

        const guest = payload.data?.guest || {};
        document.getElementById('editGuestId').value = guest.guest_id || '';
        document.getElementById('editName').value = guest.name || '';
        document.getElementById('editEmail').value = guest.email || '';
        document.getElementById('editPhone').value = guest.phone_no || '';
        document.getElementById('editGender').value = guest.gender || '';
        document.getElementById('editAge').value = guest.age ?? '';
        document.getElementById('editInId').value = guest.in_id || '';
        document.getElementById('editAddress').value = guest.address || '';
        document.getElementById('editPassword').value = '';

        const modal = getModalInstance('editGuestModal');
        if (modal) modal.show();
    } catch (error) {
        showToast(error.message || 'Failed to load guest', 'danger');
    }
}

async function handleEditGuest(event) {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target).entries());

    try {
        const response = await apiRequest('../api/admin/guests.php', {
            method: 'PUT',
            body: JSON.stringify(payload)
        });
        if (response.success) {
            showToast('Guest updated successfully', 'success');
            setTimeout(() => location.reload(), 900);
        }
    } catch (error) {
        showToast(error.message || 'Failed to update guest', 'danger');
    }
}

async function viewGuest(guestId) {
    const body = document.getElementById('viewGuestBody');
    if (!body) return;

    const modal = getModalInstance('viewGuestModal');
    if (modal) modal.show();

    body.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>';

    try {
        const response = await fetch(`../api/admin/guests.php?id=${encodeURIComponent(guestId)}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.error || payload.message || 'Unable to load guest details');
        }

        const guest = payload.data?.guest || {};
        const reservations = Array.isArray(payload.data?.reservations) ? payload.data.reservations : [];

        body.innerHTML = `
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div><strong>Name:</strong> ${escapeHtml(guest.name || '-')}</div>
                    <div><strong>Email:</strong> ${escapeHtml(guest.email || '-')}</div>
                    <div><strong>Phone:</strong> ${escapeHtml(guest.phone_no || '-')}</div>
                    <div><strong>Gender:</strong> ${escapeHtml(guest.gender || '-')}</div>
                </div>
                <div class="col-md-6">
                    <div><strong>Total Reservations:</strong> ${Number(guest.total_reservations || 0)}</div>
                    <div><strong>Total Spent:</strong> ${formatCurrency(guest.total_spent || 0)}</div>
                    <div><strong>Last Booking:</strong> ${escapeHtml(guest.last_booking || '-')}</div>
                    <div><strong>Address:</strong> ${escapeHtml(guest.address || '-')}</div>
                </div>
            </div>
            <h6 class="mb-2">Recent Reservations</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Room</th><th>Status</th><th>Check-in</th><th>Check-out</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        ${reservations.length ? reservations.map(r => `
                            <tr>
                                <td>#${r.res_id}</td>
                                <td>${escapeHtml((r.room_no || '-') + ' / ' + (r.room_type || '-'))}</td>
                                <td>${escapeHtml(r.status || '-')}</td>
                                <td>${escapeHtml(r.check_in || '-')}</td>
                                <td>${escapeHtml(r.check_out || '-')}</td>
                                <td class="text-end">${formatCurrency(r.total_price || 0)}</td>
                            </tr>
                        `).join('') : '<tr><td colspan="6" class="text-center text-muted">No reservations found</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        body.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message || 'Failed to load guest details')}</div>`;
    }
}

async function deleteGuest(guestId) {
    if (!confirm('Delete this guest? This will also remove linked reservations by cascade.')) {
        return;
    }

    try {
        const response = await apiRequest('../api/admin/guests.php', {
            method: 'DELETE',
            body: JSON.stringify({ guest_id: guestId })
        });
        if (response.success) {
            showToast('Guest deleted successfully', 'success');
            setTimeout(() => location.reload(), 900);
        }
    } catch (error) {
        showToast(error.message || 'Failed to delete guest', 'danger');
    }
}

async function logout() {
    if (!confirm('Are you sure you want to logout?')) {
        return;
    }

    try {
        await apiRequest('../api/auth/logout.php', { method: 'POST' });
        window.location.href = 'login.php';
    } catch (error) {
        showToast('Unable to logout right now. Please try again.', 'danger');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('guestSearch');
    if (!search) return;

    search.addEventListener('input', () => {
        const needle = String(search.value || '').trim().toLowerCase();
        document.querySelectorAll('#guestsTable tbody tr').forEach((row) => {
            const hay = row.getAttribute('data-search') || '';
            row.style.display = needle === '' || hay.includes(needle) ? '' : 'none';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
