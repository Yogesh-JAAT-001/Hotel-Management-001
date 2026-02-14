<?php
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../index.php?login_required=1');
}

$page_title = 'My Profile';
include '../includes/header.php';

$guest_id = $_SESSION['user_id'];

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM GUEST WHERE guest_id = ?");
    $stmt->execute([$guest_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('../index.php');
    }
    
} catch (PDOException $e) {
    error_log("Profile page error: " . $e->getMessage());
    redirect('../index.php');
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['_csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    }

    $name = sanitize($_POST['name']);
    $phone_no = sanitize($_POST['phone_no']);
    $email = sanitize($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate required fields
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($phone_no)) $errors[] = 'Phone number is required';
    if (empty($email)) $errors[] = 'Email is required';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if email is already taken by another user
    try {
        $stmt = $pdo->prepare("SELECT guest_id FROM GUEST WHERE email = ? AND guest_id != ?");
        $stmt->execute([$email, $guest_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already taken by another user';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error occurred';
    }
    
    // Password validation if changing password
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirm password do not match';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update basic info
            $stmt = $pdo->prepare("UPDATE GUEST SET name = ?, phone_no = ?, email = ? WHERE guest_id = ?");
            $stmt->execute([$name, $phone_no, $email, $guest_id]);
            
            // Update password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE GUEST SET password = ? WHERE guest_id = ?");
                $stmt->execute([$hashed_password, $guest_id]);
            }
            
            $pdo->commit();
            
            // Update session data
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM GUEST WHERE guest_id = ?");
            $stmt->execute([$guest_id]);
            $user = $stmt->fetch();
            
            $success_message = 'Profile updated successfully!';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Profile update error: " . $e->getMessage());
            $errors[] = 'Failed to update profile. Please try again.';
        }
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
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
                        <li><a class="dropdown-item" href="reservations.php">My Reservations</a></li>
                        <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Page Header -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3 luxury-header text-white">
                    My Profile
                </h1>
                <p class="lead text-light mb-4">
                    Manage your account information and preferences
                </p>
            </div>
            <div class="col-lg-4">
                <div class="text-center">
                    <i class="fas fa-user-circle fa-5x text-warning opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Profile Content -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <!-- Profile Navigation -->
            <div class="col-lg-3 mb-4">
                <div class="card card-luxury">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="bg-warning rounded-circle d-inline-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                            <h5 class="luxury-header mt-3"><?php echo htmlspecialchars($user['name']); ?></h5>
                            <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <a href="dashboard.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            <a href="reservations.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-check me-2"></i>My Reservations
                            </a>
                            <a href="profile.php" class="list-group-item list-group-item-action active">
                                <i class="fas fa-user me-2"></i>Profile Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Form -->
            <div class="col-lg-9">
                <div class="card card-luxury">
                    <div class="card-body">
                        <h4 class="luxury-header mb-4">Profile Information</h4>
                        
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="profileForm">
                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
                            <!-- Basic Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="luxury-header mb-3">Basic Information</h5>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="text" id="name" name="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        <label class="form-label" for="name">Full Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="tel" id="phone_no" name="phone_no" class="form-control" 
                                               value="<?php echo htmlspecialchars($user['phone_no']); ?>" required>
                                        <label class="form-label" for="phone_no">Phone Number</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-outline mb-4">
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                <label class="form-label" for="email">Email Address</label>
                            </div>
                            
                            <!-- Account Information -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="luxury-header mb-3">Account Information</h5>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="text" class="form-control" 
                                               value="Guest ID: <?php echo $user['guest_id']; ?>" readonly>
                                        <label class="form-label">Account ID</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="text" class="form-control" 
                                               value="Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?>" readonly>
                                        <label class="form-label">Member Since</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password Change -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="luxury-header mb-3">Change Password</h5>
                                    <p class="text-muted">Leave password fields empty if you don't want to change your password.</p>
                                </div>
                            </div>
                            
                            <div class="form-outline mb-3">
                                <input type="password" id="current_password" name="current_password" class="form-control">
                                <label class="form-label" for="current_password">Current Password</label>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="password" id="new_password" name="new_password" class="form-control">
                                        <label class="form-label" for="new_password">New Password</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-outline">
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-luxury">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Account Statistics -->
                <div class="card card-luxury mt-4">
                    <div class="card-body">
                        <h5 class="luxury-header mb-4">Account Statistics</h5>
                        
                        <?php
                        // Get user statistics
                        try {
                            $stmt = $pdo->prepare("
                                SELECT 
                                    COUNT(*) as total_reservations,
                                    COUNT(CASE WHEN status = 'Confirmed' THEN 1 END) as confirmed_reservations,
                                    COUNT(CASE WHEN status = 'Completed' THEN 1 END) as completed_stays,
                                    COALESCE(SUM(total_price), 0) as total_spent
                                FROM RESERVATION 
                                WHERE guest_id = ?
                            ");
                            $stmt->execute([$guest_id]);
                            $stats = $stmt->fetch();
                        } catch (PDOException $e) {
                            $stats = ['total_reservations' => 0, 'confirmed_reservations' => 0, 'completed_stays' => 0, 'total_spent' => 0];
                        }
                        ?>
                        
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="stats-card card p-3">
                                    <i class="fas fa-calendar-check fa-2x text-warning mb-2"></i>
                                    <h4 class="luxury-header"><?php echo $stats['total_reservations']; ?></h4>
                                    <p class="text-muted mb-0">Total Bookings</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="stats-card card p-3">
                                    <i class="fas fa-clock fa-2x text-info mb-2"></i>
                                    <h4 class="luxury-header"><?php echo $stats['confirmed_reservations']; ?></h4>
                                    <p class="text-muted mb-0">Active Bookings</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="stats-card card p-3">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <h4 class="luxury-header"><?php echo $stats['completed_stays']; ?></h4>
                                    <p class="text-muted mb-0">Completed Stays</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="stats-card card p-3">
                                    <i class="fas fa-rupee-sign fa-2x text-warning mb-2"></i>
                                    <h4 class="luxury-header">₹<?php echo number_format($stats['total_spent']); ?></h4>
                                    <p class="text-muted mb-0">Total Spent</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Form validation
document.getElementById('profileForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const currentPassword = document.getElementById('current_password').value;
    
    // If changing password, validate
    if (newPassword || confirmPassword || currentPassword) {
        if (!currentPassword) {
            e.preventDefault();
            showToast('Current password is required to change password', 'warning');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            showToast('New password and confirm password do not match', 'danger');
            return;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            showToast('New password must be at least 6 characters', 'warning');
            return;
        }
    }
});

// Logout function
async function logout() {
    if (confirm('Are you sure you want to logout?')) {
        try {
            await apiRequest('../api/auth/logout.php', { method: 'POST' });
            window.location.href = '../index.php';
        } catch (error) {
            showToast('Unable to logout right now. Please try again.', 'danger');
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>
