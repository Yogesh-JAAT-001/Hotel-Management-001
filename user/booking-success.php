<?php
require_once '../config.php';

$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$page_title = 'Booking Confirmation';
include '../includes/header.php';

// Get reservation details if ID provided
$reservation = null;
if ($reservation_id && isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                res.*,
                g.name as guest_name,
                g.phone_no,
                g.email,
                r.room_no,
                rt.name as room_type,
                rest.name as reservation_type
            FROM RESERVATION res
            JOIN GUEST g ON res.guest_id = g.guest_id
            LEFT JOIN ROOMS r ON res.room_id = r.room_id
            LEFT JOIN ROOM_TYPE rt ON r.room_type_id = rt.room_type_id
            JOIN RESERVATION_TYPE rest ON res.reservation_type_id = rest.reservation_type_id
            WHERE res.res_id = ? AND res.guest_id = ?
        ");
        
        $stmt->execute([$reservation_id, $_SESSION['user_id']]);
        $reservation = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Booking confirmation error: " . $e->getMessage());
    }
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
                <?php if (isLoggedIn()): ?>
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
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-mdb-toggle="modal" data-mdb-target="#loginModal">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-mdb-toggle="modal" data-mdb-target="#registerModal">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Booking Confirmation Content -->
<div class="container" style="margin-top: 100px;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($reservation): ?>
                <!-- Success Message -->
                <div class="text-center mb-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h1 class="luxury-header text-success mb-3">Booking Confirmed!</h1>
                    <p class="lead text-muted">Thank you for choosing <?php echo APP_NAME; ?>. Your reservation has been successfully created.</p>
                </div>

                <!-- Booking Details -->
                <div class="card card-luxury mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check me-2"></i>Reservation Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="luxury-header mb-3">Booking Information</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Reservation ID:</strong></td>
                                        <td>#<?php echo $reservation['res_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Guest Name:</strong></td>
                                        <td><?php echo htmlspecialchars($reservation['guest_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo htmlspecialchars($reservation['phone_no']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo htmlspecialchars($reservation['email']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="luxury-header mb-3">Stay Details</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Room:</strong></td>
                                        <td><?php echo $reservation['room_no'] ? 'Room ' . $reservation['room_no'] : 'To be assigned'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Room Type:</strong></td>
                                        <td><?php echo htmlspecialchars($reservation['room_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Check-in:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($reservation['check_in'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Check-out:</strong></td>
                                        <td><?php echo date('M d, Y', strtotime($reservation['check_out'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Reservation Type:</strong></td>
                                        <td><?php echo htmlspecialchars($reservation['reservation_type']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="luxury-header">Status</h6>
                                <span class="badge badge-luxury"><?php echo $reservation['status']; ?></span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h6 class="luxury-header">Total Amount</h6>
                                <h4 class="text-warning mb-0">₹<?php echo number_format($reservation['total_price'], 2); ?></h4>
                            </div>
                        </div>
                        
                        <?php if ($reservation['special_requests']): ?>
                        <hr>
                        <div>
                            <h6 class="luxury-header">Special Requests</h6>
                            <p class="text-muted"><?php echo htmlspecialchars($reservation['special_requests']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Next Steps -->
                <div class="card card-luxury mb-4">
                    <div class="card-body">
                        <h5 class="luxury-header mb-3">What's Next?</h5>
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-envelope fa-2x text-warning mb-2"></i>
                                <h6>Confirmation Email</h6>
                                <p class="small text-muted">You'll receive a confirmation email shortly with all the details.</p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-phone fa-2x text-warning mb-2"></i>
                                <h6>Contact Us</h6>
                                <p class="small text-muted">Call us at +91-11-12345678 for any questions or changes.</p>
                            </div>
                            <div class="col-md-4 text-center mb-3">
                                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                                <h6>Check-in Time</h6>
                                <p class="small text-muted">Check-in starts at 3:00 PM on your arrival date.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <a href="../" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-home me-1"></i>Back to Home
                    </a>
                    <a href="rooms.php" class="btn btn-outline-info me-2">
                        <i class="fas fa-bed me-1"></i>Browse More Rooms
                    </a>
                    <button class="btn btn-luxury" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Print Confirmation
                    </button>
                </div>

            <?php else: ?>
                <!-- No Reservation Found -->
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle fa-5x text-warning"></i>
                    </div>
                    <h1 class="luxury-header mb-3">Booking Not Found</h1>
                    <p class="lead text-muted mb-4">We couldn't find the reservation you're looking for.</p>
                    <div>
                        <a href="../" class="btn btn-luxury me-2">
                            <i class="fas fa-home me-1"></i>Back to Home
                        </a>
                        <a href="rooms.php" class="btn btn-outline-info">
                            <i class="fas fa-bed me-1"></i>Browse Rooms
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Logout function
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            location.reload();
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
