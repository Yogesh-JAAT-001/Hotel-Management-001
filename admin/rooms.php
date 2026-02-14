<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Rooms Management';
$additional_css = '<link rel="stylesheet" href="' . appPath('/assets/css/analytics.css') . '">';
$additional_js = ''
    . '<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>'
    . '<script src="' . appPath('/assets/js/admin-analytics.js') . '"></script>';
include '../includes/header.php';

// Get rooms with details
try {
    $stmt = $pdo->query("
        SELECT 
            r.*,
            rt.name as room_type_name,
            GROUP_CONCAT(DISTINCT rf.name) as features,
            COUNT(DISTINCT res.res_id) as total_bookings
        FROM ROOMS r
        JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
        LEFT JOIN ROOM_FEATURES_MAP rfm ON r.room_id = rfm.room_id
        LEFT JOIN ROOM_FEATURES rf ON rfm.feature_id = rf.room_feature_id
        LEFT JOIN RESERVATION res ON r.room_id = res.room_id
        GROUP BY r.room_id
        ORDER BY r.room_no
    ");
    $rooms = $stmt->fetchAll();
    
    // Get room types
    $stmt = $pdo->query("SELECT * FROM ROOM_TYPE ORDER BY room_type_id");
    $room_types = $stmt->fetchAll();
    
    // Get room features
    $stmt = $pdo->query("SELECT * FROM ROOM_FEATURES ORDER BY name");
    $room_features = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Rooms page error: " . $e->getMessage());
    $rooms = $room_types = $room_features = [];
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar-luxury collapse">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <h4 class="luxury-header text-warning"><?php echo APP_NAME; ?></h4>
                    <p class="text-light small">Admin Panel</p>
                </div>
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manager.php">
                            <i class="fas fa-briefcase me-2"></i>Manager Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="rooms.php">
                            <i class="fas fa-bed me-2"></i>Rooms
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reservations.php">
                            <i class="fas fa-calendar-check me-2"></i>Reservations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pricing.php">
                            <i class="fas fa-chart-line me-2"></i>Pricing Engine
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="guests.php">
                            <i class="fas fa-users me-2"></i>Guests
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff.php">
                            <i class="fas fa-user-tie me-2"></i>Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="food.php">
                            <i class="fas fa-utensils me-2"></i>Food & Dining
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-danger" href="#" onclick="logout()">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="luxury-header">Rooms Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-luxury" data-mdb-toggle="modal" data-mdb-target="#addRoomModal">
                        <i class="fas fa-plus me-1"></i>Add New Room
                    </button>
                </div>
            </div>

            <!-- Room Analytics -->
            <section class="analytics-section mb-4">
                <div class="section-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h4 class="mb-1"><i class="fas fa-bed me-2"></i>Room Analytics</h4>
                            <p class="small mb-0">Room type demand, floor utilization, occupancy trends, and stay duration intelligence</p>
                        </div>
                        <div class="export-row">
                            <button class="btn btn-outline-light btn-sm" type="button" onclick="exportRoomAnalytics('rooms', 'csv')">
                                <i class="fas fa-file-csv me-1"></i>CSV
                            </button>
                            <button class="btn btn-outline-light btn-sm" type="button" onclick="exportRoomAnalytics('rooms', 'xls')">
                                <i class="fas fa-file-excel me-1"></i>Excel
                            </button>
                            <button class="btn btn-outline-light btn-sm" type="button" onclick="downloadRoomPdf()">
                                <i class="fas fa-file-pdf me-1"></i>PDF
                            </button>
                        </div>
                    </div>
                </div>

                <div class="analytics-toolbar">
                    <div class="field field-sm">
                        <label class="form-label" for="roomFromDate">From Date</label>
                        <input type="date" id="roomFromDate" class="form-control">
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="roomToDate">To Date</label>
                        <input type="date" id="roomToDate" class="form-control">
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="roomMonth">Month</label>
                        <select id="roomMonth" class="form-select">
                            <option value="">All</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>"><?php echo date('M', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field field-sm">
                        <label class="form-label" for="roomYear">Year</label>
                        <select id="roomYear" class="form-select">
                            <option value="">All</option>
                            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="actions">
                        <button class="btn btn-luxury btn-sm" type="button" onclick="loadRoomAnalytics()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="resetRoomFilters()">
                            <i class="fas fa-rotate-left me-1"></i>Reset
                        </button>
                    </div>
                </div>

                <div class="analytics-grid cols-4">
                    <article class="analytics-kpi">
                        <div class="label">Most Booked Room Type</div>
                        <div class="value" id="roomMostBookedType">N/A</div>
                        <div class="meta">Top demand category</div>
                    </article>
                    <article class="analytics-kpi">
                        <div class="label">Total Bookings</div>
                        <div class="value" id="roomTotalBookings">0</div>
                        <div class="meta">Across selected period</div>
                    </article>
                    <article class="analytics-kpi">
                        <div class="label">Average Stay Duration</div>
                        <div class="value" id="roomAvgStay">0 nights</div>
                        <div class="meta">Average nights per booking</div>
                    </article>
                    <article class="analytics-kpi">
                        <div class="label">Repeat Stay Duration</div>
                        <div class="value" id="roomRepeatStay">0 nights</div>
                        <div class="meta">Returning guest average</div>
                    </article>
                </div>

                <div class="analytics-grid cols-2 pt-0">
                    <article class="analytics-card">
                        <div class="card-head">
                            <h6 class="mb-0">Most Booked Room Types</h6>
                            <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('roomTypeChart', 'room-type-demand.png')">
                                <i class="fas fa-image me-1"></i>PNG
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-shell"><canvas id="roomTypeDemandChart"></canvas></div>
                        </div>
                    </article>
                    <article class="analytics-card">
                        <div class="card-head">
                            <h6 class="mb-0">Floor Utilization</h6>
                            <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('roomFloorChart', 'room-floor-utilization.png')">
                                <i class="fas fa-image me-1"></i>PNG
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-shell"><canvas id="roomFloorUtilizationChart"></canvas></div>
                        </div>
                    </article>
                </div>

                <div class="analytics-grid cols-2 pt-0">
                    <article class="analytics-card">
                        <div class="card-head">
                            <h6 class="mb-0">Monthly Occupancy Trend</h6>
                            <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('roomOccupancyChart', 'room-occupancy-trend.png')">
                                <i class="fas fa-image me-1"></i>PNG
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-shell chart-sm"><canvas id="roomOccupancyTrendChart"></canvas></div>
                        </div>
                    </article>
                    <article class="analytics-card">
                        <div class="card-head">
                            <h6 class="mb-0">Yearly Occupancy Comparison</h6>
                            <button class="btn btn-outline-primary btn-sm" type="button" onclick="AdminAnalytics.downloadChartPng('roomYearlyChart', 'room-yearly-occupancy.png')">
                                <i class="fas fa-image me-1"></i>PNG
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="chart-shell chart-sm"><canvas id="roomYearlyComparisonChart"></canvas></div>
                        </div>
                    </article>
                </div>

                <div class="analytics-grid pt-0">
                    <article class="analytics-card">
                        <div class="card-head">
                            <h6 class="mb-0">Floor-Wise Bookings & Availability</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table analytics-table table-sm" id="roomFloorTable">
                                    <thead>
                                        <tr>
                                            <th>Floor</th>
                                            <th class="text-end">Total Rooms</th>
                                            <th class="text-end">Available</th>
                                            <th class="text-end">Booked</th>
                                            <th class="text-end">Bookings</th>
                                            <th class="text-end">Utilization</th>
                                            <th class="text-end">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div id="roomAnalyticsEmpty" class="mt-3" style="display:none;"></div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Rooms Table -->
            <div class="card card-luxury">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="roomsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Room No</th>
                                    <th>Type</th>
                                    <th>Tier</th>
                                    <th>Rent/Night</th>
                                    <th>Status</th>
                                    <th>Features</th>
                                    <th>Bookings</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $room['room_no']; ?></strong>
                                        <?php if ($room['image_path']): ?>
                                            <br><small class="text-muted">Has image</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $room['room_type_name']; ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $room['tier']; ?> TIER</span>
                                    </td>
                                    <td>₹<?php echo number_format($room['rent'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $room['status'] === 'Available' ? 'bg-success' : ($room['status'] === 'Occupied' ? 'bg-danger' : 'bg-warning'); ?>">
                                            <?php echo $room['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($room['features']): ?>
                                            <?php foreach (array_slice(explode(',', $room['features']), 0, 3) as $feature): ?>
                                                <span class="badge bg-light text-dark me-1"><?php echo trim($feature); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count(explode(',', $room['features'])) > 3): ?>
                                                <small class="text-muted">+<?php echo count(explode(',', $room['features'])) - 3; ?> more</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $room['total_bookings']; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editRoom(<?php echo $room['room_id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewRoom(<?php echo $room['room_id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteRoom(<?php echo $room['room_id']; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRoomForm" onsubmit="handleAddRoom(event)" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="text" id="roomNo" name="room_no" class="form-control" required>
                                <label class="form-label" for="roomNo">Room Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <select id="roomType" name="room_type_id" class="form-select" required>
                                    <option value="">Select Room Type</option>
                                    <?php foreach ($room_types as $type): ?>
                                        <option value="<?php echo $type['room_type_id']; ?>"><?php echo $type['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label" for="roomType">Room Type</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <select id="tier" name="tier" class="form-select" required>
                                    <option value="">Select Tier</option>
                                    <option value="1">1 TIER (Luxury)</option>
                                    <option value="2">2 TIER (Deluxe)</option>
                                    <option value="3">3 TIER (Standard)</option>
                                </select>
                                <label class="form-label" for="tier">Tier</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="number" id="rent" name="rent" class="form-control" step="0.01" required>
                                <label class="form-label" for="rent">Rent per Night (₹)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-outline mb-3">
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                        <label class="form-label" for="description">Description</label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Features</label>
                        <div class="row">
                            <?php foreach ($room_features as $feature): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="<?php echo $feature['room_feature_id']; ?>" id="feature<?php echo $feature['room_feature_id']; ?>">
                                    <label class="form-check-label" for="feature<?php echo $feature['room_feature_id']; ?>">
                                        <i class="<?php echo $feature['icon']; ?> me-1"></i><?php echo $feature['name']; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="roomImage" class="form-label">Room Image</label>
                        <input type="file" id="roomImage" name="image" class="form-control" accept="image/*" onchange="previewImage(this, document.getElementById('imagePreview'))">
                        <img id="imagePreview" class="image-preview" style="display: none;">
                    </div>
                    
                    <button type="submit" class="btn btn-luxury">Add Room</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRoomForm" onsubmit="handleEditRoom(event)" enctype="multipart/form-data">
                    <input type="hidden" id="editRoomId" name="room_id">
                    <!-- Similar form fields as add room -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="text" id="editRoomNo" name="room_no" class="form-control" required>
                                <label class="form-label" for="editRoomNo">Room Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <select id="editRoomType" name="room_type_id" class="form-select" required>
                                    <option value="">Select Room Type</option>
                                    <?php foreach ($room_types as $type): ?>
                                        <option value="<?php echo $type['room_type_id']; ?>"><?php echo $type['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label class="form-label" for="editRoomType">Room Type</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-outline mb-3">
                                <select id="editTier" name="tier" class="form-select" required>
                                    <option value="">Select Tier</option>
                                    <option value="1">1 TIER (Luxury)</option>
                                    <option value="2">2 TIER (Deluxe)</option>
                                    <option value="3">3 TIER (Standard)</option>
                                </select>
                                <label class="form-label" for="editTier">Tier</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-outline mb-3">
                                <input type="number" id="editRent" name="rent" class="form-control" step="0.01" required>
                                <label class="form-label" for="editRent">Rent per Night (₹)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-outline mb-3">
                                <select id="editStatus" name="status" class="form-select" required>
                                    <option value="Available">Available</option>
                                    <option value="Occupied">Occupied</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Reserved">Reserved</option>
                                </select>
                                <label class="form-label" for="editStatus">Status</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-outline mb-3">
                        <textarea id="editDescription" name="description" class="form-control" rows="3"></textarea>
                        <label class="form-label" for="editDescription">Description</label>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Features</label>
                        <div class="row" id="editFeatures">
                            <?php foreach ($room_features as $feature): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="features[]" value="<?php echo $feature['room_feature_id']; ?>" id="editFeature<?php echo $feature['room_feature_id']; ?>">
                                    <label class="form-check-label" for="editFeature<?php echo $feature['room_feature_id']; ?>">
                                        <i class="<?php echo $feature['icon']; ?> me-1"></i><?php echo $feature['name']; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editRoomImage" class="form-label">Room Image</label>
                        <input type="file" id="editRoomImage" name="image" class="form-control" accept="image/*" onchange="previewImage(this, document.getElementById('editImagePreview'))">
                        <img id="editImagePreview" class="image-preview" style="display: none;">
                        <div id="currentImage"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-luxury">Update Room</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function setRoomFilterDefaults() {
    const today = new Date();
    const from = new Date(today.getFullYear(), today.getMonth() - 11, 1);
    document.getElementById('roomFromDate').value = from.toISOString().split('T')[0];
    document.getElementById('roomToDate').value = today.toISOString().split('T')[0];
}

function getRoomFilters() {
    return AdminAnalytics.parseFilters('room');
}

function renderRoomFloorTable(rows) {
    const tbody = document.querySelector('#roomFloorTable tbody');
    if (!tbody) return;

    if (!rows || rows.length === 0) {
        tbody.innerHTML = '';
        return;
    }

    tbody.innerHTML = rows.map((row) => `
        <tr>
            <td>${row.wing_label || ('Floor ' + row.floor)}</td>
            <td class="text-end">${row.total_rooms}</td>
            <td class="text-end">${row.available_rooms}</td>
            <td class="text-end">${row.booked_rooms}</td>
            <td class="text-end">${row.booking_count}</td>
            <td class="text-end">${row.utilization_pct}%</td>
            <td class="text-end">${AdminAnalytics.toCurrency(row.revenue)}</td>
        </tr>
    `).join('');
}

async function loadRoomAnalytics() {
    try {
        const filters = getRoomFilters();
        const payload = await AdminAnalytics.fetchAnalytics('rooms', filters);
        const data = payload.data || {};
        const summary = data.summary || {};
        const chart = data.chart || {};

        AdminAnalytics.setText('roomMostBookedType', summary.most_booked_room_type || 'N/A');
        AdminAnalytics.setText('roomTotalBookings', Number(summary.total_bookings || 0).toLocaleString('en-IN'));
        AdminAnalytics.setText('roomAvgStay', `${Number(summary.avg_stay_nights || 0).toFixed(2)} nights`);
        AdminAnalytics.setText('roomRepeatStay', `${Number(summary.repeat_avg_stay_nights || 0).toFixed(2)} nights`);

        renderRoomFloorTable(data.floor_utilization || []);

        if (data.empty) {
            AdminAnalytics.showEmptyState(
                'roomAnalyticsEmpty',
                'No room booking data found for selected range.',
                'Refresh',
                loadRoomAnalytics
            );
            document.getElementById('roomAnalyticsEmpty').style.display = 'block';
        } else {
            document.getElementById('roomAnalyticsEmpty').style.display = 'none';
        }

        AdminAnalytics.renderChart('roomTypeChart', 'roomTypeDemandChart', {
            data: {
                labels: chart.room_type_labels || [],
                datasets: [
                    {
                        type: 'bar',
                        label: 'Bookings',
                        data: chart.room_type_bookings || [],
                        backgroundColor: 'rgba(212, 175, 55, 0.82)',
                        borderRadius: 8,
                        yAxisID: 'y'
                    },
                    {
                        type: 'line',
                        label: 'Occupancy %',
                        data: chart.room_type_occupancy || [],
                        borderColor: '#0F1B2D',
                        backgroundColor: 'rgba(15, 27, 45, 0.16)',
                        fill: false,
                        tension: 0.25,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
                }
            }
        });

        AdminAnalytics.renderChart('roomFloorChart', 'roomFloorUtilizationChart', {
            type: 'bar',
            data: {
                labels: chart.floor_labels || [],
                datasets: [
                    {
                        label: 'Available',
                        data: chart.floor_available || [],
                        backgroundColor: 'rgba(46, 139, 87, 0.75)'
                    },
                    {
                        label: 'Booked',
                        data: chart.floor_booked || [],
                        backgroundColor: 'rgba(178, 34, 34, 0.75)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        AdminAnalytics.renderChart('roomOccupancyChart', 'roomOccupancyTrendChart', {
            type: 'line',
            data: {
                labels: chart.occupancy_month_labels || [],
                datasets: [{
                    label: 'Occupancy %',
                    data: chart.occupancy_month_values || [],
                    borderColor: '#D4AF37',
                    backgroundColor: 'rgba(212, 175, 55, 0.22)',
                    fill: true,
                    tension: 0.28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });

        AdminAnalytics.renderChart('roomYearlyChart', 'roomYearlyComparisonChart', {
            type: 'bar',
            data: {
                labels: chart.year_labels || [],
                datasets: [{
                    label: 'Yearly Occupancy %',
                    data: chart.year_occupancy || [],
                    backgroundColor: 'rgba(15, 27, 45, 0.8)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });
    } catch (error) {
        showToast(error.message || 'Failed to load room analytics', 'danger');
    }
}

function resetRoomFilters() {
    document.getElementById('roomMonth').value = '';
    document.getElementById('roomYear').value = '';
    setRoomFilterDefaults();
    loadRoomAnalytics();
}

function exportRoomAnalytics(type, format) {
    AdminAnalytics.exportAnalytics(type, getRoomFilters(), format);
}

function downloadRoomPdf() {
    const lines = [
        `Most Booked Room Type: ${document.getElementById('roomMostBookedType').textContent}`,
        `Total Bookings: ${document.getElementById('roomTotalBookings').textContent}`,
        `Average Stay Duration: ${document.getElementById('roomAvgStay').textContent}`,
        `Repeat Customer Stay: ${document.getElementById('roomRepeatStay').textContent}`
    ];
    AdminAnalytics.downloadSimplePdf('Room Analytics Report', lines, 'room-analytics-report.pdf');
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.AdminAnalytics) {
        showToast('Analytics module failed to load. Please refresh the page.', 'danger');
        return;
    }
    setRoomFilterDefaults();
    loadRoomAnalytics();

    ['roomFromDate', 'roomToDate', 'roomMonth', 'roomYear'].forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', loadRoomAnalytics);
    });
});

// Add room
async function handleAddRoom(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    try {
        // First upload image if provided
        let imagePath = null;
        const imageFile = formData.get('image');
        if (imageFile && imageFile.size > 0) {
            const imageFormData = new FormData();
            imageFormData.append('image', imageFile);
            imageFormData.append('type', 'room');
            
            const imageResponse = await fetch('../api/upload-image.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: imageFormData
            });
            
            if (imageResponse.ok) {
                const imageResult = await imageResponse.json();
                if (imageResult.success) {
                    imagePath = imageResult.data.filepath;
                }
            }
        }
        
        // Prepare room data
        const roomData = {
            room_no: formData.get('room_no'),
            room_type_id: formData.get('room_type_id'),
            tier: formData.get('tier'),
            rent: formData.get('rent'),
            description: formData.get('description'),
            image_path: imagePath,
            features: formData.getAll('features')
        };
        
        const response = await apiRequest('../api/admin/rooms.php', {
            method: 'POST',
            body: JSON.stringify(roomData)
        });
        
        if (response.success) {
            showToast('Room added successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

// Edit room
function editRoom(roomId) {
    // Fetch room data and populate edit form
    fetch(`../api/admin/rooms.php?id=${roomId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const room = data.data;
                document.getElementById('editRoomId').value = room.room_id;
                document.getElementById('editRoomNo').value = room.room_no;
                document.getElementById('editRoomType').value = room.room_type_id;
                document.getElementById('editTier').value = room.tier;
                document.getElementById('editRent').value = room.rent;
                document.getElementById('editStatus').value = room.status;
                document.getElementById('editDescription').value = room.description || '';
                
                // Set features
                const featureCheckboxes = document.querySelectorAll('#editFeatures input[type="checkbox"]');
                featureCheckboxes.forEach(checkbox => {
                    checkbox.checked = room.feature_ids && room.feature_ids.includes(parseInt(checkbox.value));
                });
                
                // Show current image
                if (room.image_path) {
                    document.getElementById('currentImage').innerHTML = `
                        <div class="mt-2">
                            <label class="form-label">Current Image:</label><br>
                            <img src="${room.image_url}" class="image-preview">
                        </div>
                    `;
                }
                
                const modal = new mdb.Modal(document.getElementById('editRoomModal'));
                modal.show();
            }
        })
        .catch(error => {
            showToast('Failed to load room data', 'danger');
        });
}

// Handle edit room form
async function handleEditRoom(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    try {
        // Handle image upload if new image provided
        let imagePath = null;
        const imageFile = formData.get('image');
        if (imageFile && imageFile.size > 0) {
            const imageFormData = new FormData();
            imageFormData.append('image', imageFile);
            imageFormData.append('type', 'room');
            
            const imageResponse = await fetch('../api/upload-image.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: imageFormData
            });
            
            if (imageResponse.ok) {
                const imageResult = await imageResponse.json();
                if (imageResult.success) {
                    imagePath = imageResult.data.filepath;
                }
            }
        }
        
        // Prepare room data
        const roomData = {
            room_id: formData.get('room_id'),
            room_no: formData.get('room_no'),
            room_type_id: formData.get('room_type_id'),
            tier: formData.get('tier'),
            rent: formData.get('rent'),
            status: formData.get('status'),
            description: formData.get('description'),
            features: formData.getAll('features')
        };
        
        if (imagePath) {
            roomData.image_path = imagePath;
        }
        
        const response = await apiRequest('../api/admin/rooms.php', {
            method: 'PUT',
            body: JSON.stringify(roomData)
        });
        
        if (response.success) {
            showToast('Room updated successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

// View room details
function viewRoom(roomId) {
    window.open(`../user/room-detail.php?id=${roomId}`, '_blank');
}

// Delete room
async function deleteRoom(roomId) {
    if (confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
        try {
            const response = await apiRequest('../api/admin/rooms.php', {
                method: 'DELETE',
                body: JSON.stringify({ room_id: roomId })
            });
            
            if (response.success) {
                showToast('Room deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        } catch (error) {
            showToast(error.message, 'danger');
        }
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
