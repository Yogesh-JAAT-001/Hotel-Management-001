<?php
require_once '../config.php';

if (!isLoggedIn()) {
    redirect('../index.php');
}

$page_title = 'My Reservations';
include '../includes/header.php';

// Get user's reservations with more details
try {
    $stmt = $pdo->prepare("
        SELECT 
            res.*,
            r.room_no,
            r.rent as room_rent,
            rt.name as room_type,
            rest.name as reservation_type,
            rest.payment_rule,
            p.status as payment_status,
            p.payment_method,
            p.txn_id
        FROM RESERVATION res
        LEFT JOIN ROOMS r ON res.room_id = r.room_id
        LEFT JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
        JOIN RESERVATION_TYPE rest ON res.reservation_type_id = rest.reservation_type_id
        LEFT JOIN PAYMENTS p ON res.res_id = p.res_id
        WHERE res.guest_id = ?
        ORDER BY res.created_at DESC
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $reservations = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reservations error: " . $e->getMessage());
    $reservations = [];
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-luxury fixed-top">
    <div class="container">
        <a class="navbar-brand" href="../"><?php echo APP_NAME; ?></a>
        
        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
            <i class="fas fa-bars text-white"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rooms.php">Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dining.php">Dining</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_name']; ?>
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

<!-- Reservations Content -->
<div class="container" style="margin-top: 100px;">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="luxury-header">My Reservations</h1>
                <a href="rooms.php" class="btn btn-luxury">
                    <i class="fas fa-plus me-1"></i>New Booking
                </a>
            </div>
        </div>
    </div>
    
    <?php if (empty($reservations)): ?>
        <!-- No Reservations -->
        <div class="row">
            <div class="col-12">
                <div class="card card-luxury">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-times fa-5x text-muted mb-4"></i>
                        <h3 class="luxury-header text-muted">No Reservations Found</h3>
                        <p class="text-muted mb-4">You haven't made any reservations yet. Book your first room to get started!</p>
                        <a href="rooms.php" class="btn btn-luxury btn-lg">
                            <i class="fas fa-bed me-2"></i>Browse Available Rooms
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Reservations List -->
        <div class="row">
            <?php foreach ($reservations as $reservation): ?>
            <div class="col-lg-6 mb-4">
                <div class="card card-luxury h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="luxury-header mb-0">Reservation #<?php echo $reservation['res_id']; ?></h5>
                        <span class="badge <?php 
                            echo $reservation['status'] === 'Confirmed' ? 'bg-success' : 
                                ($reservation['status'] === 'Pending' ? 'bg-warning text-dark' : 
                                ($reservation['status'] === 'Cancelled' ? 'bg-danger' : 
                                ($reservation['status'] === 'Checked-in' ? 'bg-info' : 'bg-secondary'))); 
                        ?>">
                            <?php echo $reservation['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Room:</strong><br>
                                <?php if ($reservation['room_no']): ?>
                                    Room <?php echo $reservation['room_no']; ?><br>
                                    <small class="text-muted"><?php echo $reservation['room_type']; ?></small>
                                <?php else: ?>
                                    <span class="text-muted">To be assigned</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-6">
                                <strong>Type:</strong><br>
                                <?php echo $reservation['reservation_type']; ?><br>
                                <small class="text-muted"><?php echo $reservation['payment_rule']; ?></small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Check-in:</strong><br>
                                <?php echo date('M d, Y', strtotime($reservation['check_in'])); ?>
                            </div>
                            <div class="col-6">
                                <strong>Check-out:</strong><br>
                                <?php echo date('M d, Y', strtotime($reservation['check_out'])); ?>
                            </div>
                        </div>
                        
                        <?php 
                        $nights = (strtotime($reservation['check_out']) - strtotime($reservation['check_in'])) / (60 * 60 * 24);
                        ?>
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Duration:</strong><br>
                                <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>
                            </div>
                            <div class="col-6">
                                <strong>Total Amount:</strong><br>
                                <span class="text-warning fw-bold">₹<?php echo number_format($reservation['total_price'], 2); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($reservation['payment_status']): ?>
                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Payment Status:</strong>
                                <span class="badge <?php echo $reservation['payment_status'] === 'Success' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $reservation['payment_status']; ?>
                                </span>
                                <?php if ($reservation['payment_method']): ?>
                                    <small class="text-muted d-block">via <?php echo $reservation['payment_method']; ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($reservation['special_requests']): ?>
                        <div class="mb-3">
                            <strong>Special Requests:</strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($reservation['special_requests']); ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="booking-success.php?id=<?php echo $reservation['res_id']; ?>" 
                               class="btn btn-outline-info btn-sm me-2">
                                <i class="fas fa-eye me-1"></i>View Details
                            </a>
                            
                            <?php if ($reservation['status'] === 'Pending'): ?>
                            <button class="btn btn-outline-danger btn-sm" 
                                    onclick="cancelReservation(<?php echo $reservation['res_id']; ?>)">
                                <i class="fas fa-times me-1"></i>Cancel
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer text-muted small">
                        Booked on <?php echo date('M d, Y g:i A', strtotime($reservation['created_at'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Cancel reservation function
async function cancelReservation(reservationId) {
    if (confirm('Are you sure you want to cancel this reservation? This action cannot be undone.')) {
        try {
            showLoading();
            
            const response = await apiRequest('../api/reservations.php', {
                method: 'PUT',
                body: JSON.stringify({
                    reservation_id: reservationId,
                    status: 'Cancelled'
                })
            });
            
            if (response.success) {
                showToast('Reservation cancelled successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            }
        } catch (error) {
            showToast(error.message, 'danger');
        } finally {
            hideLoading();
        }
    }
}

// Logout function
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
