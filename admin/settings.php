<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login.php');
}

$page_title = 'Settings';
include '../includes/header.php';

// Get hotel settings
try {
    $stmt = $pdo->query("SELECT * FROM HOTEL LIMIT 1");
    $hotel = $stmt->fetch();
    
    // Get room types
    $stmt = $pdo->query("SELECT * FROM ROOM_TYPE ORDER BY name");
    $room_types = $stmt->fetchAll();
    
    // Get departments (schema varies: dept_id/name vs dep_id/dep_name)
    $deptPkColumn = dbFirstExistingColumn($pdo, 'DEPARTMENT', ['dept_id', 'dep_id']) ?? 'dept_id';
    $deptNameColumn = dbFirstExistingColumn($pdo, 'DEPARTMENT', ['name', 'dep_name']) ?? 'name';
    $hasDeptDescription = dbHasColumn($pdo, 'DEPARTMENT', 'description');

    $departmentSql = "SELECT {$deptPkColumn} AS dept_id, {$deptNameColumn} AS name";
    $departmentSql .= $hasDeptDescription ? ", description" : ", '' AS description";
    $departmentSql .= " FROM DEPARTMENT ORDER BY {$deptNameColumn}";
    $stmt = $pdo->query($departmentSql);
    $departments = $stmt->fetchAll();
    
    // Get reservation types
    $stmt = $pdo->query("SELECT * FROM RESERVATION_TYPE ORDER BY name");
    $reservation_types = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
    $hotel = null;
    $room_types = [];
    $departments = [];
    $reservation_types = [];
}
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
                    <a class="nav-link" href="reservations.php">
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
                    <a class="nav-link active" href="settings.php">
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
            <h1 class="h3 mb-4">Settings</h1>
        </div>
    </div>
    
    <!-- Settings Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="hotel-tab" data-mdb-toggle="tab" data-mdb-target="#hotel" type="button" role="tab">
                        <i class="fas fa-hotel me-1"></i>Hotel Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="room-types-tab" data-mdb-toggle="tab" data-mdb-target="#room-types" type="button" role="tab">
                        <i class="fas fa-bed me-1"></i>Room Types
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="departments-tab" data-mdb-toggle="tab" data-mdb-target="#departments" type="button" role="tab">
                        <i class="fas fa-building me-1"></i>Departments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reservation-types-tab" data-mdb-toggle="tab" data-mdb-target="#reservation-types" type="button" role="tab">
                        <i class="fas fa-calendar me-1"></i>Reservation Types
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-mdb-toggle="tab" data-mdb-target="#system" type="button" role="tab">
                        <i class="fas fa-cogs me-1"></i>System Settings
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabContent">
                <!-- Hotel Information Tab -->
                <div class="tab-pane fade show active" id="hotel" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Hotel Information</h5>
                        </div>
                        <div class="card-body">
                            <form id="hotelForm" onsubmit="handleHotelUpdate(event)">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-outline mb-3">
                                            <input type="text" id="hotelName" name="name" class="form-control" 
                                                   value="<?php echo $hotel ? htmlspecialchars($hotel['name']) : ''; ?>" required>
                                            <label class="form-label" for="hotelName">Hotel Name</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-outline mb-3">
                                            <input type="email" id="hotelEmail" name="email" class="form-control" 
                                                   value="<?php echo $hotel ? htmlspecialchars($hotel['email'] ?? '') : ''; ?>" required>
                                            <label class="form-label" for="hotelEmail">Email</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-outline mb-3">
                                            <input type="tel" id="hotelPhone" name="phone_no" class="form-control" 
                                                   value="<?php echo $hotel ? htmlspecialchars($hotel['phone_no'] ?? '') : ''; ?>" required>
                                            <label class="form-label" for="hotelPhone">Phone Number</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-outline mb-3">
                                            <input type="url" id="hotelWebsite" name="website" class="form-control" 
                                                   value="<?php echo $hotel ? htmlspecialchars($hotel['website'] ?? '') : ''; ?>">
                                            <label class="form-label" for="hotelWebsite">Website</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-outline mb-3">
                                    <textarea id="hotelAddress" name="address" class="form-control" rows="3" required><?php echo $hotel ? htmlspecialchars($hotel['address'] ?? ($hotel['location'] ?? '')) : ''; ?></textarea>
                                    <label class="form-label" for="hotelAddress">Address</label>
                                </div>
                                
                                <div class="form-outline mb-3">
                                    <textarea id="hotelDescription" name="description" class="form-control" rows="4"><?php echo $hotel ? htmlspecialchars($hotel['description'] ?? '') : ''; ?></textarea>
                                    <label class="form-label" for="hotelDescription">Description</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Hotel Information</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Room Types Tab -->
                <div class="tab-pane fade" id="room-types" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Room Types</h5>
                            <button class="btn btn-primary btn-sm" data-mdb-toggle="modal" data-mdb-target="#addRoomTypeModal">
                                <i class="fas fa-plus me-1"></i>Add Room Type
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($room_types as $type): ?>
                                        <tr>
                                            <td><?php echo $type['room_type_id']; ?></td>
                                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                                            <td><?php echo htmlspecialchars($type['description']); ?></td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick="editRoomType(<?php echo $type['room_type_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteRoomType(<?php echo $type['room_type_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Departments Tab -->
                <div class="tab-pane fade" id="departments" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Departments</h5>
                            <button class="btn btn-primary btn-sm" data-mdb-toggle="modal" data-mdb-target="#addDepartmentModal">
                                <i class="fas fa-plus me-1"></i>Add Department
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><?php echo $dept['dept_id']; ?></td>
                                            <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                            <td><?php echo htmlspecialchars($dept['description']); ?></td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick="editDepartment(<?php echo $dept['dept_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteDepartment(<?php echo $dept['dept_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reservation Types Tab -->
                <div class="tab-pane fade" id="reservation-types" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Reservation Types</h5>
                            <button class="btn btn-primary btn-sm" data-mdb-toggle="modal" data-mdb-target="#addReservationTypeModal">
                                <i class="fas fa-plus me-1"></i>Add Reservation Type
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Payment Rule</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reservation_types as $res_type): ?>
                                        <tr>
                                            <td><?php echo $res_type['reservation_type_id']; ?></td>
                                            <td><?php echo htmlspecialchars($res_type['name']); ?></td>
                                            <td><?php echo htmlspecialchars($res_type['payment_rule']); ?></td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm" onclick="editReservationType(<?php echo $res_type['reservation_type_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm" onclick="deleteReservationType(<?php echo $res_type['reservation_type_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Settings Tab -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">System Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Application Settings</h6>
                                    <div class="form-outline mb-3">
                                        <input type="text" id="appName" class="form-control" value="<?php echo APP_NAME; ?>" readonly>
                                        <label class="form-label" for="appName">Application Name</label>
                                    </div>
                                    <div class="form-outline mb-3">
                                        <input type="url" id="appUrl" class="form-control" value="<?php echo APP_URL; ?>" readonly>
                                        <label class="form-label" for="appUrl">Application URL</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Database Settings</h6>
                                    <div class="form-outline mb-3">
                                        <input type="text" id="dbHost" class="form-control" value="<?php echo DB_HOST; ?>" readonly>
                                        <label class="form-label" for="dbHost">Database Host</label>
                                    </div>
                                    <div class="form-outline mb-3">
                                        <input type="text" id="dbName" class="form-control" value="<?php echo DB_NAME; ?>" readonly>
                                        <label class="form-label" for="dbName">Database Name</label>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-12">
                                    <h6>System Actions</h6>
                                    <div class="btn-group">
                                        <button class="btn btn-outline-info" onclick="clearCache()">
                                            <i class="fas fa-broom me-1"></i>Clear Cache
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="backupDatabase()">
                                            <i class="fas fa-download me-1"></i>Backup Database
                                        </button>
                                        <button class="btn btn-outline-success" onclick="checkUpdates()">
                                            <i class="fas fa-sync me-1"></i>Check Updates
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Hotel information update
async function handleHotelUpdate(event) {
    event.preventDefault();
    showToast('Hotel information update functionality coming soon', 'info');
}

// Room type functions
function editRoomType(id) {
    showToast('Edit room type functionality coming soon', 'info');
}

function deleteRoomType(id) {
    if (confirm('Are you sure you want to delete this room type?')) {
        showToast('Delete room type functionality coming soon', 'warning');
    }
}

// Department functions
function editDepartment(id) {
    showToast('Edit department functionality coming soon', 'info');
}

function deleteDepartment(id) {
    if (confirm('Are you sure you want to delete this department?')) {
        showToast('Delete department functionality coming soon', 'warning');
    }
}

// Reservation type functions
function editReservationType(id) {
    showToast('Edit reservation type functionality coming soon', 'info');
}

function deleteReservationType(id) {
    if (confirm('Are you sure you want to delete this reservation type?')) {
        showToast('Delete reservation type functionality coming soon', 'warning');
    }
}

// System functions
function clearCache() {
    showToast('Clear cache functionality coming soon', 'info');
}

function backupDatabase() {
    showToast('Database backup functionality coming soon', 'info');
}

function checkUpdates() {
    showToast('Check updates functionality coming soon', 'info');
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
