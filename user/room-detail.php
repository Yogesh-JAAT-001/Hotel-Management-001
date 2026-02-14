<?php
require_once '../config.php';

$room_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$room_id) {
    redirect(appPath('/user/rooms.php'));
}

$page_title = 'Room Details';
include '../includes/header.php';
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-luxury fixed-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo appPath('/'); ?>"><?php echo APP_NAME; ?></a>
        
        <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
            <i class="fas fa-bars text-white"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo appPath('/'); ?>">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo appPath('/user/rooms.php'); ?>">Rooms</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo appPath('/user/dining.php'); ?>">Dining</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo appPath('/user/contact.php'); ?>">Contact</a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo appPath('/user/dashboard.php'); ?>">Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?php echo appPath('/user/reservations.php'); ?>">My Reservations</a></li>
                            <li><a class="dropdown-item" href="<?php echo appPath('/user/profile.php'); ?>">Profile</a></li>
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

<!-- Room Detail Content -->
<div class="container-fluid p-0" style="margin-top: 76px;">
    <!-- Loading State -->
    <div id="loadingState" class="text-center py-5">
        <div class="spinner-border text-warning" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading room details...</p>
    </div>
    
    <!-- Room Detail Content -->
    <div id="roomContent" style="display: none;">
        <!-- Room Image Hero -->
        <section class="position-relative" style="height: 60vh; overflow: hidden;">
            <img id="roomHeroImage" src="" alt="Room Image" class="w-100 h-100" style="object-fit: cover;" onerror="this.onerror=null;this.src='<?php echo appPath('/assets/images/rooms/suite/suite-room.png'); ?>';">
            <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-25"></div>
            <div class="position-absolute bottom-0 start-0 p-4 text-white">
                <div class="container">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb text-white">
                            <li class="breadcrumb-item"><a href="<?php echo appPath('/'); ?>" class="text-white text-decoration-none">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo appPath('/user/rooms.php'); ?>" class="text-white text-decoration-none">Rooms</a></li>
                            <li class="breadcrumb-item active text-warning" id="breadcrumbRoom">Room</li>
                        </ol>
                    </nav>
                    <h1 class="display-4 fw-bold luxury-header" id="roomTitle">Room</h1>
                    <p class="lead" id="roomSubtitle">Luxury accommodation</p>
                </div>
            </div>
        </section>
        
        <!-- Room Details -->
        <section class="py-5">
            <div class="container">
                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- Room Info -->
                        <div class="card card-luxury mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h3 class="luxury-header mb-3" id="roomName">Room Name</h3>
                                        <p class="text-muted mb-3" id="roomDescription">Room description will appear here.</p>
                                        
                                        <div class="row mb-3">
                                            <div class="col-sm-6">
                                                <strong>Room Type:</strong>
                                                <span id="roomType" class="text-muted">-</span>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Tier:</strong>
                                                <span id="roomTier" class="badge bg-info">-</span>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-sm-6">
                                                <strong>Status:</strong>
                                                <span id="roomStatus" class="badge">-</span>
                                            </div>
                                            <div class="col-sm-6">
                                                <strong>Hotel:</strong>
                                                <span id="hotelName" class="text-muted">-</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <div class="text-center">
                                            <h2 class="text-warning mb-0" id="roomPrice">₹0</h2>
                                            <small class="text-muted">per night</small>
                                            <div class="mt-2">
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <i class="fas fa-star text-warning"></i>
                                                <span class="text-muted ms-1" id="hotelRating">5 Star</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Room Features -->
                        <div class="card card-luxury mb-4">
                            <div class="card-body">
                                <h4 class="luxury-header mb-3">Room Features & Amenities</h4>
                                <div class="row" id="roomFeatures">
                                    <!-- Features will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Similar Rooms -->
                        <div class="card card-luxury">
                            <div class="card-body">
                                <h4 class="luxury-header mb-3">Similar Rooms</h4>
                                <div class="row" id="similarRooms">
                                    <!-- Similar rooms will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Sidebar -->
                    <div class="col-lg-4">
                        <div class="card card-luxury sticky-top" style="top: 100px;">
                            <div class="card-body">
                                <h5 class="luxury-header mb-3">Book This Room</h5>
                                
                                <form id="bookingForm" onsubmit="handleBooking(event)">
                                    <div class="form-outline mb-3">
                                        <input type="date" id="checkIn" name="check_in" class="form-control" required>
                                        <label class="form-label" for="checkIn">Check-in Date</label>
                                    </div>
                                    
                                    <div class="form-outline mb-3">
                                        <input type="date" id="checkOut" name="check_out" class="form-control" required>
                                        <label class="form-label" for="checkOut">Check-out Date</label>
                                    </div>
                                    
                                    <div class="form-outline mb-3">
                                        <select id="guests" name="guests" class="form-select" required>
                                            <option value="1">1 Guest</option>
                                            <option value="2" selected>2 Guests</option>
                                            <option value="3">3 Guests</option>
                                            <option value="4">4 Guests</option>
                                        </select>
                                        <label class="form-label" for="guests">Number of Guests</label>
                                    </div>
                                    
                                    <div class="form-outline mb-3">
                                        <select id="reservationType" name="reservation_type_id" class="form-select" required>
                                            <option value="1">Premium (Advance Payment)</option>
                                            <option value="2">Standard (Pay on Arrival)</option>
                                        </select>
                                        <label class="form-label" for="reservationType">Reservation Type</label>
                                    </div>
                                    
                                    <!-- Booking Summary -->
                                    <div class="border rounded p-3 mb-3 bg-light">
                                        <h6 class="mb-2">Booking Summary</h6>
                                        <div class="d-flex justify-content-between">
                                            <span>Nights:</span>
                                            <span id="totalNights">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Rate per night:</span>
                                            <span id="ratePerNight">₹0</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Dynamic Multiplier:</span>
                                            <span id="dynamicMultiplier">1.0000x</span>
                                        </div>
                                        <div class="d-flex justify-content-between small text-muted">
                                            <span>Season / Demand / Lead:</span>
                                            <span id="dynamicFactors">Base pricing</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold">
                                            <span>Total:</span>
                                            <span id="totalAmount" class="text-warning">₹0</span>
                                        </div>
                                    </div>
                                    
                                    <?php if (isLoggedIn()): ?>
                                        <button type="submit" class="btn btn-luxury w-100 mb-2">
                                            <i class="fas fa-calendar-check me-1"></i>Book Now
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-luxury w-100 mb-2" data-mdb-toggle="modal" data-mdb-target="#loginModal">
                                            <i class="fas fa-sign-in-alt me-1"></i>Login to Book
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-outline-info w-100" onclick="checkAvailability()">
                                        <i class="fas fa-search me-1"></i>Check Availability
                                    </button>
                                </form>
                                
                                <!-- Availability Status -->
                                <div id="availabilityStatus" class="mt-3" style="display: none;">
                                    <!-- Availability info will appear here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Info -->
                        <div class="card card-luxury mt-4">
                            <div class="card-body text-center">
                                <h6 class="luxury-header mb-3">Need Help?</h6>
                                <p class="text-muted small mb-3">Contact our reservation team</p>
                                <div class="d-grid gap-2">
                                    <a href="tel:+911112345678" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-phone me-1"></i>+91-11-12345678
                                    </a>
                                    <a href="mailto:reservations@heartlandabode.com" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-envelope me-1"></i>Email Us
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Login to Book</h5>
                <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="form-outline mb-3">
                        <input type="email" id="loginEmail" name="email" class="form-control" required>
                        <label class="form-label" for="loginEmail">Email Address</label>
                    </div>
                    <div class="form-outline mb-3">
                        <input type="password" id="loginPassword" name="password" class="form-control" required>
                        <label class="form-label" for="loginPassword">Password</label>
                    </div>
                    <button type="submit" class="btn btn-luxury w-100">Login</button>
                </form>
                <div class="text-center mt-3">
                    <p>Don't have an account? <a href="#" onclick="switchToRegister()">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentRoom = null;
let pricingCache = {};
let currentPricingQuote = null;
const roomImageFallbackUrl = <?php echo json_encode(appUrl('/assets/images/rooms/suite/suite-room.png')); ?>;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum dates
    const today = new Date().toISOString().split('T')[0];
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    document.getElementById('checkIn').value = today;
    document.getElementById('checkIn').min = today;
    document.getElementById('checkOut').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('checkOut').min = tomorrow.toISOString().split('T')[0];
    
    // Update checkout min date when checkin changes
    document.getElementById('checkIn').addEventListener('change', function() {
        const checkInDate = new Date(this.value);
        checkInDate.setDate(checkInDate.getDate() + 1);
        document.getElementById('checkOut').min = checkInDate.toISOString().split('T')[0];
        calculateTotal();
    });
    
    document.getElementById('checkOut').addEventListener('change', calculateTotal);
    
    // Load room details
    loadRoomDetails();
});

// Load room details
async function loadRoomDetails() {
    try {
        const response = await fetch(`../api/room-detail.php?id=<?php echo $room_id; ?>`);
        const data = await response.json();
        
        if (data.success) {
            currentRoom = data.data;
            displayRoomDetails(currentRoom);
            displaySimilarRooms(data.similar_rooms || []);
            calculateTotal();
        } else {
            throw new Error(data.error || 'Room not found');
        }
    } catch (error) {
        showToast('Failed to load room details', 'danger');
        setTimeout(() => {
            window.location.href = 'rooms.php';
        }, 2000);
    } finally {
        document.getElementById('loadingState').style.display = 'none';
        document.getElementById('roomContent').style.display = 'block';
    }
}

// Display room details
function displayRoomDetails(room) {
    // Update page title
    document.title = `Room ${room.room_no} - ${room.room_type_name} - <?php echo APP_NAME; ?>`;
    
    // Update hero section
    const heroImage = document.getElementById('roomHeroImage');
    const heroFallback = room.image_fallback_url || roomImageFallbackUrl;
    heroImage.onerror = function () {
        this.onerror = null;
        this.src = heroFallback;
    };
    heroImage.src = room.image_url || heroFallback;
    document.getElementById('breadcrumbRoom').textContent = `Room ${room.room_no}`;
    document.getElementById('roomTitle').textContent = `Room ${room.room_no}`;
    document.getElementById('roomSubtitle').textContent = `${room.room_type_name} • ${room.tier} TIER`;
    
    // Update room info
    document.getElementById('roomName').textContent = `Room ${room.room_no}`;
    document.getElementById('roomDescription').textContent = room.description || 'Comfortable and well-appointed room with modern amenities.';
    document.getElementById('roomType').textContent = room.room_type_name;
    document.getElementById('roomTier').textContent = `${room.tier} TIER`;
    document.getElementById('roomPrice').textContent = formatCurrency(room.rent);
    document.getElementById('ratePerNight').textContent = formatCurrency(room.rent);
    document.getElementById('hotelName').textContent = room.hotel_name || 'The Heartland Abode';
    document.getElementById('hotelRating').textContent = `${room.star_rating || 5} Star`;
    
    // Update room status
    const statusElement = document.getElementById('roomStatus');
    statusElement.textContent = room.status;
    statusElement.className = `badge ${room.status === 'Available' ? 'bg-success' : 'bg-warning'}`;
    
    // Display features
    displayRoomFeatures(room.features, room.feature_icons);
}

async function fetchDynamicQuote(checkIn, checkOut) {
    const key = `${checkIn}|${checkOut}`;
    if (pricingCache[key]) {
        return pricingCache[key];
    }

    const response = await fetch(`../api/pricing.php?room_id=<?php echo $room_id; ?>&check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`);
    const result = await response.json();
    if (!response.ok || !result.success) {
        throw new Error(result.error || 'Failed to fetch dynamic quote');
    }

    pricingCache[key] = result.data;
    return result.data;
}

// Display room features
function displayRoomFeatures(features, icons) {
    const container = document.getElementById('roomFeatures');
    container.innerHTML = '';
    
    if (features && features.length > 0) {
        features.forEach((feature, index) => {
            const icon = icons && icons[index] ? icons[index] : 'fas fa-check';
            const div = document.createElement('div');
            div.className = 'col-md-6 col-lg-4 mb-3';
            div.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="${icon} text-warning me-2"></i>
                    <span>${feature}</span>
                </div>
            `;
            container.appendChild(div);
        });
    } else {
        container.innerHTML = '<div class="col-12"><p class="text-muted">No specific features listed.</p></div>';
    }
}

// Display similar rooms
function displaySimilarRooms(rooms) {
    const container = document.getElementById('similarRooms');
    container.innerHTML = '';
    
    if (rooms.length === 0) {
        container.innerHTML = '<div class="col-12"><p class="text-muted">No similar rooms available.</p></div>';
        return;
    }
    
    rooms.forEach(room => {
        const div = document.createElement('div');
        div.className = 'col-md-6 mb-3';
        div.innerHTML = `
            <div class="card h-100">
                <img src="${room.image_url || roomImageFallbackUrl}" class="card-img-top" alt="Room ${room.room_no}" loading="lazy" decoding="async" style="height: 150px; object-fit: cover;" onerror="this.onerror=null;this.src='${room.image_fallback_url || roomImageFallbackUrl}';">
                <div class="card-body">
                    <h6 class="card-title">Room ${room.room_no}</h6>
                    <p class="card-text small text-muted">${room.room_type_name} • ${room.tier} TIER</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="text-warning">${formatCurrency(room.rent)}</strong>
                        <a href="room-detail.php?id=${room.room_id}" class="btn btn-outline-primary btn-sm">View</a>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

// Calculate total booking amount
async function calculateTotal() {
    if (!currentRoom) return;
    
    const checkIn = document.getElementById('checkIn').value;
    const checkOut = document.getElementById('checkOut').value;
    
    if (checkIn && checkOut) {
        const checkInDate = new Date(checkIn);
        const checkOutDate = new Date(checkOut);
        const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
        
        if (nights > 0) {
            let total = currentRoom.rent * nights;
            let avgNightRate = currentRoom.rent;
            let avgMultiplier = 1;
            let factorSummary = 'Base pricing';
            currentPricingQuote = null;

            try {
                const quote = await fetchDynamicQuote(checkIn, checkOut);
                currentPricingQuote = quote;
                total = Number(quote.dynamic_total || total);
                avgNightRate = Number(quote.average_nightly_rate || avgNightRate);
                avgMultiplier = Number(quote.average_multiplier || 1);

                const factorParts = [];
                if (quote.factors) {
                    factorParts.push(`Season ${Number(quote.factors.season_multiplier_avg || 1).toFixed(2)}x`);
                    factorParts.push(`Demand ${(Number(quote.factors.demand_adjustment_avg || 0) * 100).toFixed(1)}%`);
                    factorParts.push(`Lead ${(Number(quote.factors.lead_time_adjustment_avg || 0) * 100).toFixed(1)}%`);
                }
                factorSummary = factorParts.join(' | ') || 'Base pricing';
            } catch (error) {
                factorSummary = 'Base pricing fallback';
            }

            document.getElementById('totalNights').textContent = nights;
            document.getElementById('ratePerNight').textContent = formatCurrency(avgNightRate);
            document.getElementById('dynamicMultiplier').textContent = `${avgMultiplier.toFixed(4)}x`;
            document.getElementById('dynamicFactors').textContent = factorSummary;
            document.getElementById('totalAmount').textContent = formatCurrency(total);
        } else {
            document.getElementById('totalNights').textContent = '0';
            document.getElementById('dynamicMultiplier').textContent = '1.0000x';
            document.getElementById('dynamicFactors').textContent = 'Base pricing';
            document.getElementById('totalAmount').textContent = '₹0';
        }
    }
}

// Check room availability
async function checkAvailability() {
    const checkIn = document.getElementById('checkIn').value;
    const checkOut = document.getElementById('checkOut').value;
    
    if (!checkIn || !checkOut) {
        showToast('Please select check-in and check-out dates', 'warning');
        return;
    }
    
    try {
        const response = await fetch(`../api/rooms.php?id=<?php echo $room_id; ?>&check_in=${checkIn}&check_out=${checkOut}`);
        const data = await response.json();
        
        const statusDiv = document.getElementById('availabilityStatus');
        statusDiv.style.display = 'block';
        
        if (data.success && data.data.length > 0) {
            statusDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Available!</strong> This room is available for your selected dates.
                </div>
            `;
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Not Available</strong> This room is not available for your selected dates.
                </div>
            `;
        }
    } catch (error) {
        showToast('Failed to check availability', 'danger');
    }
}

// Handle booking
async function handleBooking(event) {
    event.preventDefault();

    // Show loading immediately
    showLoading();
    
    const formData = new FormData(event.target);
    const bookingData = {
        room_id: <?php echo $room_id; ?>,
        check_in: formData.get('check_in'),
        check_out: formData.get('check_out'),
        reservation_type_id: formData.get('reservation_type_id'),
        guests: formData.get('guests')
    };

    // Enhanced validation
    const validationErrors = [];
    
    if (!bookingData.check_in) validationErrors.push('Check-in date is required');
    if (!bookingData.check_out) validationErrors.push('Check-out date is required');
    if (!bookingData.reservation_type_id) validationErrors.push('Reservation type is required');
    if (!bookingData.guests) validationErrors.push('Number of guests is required');
    
    if (validationErrors.length > 0) {
        hideLoading();
        showToast(validationErrors.join(', '), 'warning');
        return;
    }
    
    // Validate dates
    const checkInDate = new Date(bookingData.check_in);
    const checkOutDate = new Date(bookingData.check_out);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Reset time for date comparison
    
    if (checkInDate < today) {
        hideLoading();
        showToast('Check-in date cannot be in the past', 'warning');
        return;
    }
    
    if (checkOutDate <= checkInDate) {
        hideLoading();
        showToast('Check-out date must be after check-in date', 'warning');
        return;
    }
    
    // Calculate nights for validation
    const nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
    if (nights > 30) {
        hideLoading();
        showToast('Maximum stay is 30 nights', 'warning');
        return;
    }
    
    try {
        const response = await apiRequest('../api/reservations.php', {
            method: 'POST',
            body: JSON.stringify(bookingData)
        });
        
        if (response.success) {
            showToast('Booking created successfully!', 'success');
            setTimeout(() => {
                window.location.href = `booking-success.php?id=${response.data.reservation_id}`;
            }, 1500);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

// Handle login
async function handleLogin(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const loginData = {
        email: formData.get('email'),
        password: formData.get('password'),
        user_type: 'guest'
    };
    
    try {
        const response = await apiRequest('../api/auth/login.php', {
            method: 'POST',
            body: JSON.stringify(loginData)
        });
        
        if (response.success) {
            showToast('Login successful!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    } catch (error) {
        showToast(error.message, 'danger');
    }
}

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
